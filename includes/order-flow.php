<?php
/**
 * Bagel Boyz NJ — order lifecycle transitions.
 *
 * The side effects of an order changing state live here and nowhere else, so
 * that a card order (confirmed by the Stripe webhook) and a pay-at-pickup
 * order (confirmed inline) produce byte-identical outcomes: same ticket, same
 * emails, same audit trail.
 */

require_once __DIR__ . '/order-lib.php';
require_once __DIR__ . '/ticket.php';
require_once __DIR__ . '/order-mailer.php';

if (!function_exists('bb_finalize_new_order')) {

    /**
     * An order has become real to the kitchen: queue its ticket and notify.
     * Idempotent — safe if Stripe delivers the same webhook twice.
     */
    function bb_finalize_new_order(PDO $pdo, array $order) {
        // Guard against double-finalizing (Stripe happily delivers the same
        // webhook twice). This checks the EVENT LOG rather than the print
        // queue: bb_queue_print() returns without inserting when printing is
        // disabled, so a queue-based check would silently stop guarding and
        // the customer would get a second receipt on every retry.
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM bb_order_events WHERE order_id = ? AND event = 'confirmed'"
        );
        $st->execute([$order['id']]);
        if ((int) $st->fetchColumn() > 0) {
            error_log('BB FLOW: order ' . $order['order_code'] . ' already finalized — skipping duplicate.');
            return false;
        }

        bb_queue_print($pdo, $order);

        try { bb_email_order_receipt($order); } catch (Exception $e) { error_log('BB MAIL receipt: ' . $e->getMessage()); }
        try { bb_email_store_notify($order); }  catch (Exception $e) { error_log('BB MAIL store: '   . $e->getMessage()); }

        bb_log_event($pdo, $order['id'], 'confirmed', 'ticket queued', 'system');
        return true;
    }

    /**
     * Move an order along the rail. Stamps the matching timestamp column and
     * fires the "your order is ready" email exactly once.
     *
     * Returns ['ok'=>bool,'message'=>string,'order'=>array|null]
     */
    function bb_set_order_status(PDO $pdo, $orderId, $newStatus, $actor = 'kds', $note = null) {
        $allowed = ['new', 'in_progress', 'ready', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) {
            return ['ok' => false, 'message' => 'Unknown status.', 'order' => null];
        }

        $order = bb_fetch_order($pdo, $orderId);
        if (!$order) return ['ok' => false, 'message' => 'Order not found.', 'order' => null];

        // An unpaid card order must never reach the kitchen by a side door.
        if ($order['status'] === 'pending_payment' && $newStatus !== 'cancelled') {
            return ['ok' => false, 'message' => 'That order hasn\'t been paid for yet.', 'order' => null];
        }
        if ($order['status'] === $newStatus) {
            return ['ok' => true, 'message' => 'Already ' . bb_status_label($newStatus) . '.', 'order' => $order];
        }
        if (in_array($order['status'], ['completed', 'cancelled'], true)) {
            return ['ok' => false, 'message' => 'This order is already ' . strtolower(bb_status_label($order['status'])) . '.', 'order' => $order];
        }

        $stampColumn = [
            'new'         => 'confirmed_at',
            'in_progress' => 'started_at',
            'ready'       => 'ready_at',
            'completed'   => 'completed_at',
            'cancelled'   => 'cancelled_at',
        ][$newStatus];

        $sql = "UPDATE bb_orders SET status = ?, {$stampColumn} = NOW()";
        $params = [$newStatus];
        if ($newStatus === 'cancelled' && $note) {
            $sql .= ', cancel_reason = ?';
            $params[] = mb_substr($note, 0, 255);
        }
        $sql .= ' WHERE id = ?';
        $params[] = $orderId;

        $pdo->prepare($sql)->execute($params);
        bb_log_event($pdo, $orderId, 'status:' . $newStatus, $note, $actor);

        $order = bb_fetch_order($pdo, $orderId);

        // Ready email fires once, on the transition itself.
        if ($newStatus === 'ready') {
            try { bb_email_order_ready($order); } catch (Exception $e) { error_log('BB MAIL ready: ' . $e->getMessage()); }
        }

        return ['ok' => true, 'message' => bb_status_label($newStatus), 'order' => $order];
    }
}
