<?php
/**
 * Bagel Boyz NJ — kitchen display authentication.
 *
 * Deliberately simple: a shared PIN unlocks a long-lived session cookie that
 * outlasts a full shift. Staff should never be typing a password at 6 AM with
 * flour on their hands.
 *
 * The KDS is not a public surface — it exposes customer names and phone
 * numbers — so the session cookie is HttpOnly, SameSite=Lax and (over HTTPS)
 * Secure. Rotate the PIN in order-config.php if it ever gets out.
 */

require_once __DIR__ . '/../includes/order-lib.php';

define('BB_KDS_COOKIE', 'bb_kds');

function bb_kds_is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

/**
 * Current KDS session row, or null.
 * Refreshes last_seen_at so an idle iPad can be spotted on the events log.
 */
function bb_kds_session() {
    static $session = null;
    static $looked  = false;
    if ($looked) return $session;
    $looked = true;

    $token = $_COOKIE[BB_KDS_COOKIE] ?? '';
    if ($token === '' || strlen($token) !== 48) return $session = null;

    try {
        $pdo = bb_db();
        $st  = $pdo->prepare('SELECT * FROM bb_kds_sessions WHERE session_token = ? LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch();

        if (!$row) return $session = null;

        if (strtotime($row['expires_at']) < time()) {
            $pdo->prepare('DELETE FROM bb_kds_sessions WHERE id = ?')->execute([$row['id']]);
            return $session = null;
        }

        $pdo->prepare('UPDATE bb_kds_sessions SET last_seen_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return $session = $row;

    } catch (Exception $e) {
        error_log('BB KDS: session lookup failed — ' . $e->getMessage());
        return $session = null;
    }
}

/** Verify the PIN and open a session. Returns the token, or null. */
function bb_kds_login($pin, $locationId, $deviceLabel = null) {
    $expected = (string) bb_config('kds.pin', '');
    $cfg      = bb_config();

    // Never authenticate against the example config — its PIN is in the repo.
    if (empty($cfg['_configured'])) {
        error_log('BB KDS: refusing sign-in — includes/order-config.php has not been created.');
        return null;
    }

    if ($expected === '' || !isset($cfg['locations'][$locationId])) return null;
    // Constant-time so the PIN can't be recovered by timing the response.
    if (!hash_equals($expected, (string) $pin)) return null;

    $token = bin2hex(random_bytes(24));   // 48 chars
    // Cast + interpolate: MySQL won't reliably bind the INTERVAL quantity.
    // Internal int from config, never user input.
    $hours = max(1, min(720, (int) bb_config('kds.session_hours', 18)));

    $pdo = bb_db();
    $pdo->prepare(
        "INSERT INTO bb_kds_sessions (session_token, location_id, device_label, expires_at)
         VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL {$hours} HOUR))"
    )->execute([$token, $locationId, $deviceLabel ? mb_substr($deviceLabel, 0, 64) : null]);

    // Opportunistic cleanup so the table doesn't grow forever.
    $pdo->exec('DELETE FROM bb_kds_sessions WHERE expires_at < NOW()');

    setcookie(BB_KDS_COOKIE, $token, [
        'expires'  => time() + $hours * 3600,
        'path'     => '/',
        'secure'   => bb_kds_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return $token;
}

function bb_kds_logout() {
    $token = $_COOKIE[BB_KDS_COOKIE] ?? '';
    if ($token !== '') {
        try {
            bb_db()->prepare('DELETE FROM bb_kds_sessions WHERE session_token = ?')->execute([$token]);
        } catch (Exception $e) {
            error_log('BB KDS: logout cleanup failed — ' . $e->getMessage());
        }
    }
    setcookie(BB_KDS_COOKIE, '', [
        'expires' => time() - 3600, 'path' => '/',
        'secure' => bb_kds_is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/** Guard for KDS API endpoints. Emits JSON 401 and exits when signed out. */
function bb_kds_require_session() {
    $session = bb_kds_session();
    if (!$session) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please sign in again.', 'signed_out' => true]);
        exit;
    }
    return $session;
}
