<?php
/**
 * Bagel Boyz NJ — shared bootstrap for every /api endpoint.
 * JSON in, JSON out, no caching, uniform error shape.
 */

require_once __DIR__ . '/../includes/order-lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

// APIs are same-origin only; nothing here is meant for third parties.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function bb_json($data, $status = 200) {
    http_response_code($status);

    // INVALID_UTF8_SUBSTITUTE is the safety net: without it, one bad byte
    // anywhere in the payload makes json_encode return false and the client
    // receives an empty body with a 200 — the worst possible failure mode.
    $json = json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        error_log('BB API: json_encode failed — ' . json_last_error_msg());
        http_response_code(500);
        echo '{"success":false,"message":"We hit a snag. Please call the shop to order."}';
        exit;
    }

    echo $json;
    exit;
}

function bb_fail($message, $status = 400, $extra = []) {
    bb_json(array_merge(['success' => false, 'message' => $message], $extra), $status);
}

function bb_ok($data = []) {
    bb_json(array_merge(['success' => true], $data));
}

/** Read a JSON body, falling back to form-encoded POST. */
function bb_input() {
    static $input = null;
    if ($input !== null) return $input;

    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $input = $decoded;
    }
    return $input = $_POST;
}

function bb_require_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bb_fail('Method not allowed.', 405);
    }
}

function bb_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Fixed-window rate limiter backed by one row per bucket.
 * Crude but effective against the only realistic threat here: someone
 * scripting junk orders at the kitchen.
 */
function bb_rate_limit($bucket, $maxHits, $windowSeconds = 3600) {
    try {
        $pdo = bb_db();
        $key = substr($bucket, 0, 64);

        $st = $pdo->prepare('SELECT hits, window_start FROM bb_rate_limit WHERE bucket_key = ?');
        $st->execute([$key]);
        $row = $st->fetch();

        $now = time();
        if (!$row) {
            // ON DUPLICATE KEY so two concurrent first-hits don't collide on
            // the primary key and let one slip through uncounted.
            $pdo->prepare(
                'INSERT INTO bb_rate_limit (bucket_key, hits, window_start) VALUES (?,1,NOW())
                 ON DUPLICATE KEY UPDATE hits = hits + 1'
            )->execute([$key]);
            return true;
        }

        $started = strtotime($row['window_start']);
        if (($now - $started) > $windowSeconds) {
            $pdo->prepare('UPDATE bb_rate_limit SET hits = 1, window_start = NOW() WHERE bucket_key = ?')
                ->execute([$key]);
            return true;
        }

        if ((int) $row['hits'] >= $maxHits) return false;

        $pdo->prepare('UPDATE bb_rate_limit SET hits = hits + 1 WHERE bucket_key = ?')->execute([$key]);
        return true;
    } catch (Exception $e) {
        // A broken limiter must not block real customers from ordering.
        error_log('BB API: rate limit check failed — ' . $e->getMessage());
        return true;
    }
}

/**
 * Turn any unexpected failure into a friendly message, while logging the real
 * one. Set 'debug' => true in order-config.php to surface details.
 *
 * Typed as Throwable, not Exception: since PHP 7 a TypeError/ValueError
 * extends Error, which does NOT extend Exception. Catching only Exception
 * let those escape and emit an HTML fatal under an application/json header —
 * i.e. a blank 500 with no clue why.
 */
function bb_handle_exception($e, $friendly = 'Something went wrong on our end. Please call the shop and we\'ll take your order.') {
    error_log('BB API ERROR: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (bb_config('debug', false)) {
        bb_fail($friendly, 500, ['debug' => $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()]);
    }
    bb_fail($friendly, 500);
}
