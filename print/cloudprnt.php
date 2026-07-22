<?php
/**
 * Star CloudPRNT endpoint  —  /print/cloudprnt.php?loc=holmdel&key=YOUR_POLL_KEY
 *
 * Point the printer here in its CloudPRNT settings and it will pull jobs by
 * itself over wifi. No computer, no driver, no app. Works behind any NAT
 * because the printer makes all the connections outbound.
 *
 * Protocol (Star CloudPRNT 1.x):
 *   POST   → "got anything for me?"  We answer {jobReady:true, jobToken:...}
 *   GET    → "give me the job"       We answer with Star Markup
 *   DELETE → "printed it, thanks"    We mark the job done
 *
 * Tested against the TSP100IV / TSP143IV markup dialect.
 */

require_once __DIR__ . '/../includes/order-lib.php';
require_once __DIR__ . '/../includes/ticket.php';
require_once __DIR__ . '/_queue.php';

/* ---------------- auth: shared secret in the printer's URL ---------------- */
$expectedKey = (string) bb_config('printing.poll_key', '');
$givenKey    = (string) ($_GET['key'] ?? '');

// The example config's poll_key is public, so refuse until a real one exists.
if (!bb_config('_configured', false) || $expectedKey === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['jobReady' => false]);
    exit;
}

if ($expectedKey === '' || !hash_equals($expectedKey, $givenKey)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['jobReady' => false]);
    exit;
}

$location = (string) ($_GET['loc'] ?? '');
$cfg      = bb_config();
if (!isset($cfg['locations'][$location])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['jobReady' => false]);
    exit;
}

if (!bb_config('printing.enabled', true) || !bb_config('printing.star_enabled', true)) {
    header('Content-Type: application/json');
    echo json_encode(['jobReady' => false]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = bb_db();

    /* =================================================================
       POST — the printer's heartbeat. Answer whether work is waiting.
       ================================================================= */
    if ($method === 'POST') {
        header('Content-Type: application/json');

        $body      = json_decode(file_get_contents('php://input'), true) ?: [];
        $printerId = (string) ($body['printerMAC'] ?? 'unknown');

        // A printer reporting an error state (out of paper, cover open) should
        // not be handed a ticket — it would be silently lost.
        $statusCode = (string) ($body['statusCode'] ?? '200 OK');
        if (strpos($statusCode, '200') !== 0) {
            error_log("BB PRINT [star/{$location}]: printer not ready — {$statusCode}");
            echo json_encode(['jobReady' => false]);
            exit;
        }

        // Recover jobs a printer claimed but never confirmed (power cut mid-print).
        bb_requeue_stale_jobs($pdo, $location);

        $job = bb_claim_next_job($pdo, $location, 'star:' . $printerId);

        if (!$job) {
            echo json_encode(['jobReady' => false]);
            exit;
        }

        echo json_encode([
            'jobReady'   => true,
            'mediaTypes' => ['text/vnd.star.markup'],
            'jobToken'   => $job['job_token'],
        ]);
        exit;
    }

    /* =================================================================
       GET — hand over the actual ticket content.
       ================================================================= */
    if ($method === 'GET') {
        $token = (string) ($_GET['token'] ?? '');

        $st = $pdo->prepare("SELECT * FROM bb_print_jobs WHERE job_token = ? AND location_id = ? LIMIT 1");
        $st->execute([$token, $location]);
        $job = $st->fetch();

        if (!$job) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo '';
            exit;
        }

        $order = bb_fetch_order($pdo, (int) $job['order_id']);
        if (!$order) {
            // Order vanished — retire the job so it doesn't loop forever.
            $pdo->prepare("UPDATE bb_print_jobs SET status='failed', last_error='order missing' WHERE id=?")
                ->execute([$job['id']]);
            http_response_code(404);
            exit;
        }

        header('Content-Type: text/vnd.star.markup');
        $markup = bb_ticket_star($order);

        // Extra copies: repeat the whole ticket, each ending in its own cut.
        $copies = max(1, (int) $job['copies']);
        echo str_repeat($markup, $copies);
        exit;
    }

    /* =================================================================
       DELETE — the printer confirms it printed. Retire the job.
       ================================================================= */
    if ($method === 'DELETE') {
        $token = (string) ($_GET['token'] ?? '');
        $code  = (string) ($_GET['code'] ?? '');   // Star sends a result code

        $st = $pdo->prepare("SELECT * FROM bb_print_jobs WHERE job_token = ? AND location_id = ? LIMIT 1");
        $st->execute([$token, $location]);
        $job = $st->fetch();

        if ($job) {
            bb_complete_job($pdo, $job, $code);
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['jobReady' => false]);

} catch (Throwable $e) {
    error_log('BB PRINT [star]: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['jobReady' => false]);
}
