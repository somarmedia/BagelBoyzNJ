<?php
/**
 * Epson Server Direct Print endpoint
 *   /print/epos.php?loc=holmdel&key=YOUR_POLL_KEY
 *
 * Kept alongside the Star drivers so an Epson TM-m30 can be dropped in
 * without touching anything else — it drains the same bb_print_jobs queue.
 *
 * Protocol (Epson Server Direct Print):
 *   POST ConnectionType=GetRequest  → we answer with a PrintRequestInfo document
 *   POST ConnectionType=SetResponse → printer reports the result, we ack
 */

require_once __DIR__ . '/../includes/order-lib.php';
require_once __DIR__ . '/../includes/ticket.php';
require_once __DIR__ . '/_queue.php';

header('Content-Type: text/xml; charset=utf-8');

function bb_epos_empty() {
    // "Nothing to print" is an empty PrintRequestInfo.
    echo '<?xml version="1.0" encoding="utf-8"?>' . "\n"
       . '<PrintRequestInfo Version="3.00"><ePOSPrint></ePOSPrint></PrintRequestInfo>';
    exit;
}

/* ---------------- auth ---------------- */
$expectedKey = (string) bb_config('printing.poll_key', '');

// The example config's poll_key is public, so refuse until a real one exists.
if (!bb_config('_configured', false) || $expectedKey === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING') {
    http_response_code(503);
    bb_epos_empty();
}

if ($expectedKey === '' || !hash_equals($expectedKey, (string) ($_GET['key'] ?? ''))) {
    http_response_code(401);
    bb_epos_empty();
}

$location = (string) ($_GET['loc'] ?? '');
$cfg      = bb_config();
if (!isset($cfg['locations'][$location])) {
    http_response_code(400);
    bb_epos_empty();
}

if (!bb_config('printing.enabled', true) || !bb_config('printing.epson_enabled', true)) {
    bb_epos_empty();
}

try {
    $pdo = bb_db();

    $connectionType = $_GET['ConnectionType'] ?? $_POST['ConnectionType'] ?? '';
    $printerId      = (string) ($_GET['printerid'] ?? $_POST['printerid'] ?? 'epson');

    /* =================================================================
       SetResponse — the printer telling us how the last job went.
       ================================================================= */
    if ($connectionType === 'SetResponse') {
        $jobId = (string) ($_GET['printjobid'] ?? $_POST['printjobid'] ?? '');
        $code  = (string) ($_GET['code'] ?? $_POST['code'] ?? '');
        // Epson uses an empty code / "PrintSuccess" to mean it worked.
        $ok    = ($code === '' || stripos($code, 'success') !== false);

        if ($jobId !== '') {
            $st = $pdo->prepare('SELECT * FROM bb_print_jobs WHERE job_token = ? AND location_id = ? LIMIT 1');
            $st->execute([$jobId, $location]);
            $job = $st->fetch();
            if ($job) {
                bb_complete_job($pdo, $job, $ok ? '200' : $code);
            }
        }

        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n"
           . '<PrintResponseInfo><ePOSPrint><Response success="true" code="" status="" battery=""/></ePOSPrint></PrintResponseInfo>';
        exit;
    }

    /* =================================================================
       GetRequest — the printer asking for work.
       ================================================================= */

    // Reclaim jobs a printer took but never confirmed.
    $pdo->prepare(
        "UPDATE bb_print_jobs
            SET status = 'queued', claimed_by = NULL, claimed_at = NULL
          WHERE location_id = ? AND status = 'claimed'
            AND claimed_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)"
    )->execute([$location]);

    $job = bb_claim_next_job($pdo, $location, 'epson:' . $printerId);
    if (!$job) bb_epos_empty();

    $order = bb_fetch_order($pdo, (int) $job['order_id']);
    if (!$order) {
        $pdo->prepare("UPDATE bb_print_jobs SET status='failed', last_error='order missing' WHERE id=?")
            ->execute([$job['id']]);
        bb_epos_empty();
    }

    $body   = bb_ticket_epos($order);
    $copies = max(1, (int) $job['copies']);
    if ($copies > 1) {
        // Repeat the inner payload rather than nesting whole documents.
        $inner  = preg_replace('#^<epos-print[^>]*>|</epos-print>$#', '', $body);
        $body   = '<epos-print xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print">'
                . str_repeat($inner, $copies) . '</epos-print>';
    }

    echo '<?xml version="1.0" encoding="utf-8"?>' . "\n"
       . '<PrintRequestInfo Version="3.00"><ePOSPrint>'
       . '<Parameter>'
       . '<devid>local_printer</devid>'
       . '<timeout>10000</timeout>'
       . '<printjobid>' . bb_xml($job['job_token']) . '</printjobid>'
       . '</Parameter>'
       . '<PrintData>' . $body . '</PrintData>'
       . '</ePOSPrint></PrintRequestInfo>';

} catch (Throwable $e) {
    error_log('BB PRINT [epson]: ' . $e->getMessage());
    http_response_code(500);
    bb_epos_empty();
}
