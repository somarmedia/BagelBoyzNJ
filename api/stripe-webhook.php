<?php
/**
 * POST /api/stripe-webhook.php   ← called by Stripe, not by your site
 *
 * This is the ONLY thing that marks a card order paid and releases it to the
 * kitchen. The browser saying "payment worked" is never trusted: a customer
 * could close the tab mid-payment, or fake the success callback entirely.
 *
 * Setup (do this when you connect Stripe):
 *   Stripe Dashboard → Developers → Webhooks → Add endpoint
 *   URL:    https://bagelboyznj.com/api/stripe-webhook.php
 *   Events: payment_intent.succeeded
 *           payment_intent.payment_failed
 *           charge.refunded
 *   Copy the signing secret (whsec_…) into order-config.php → stripe.webhook_secret
 */

require_once __DIR__ . '/../includes/order-flow.php';
require_once __DIR__ . '/../includes/stripe.php';

header('Content-Type: application/json');

// Stripe retries on any non-2xx, which is what we want on a transient failure.
function bb_webhook_out($ok, $message, $status = 200) {
    http_response_code($status);
    echo json_encode(['received' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bb_webhook_out(false, 'Method not allowed', 405);
}

$payload = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$event = bb_stripe_verify_webhook($payload, $sig);
if (!$event) {
    // Either a bad signature or an unconfigured secret. Do NOT process.
    error_log('BB STRIPE WEBHOOK: rejected unverified payload.');
    bb_webhook_out(false, 'Signature verification failed', 400);
}

try {
    $pdo  = bb_db();
    $type = $event['type'] ?? '';
    $obj  = $event['data']['object'] ?? [];

    switch ($type) {

        /* ------------------------------------------------------------- */
        case 'payment_intent.succeeded':
            $intentId = $obj['id'] ?? '';
            $orderId  = (int) ($obj['metadata']['order_id'] ?? 0);

            $order = bb_locate_order($pdo, $orderId, $intentId);
            if (!$order) {
                error_log('BB STRIPE WEBHOOK: no order for intent ' . $intentId);
                bb_webhook_out(true, 'No matching order — acknowledged so Stripe stops retrying');
            }

            if ($order['payment_status'] === 'paid') {
                bb_webhook_out(true, 'Already processed');
            }

            // Guard against an amount mismatch (e.g. an intent edited elsewhere).
            $paid = (int) ($obj['amount_received'] ?? $obj['amount'] ?? 0);
            if ($paid < (int) $order['total_cents']) {
                error_log(sprintf(
                    'BB STRIPE WEBHOOK: underpayment on %s — got %d, expected %d',
                    $order['order_code'], $paid, $order['total_cents']
                ));
                bb_log_event($pdo, $order['id'], 'payment_underpaid', "received {$paid}", 'stripe');
                bb_webhook_out(true, 'Amount mismatch logged');
            }

            // The status guard matters: if staff cancelled the order while the
            // card was in flight, it must NOT reappear on the kitchen board.
            $upd = $pdo->prepare(
                "UPDATE bb_orders
                    SET payment_status = 'paid', status = 'new',
                        stripe_payment_intent = ?, confirmed_at = NOW()
                  WHERE id = ? AND status = 'pending_payment'"
            );
            $upd->execute([$intentId, $order['id']]);

            if ($upd->rowCount() === 0) {
                // Payment landed on an order that is no longer awaiting it.
                // Record the money so it can be refunded by hand.
                $pdo->prepare('UPDATE bb_orders SET payment_status = ? WHERE id = ?')
                    ->execute(['paid', $order['id']]);
                bb_log_event(
                    $pdo, $order['id'], 'payment_after_cancel',
                    'paid while status=' . $order['status'] . ' — may need a refund', 'stripe'
                );
                error_log('BB STRIPE WEBHOOK: ' . $order['order_code'] . ' paid but status was ' . $order['status']);
                bb_webhook_out(true, 'Paid, but order was not awaiting payment — logged for review');
            }

            bb_log_event($pdo, $order['id'], 'payment_succeeded', $intentId, 'stripe');

            // NOW the kitchen gets it: ticket queued, emails sent.
            $order = bb_fetch_order($pdo, (int) $order['id']);
            bb_finalize_new_order($pdo, $order);

            bb_webhook_out(true, 'Order released to kitchen');
            break;

        /* ------------------------------------------------------------- */
        case 'payment_intent.payment_failed':
            $intentId = $obj['id'] ?? '';
            $orderId  = (int) ($obj['metadata']['order_id'] ?? 0);
            $order    = bb_locate_order($pdo, $orderId, $intentId);

            if ($order && $order['payment_status'] !== 'paid') {
                $reason = $obj['last_payment_error']['message'] ?? 'Card declined';
                $pdo->prepare('UPDATE bb_orders SET payment_status = ? WHERE id = ?')
                    ->execute(['failed', $order['id']]);
                bb_log_event($pdo, $order['id'], 'payment_failed', $reason, 'stripe');
            }
            bb_webhook_out(true, 'Failure recorded');
            break;

        /* ------------------------------------------------------------- */
        case 'charge.refunded':
            $intentId = $obj['payment_intent'] ?? '';
            $order    = bb_locate_order($pdo, 0, $intentId);

            if ($order) {
                $fully = !empty($obj['refunded']);
                $pdo->prepare('UPDATE bb_orders SET payment_status = ? WHERE id = ?')
                    ->execute([$fully ? 'refunded' : 'partially_refunded', $order['id']]);
                bb_log_event($pdo, $order['id'], 'refunded', ($obj['amount_refunded'] ?? 0) . ' cents', 'stripe');
            }
            bb_webhook_out(true, 'Refund recorded');
            break;

        /* ------------------------------------------------------------- */
        default:
            // Acknowledge everything else so Stripe doesn't retry forever.
            bb_webhook_out(true, 'Ignored event type: ' . $type);
    }

} catch (Throwable $e) {
    error_log('BB STRIPE WEBHOOK ERROR: ' . $e->getMessage());
    // 500 → Stripe retries with backoff, which is the correct behavior here.
    bb_webhook_out(false, 'Processing error', 500);
}


/** Find the order by metadata id first, falling back to the stored intent id. */
function bb_locate_order(PDO $pdo, $orderId, $intentId) {
    if ($orderId > 0) {
        $order = bb_fetch_order($pdo, $orderId);
        if ($order) return $order;
    }
    if ($intentId) {
        $st = $pdo->prepare('SELECT id FROM bb_orders WHERE stripe_payment_intent = ? LIMIT 1');
        $st->execute([$intentId]);
        $id = $st->fetchColumn();
        if ($id) return bb_fetch_order($pdo, (int) $id);
    }
    return null;
}
