<?php
/**
 * Local print bridge endpoint
 *   GET  /print/bridge.php?loc=holmdel&key=POLL_KEY          → next job (or empty)
 *   POST /print/bridge.php?loc=holmdel&key=POLL_KEY&ack=TOK  → confirm printed
 *
 * Paired with backend/src/print-bridge.js, which runs on any always-on machine
 * on the shop's wifi (a Raspberry Pi, an old laptop, the back-office PC).
 *
 * WHY THIS IS THE RECOMMENDED PATH FOR THE TSP143IIIW
 * ---------------------------------------------------
 * The TSP100III can't pull jobs from the internet (no CloudPRNT firmware), and
 * pushing to it from the iPad runs into the HTTPS→HTTP mixed-content wall that
 * every iOS browser enforces — Chrome included, because iOS forces all browsers
 * onto WebKit.
 *
 * The bridge sidesteps both: it makes an ordinary outbound HTTPS call to this
 * endpoint, then writes raw Star Line Mode bytes to the printer on TCP 9100
 * over the LAN. No browser involved, so the iPad becomes purely a display and
 * tickets print even if nobody is looking at it.
 */

require_once __DIR__ . '/../includes/order-lib.php';
require_once __DIR__ . '/../includes/ticket.php';
require_once __DIR__ . '/_queue.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bb_bridge_out($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------- auth ---------------- */
$expectedKey = (string) bb_config('printing.poll_key', '');

if (!bb_config('_configured', false) || $expectedKey === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING') {
    bb_bridge_out(['ok' => false, 'message' => 'Printing is not configured yet.'], 503);
}
if ($expectedKey === '' || !hash_equals($expectedKey, (string) ($_GET['key'] ?? ''))) {
    bb_bridge_out(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

$location = (string) ($_GET['loc'] ?? '');
$cfg      = bb_config();
if (!isset($cfg['locations'][$location])) {
    bb_bridge_out(['ok' => false, 'message' => 'Unknown location.'], 400);
}

if (!bb_config('printing.enabled', true) || !bb_config('printing.bridge_enabled', true)) {
    bb_bridge_out(['ok' => true, 'empty' => true, 'disabled' => true]);
}

try {
    $pdo = bb_db();

    /* =================================================================
       POST ?ack=<job_token> — the bridge confirms the printer took it.
       ================================================================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string) ($_GET['ack'] ?? '');
        $ok    = ($_GET['result'] ?? 'ok') === 'ok';

        if ($token === '') {
            bb_bridge_out(['ok' => false, 'message' => 'Missing ack token.'], 400);
        }

        $st = $pdo->prepare('SELECT * FROM bb_print_jobs WHERE job_token = ? AND location_id = ? LIMIT 1');
        $st->execute([$token, $location]);
        $job = $st->fetch();

        if ($job) {
            bb_complete_job($pdo, $job, $ok ? '200' : 'bridge-failed');
        }
        bb_bridge_out(['ok' => true]);
    }

    /* =================================================================
       GET — hand over the next queued ticket as raw printer bytes.
       ================================================================= */
    bb_requeue_stale_jobs($pdo, $location);

    $job = bb_claim_next_job($pdo, $location, 'bridge:' . substr((string) ($_GET['host'] ?? 'local'), 0, 40));
    if (!$job) {
        bb_bridge_out(['ok' => true, 'empty' => true]);
    }

    $order = bb_fetch_order($pdo, (int) $job['order_id']);
    if (!$order) {
        $pdo->prepare("UPDATE bb_print_jobs SET status='failed', last_error='order missing' WHERE id=?")
            ->execute([$job['id']]);
        bb_bridge_out(['ok' => true, 'empty' => true]);
    }

    $bytes  = bb_ticket_starline($order);
    $copies = max(1, (int) $job['copies']);

    bb_bridge_out([
        'ok'         => true,
        'empty'      => false,
        'job_token'  => $job['job_token'],
        'order_code' => $order['order_code'],
        // base64 so the raw ESC bytes survive JSON transport intact.
        'payload'    => base64_encode(str_repeat($bytes, $copies)),
    ]);

} catch (Throwable $e) {
    error_log('BB PRINT [bridge]: ' . $e->getMessage());
    bb_bridge_out(['ok' => false, 'message' => 'Server error.'], 500);
}
