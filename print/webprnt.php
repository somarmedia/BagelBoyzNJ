<?php
/**
 * Star WebPRNT ticket source  —  for the TSP100III (LAN / WiFi models)
 *   GET /print/webprnt.php?order_id=123      (KDS session required)
 *   GET /print/webprnt.php?job=<job_token>   (claims the next queued job)
 *
 * WHY THIS EXISTS
 * ---------------
 * The TSP100III has no CloudPRNT firmware, so it cannot pull jobs from this
 * server the way a TSP100IV can. Instead it runs Star WebPRNT: the iPad on the
 * same LAN PUSHES a ticket to the printer at
 *
 *     POST http://<printer-ip>/StarWebPRNT/SendMessage
 *
 * So the flow is: KDS fetches the XML from here → KDS posts it to the printer.
 * This endpoint only produces the XML; js/kds.js does the pushing.
 *
 * MIXED-CONTENT NOTE
 * ------------------
 * bagelboyznj.com is HTTPS and the printer is plain HTTP on a private IP, so
 * Safari will block the push from a normal browser tab. Two ways around it,
 * both covered in ORDERING.md:
 *   1. Run the KDS inside Star's free "Star WebPRNT Browser" iOS app, or
 *   2. Use the AirPrint fallback button (works anywhere, needs a tap).
 */

require_once __DIR__ . '/../includes/order-lib.php';
require_once __DIR__ . '/../includes/ticket.php';
require_once __DIR__ . '/_queue.php';
require_once __DIR__ . '/../kds/_auth.php';

// This one IS called from the browser, so it authenticates as the KDS
// rather than with the printer poll key.
$session = bb_kds_session();
if (!$session) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not signed in.']);
    exit;
}

if (!bb_config('printing.enabled', true) || !bb_config('printing.webprnt_enabled', true)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'empty' => true, 'disabled' => true]);
    exit;
}

try {
    $pdo      = bb_db();
    $location = $session['location_id'];
    $job      = null;
    $order    = null;

    if (!empty($_GET['order_id'])) {
        /* ---- explicit reprint of a known order ---- */
        $order = bb_fetch_order($pdo, (int) $_GET['order_id']);
        if (!$order || $order['location_id'] !== $location) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
    } else {
        /* ---- drain the queue: hand back the next waiting ticket ---- */
        bb_requeue_stale_jobs($pdo, $location);
        $job = bb_claim_next_job($pdo, $location, 'webprnt:' . ($session['device_label'] ?: 'ipad'));

        if (!$job) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'empty' => true]);
            exit;
        }

        $order = bb_fetch_order($pdo, (int) $job['order_id']);
        if (!$order) {
            $pdo->prepare("UPDATE bb_print_jobs SET status='failed', last_error='order missing' WHERE id=?")
                ->execute([$job['id']]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'empty' => true]);
            exit;
        }
    }

    $copies = $job ? max(1, (int) $job['copies']) : 1;
    $xml    = bb_ticket_webprnt($order);

    if ($copies > 1) {
        // Repeat the printable body, not the XML declaration.
        $inner = preg_replace('#^.*?<root>|</root>\s*$#s', '', $xml);
        $xml   = '<?xml version="1.0" encoding="utf-8"?>' . "\n<root>" . str_repeat($inner, $copies) . "</root>";
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success'    => true,
        'empty'      => false,
        'order_code' => $order['order_code'],
        'order_id'   => (int) $order['id'],
        'job_token'  => $job ? $job['job_token'] : null,
        'xml'        => $xml,
    ]);

} catch (Throwable $e) {
    error_log('BB PRINT [webprnt]: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Could not build the ticket.']);
}
