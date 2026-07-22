<?php
/**
 * GET /api/order-status.php?code=BB-4821&t=<track_token>
 *
 * Public endpoint powering the customer tracking page. Requires BOTH the
 * order code and its 32-char token — the code alone is guessable, so the
 * token is what actually authorizes reading the order.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    $code  = trim((string) ($_GET['code'] ?? ''));
    $token = trim((string) ($_GET['t'] ?? ''));

    if ($code === '' || $token === '') {
        bb_fail('Missing order details.', 400);
    }

    $pdo = bb_db();
    $st  = $pdo->prepare('SELECT * FROM bb_orders WHERE order_code = ? LIMIT 1');
    $st->execute([$code]);
    $row = $st->fetch();

    // Constant-time compare, and an identical response either way, so this
    // can't be used to probe which order codes exist.
    if (!$row || !hash_equals($row['track_token'], $token)) {
        bb_fail('We couldn\'t find that order. Double-check the link from your confirmation email.', 404);
    }

    $order = bb_fetch_order($pdo, (int) $row['id']);
    $cfg   = bb_config();
    $loc   = $cfg['locations'][$order['location_id']] ?? [];

    $payload = bb_order_public($order, true);

    $payload['location'] = [
        'name'    => $loc['name']    ?? '',
        'address' => $loc['address'] ?? '',
        'phone'   => $loc['phone']   ?? '',
    ];

    // A simple ordered pipeline for the progress bar on the tracking page.
    $steps = ['new' => 'Received', 'in_progress' => 'Being made', 'ready' => 'Ready', 'completed' => 'Picked up'];
    $order_of = array_keys($steps);
    $currentIndex = array_search($order['status'], $order_of, true);

    $payload['progress'] = [];
    foreach ($steps as $key => $label) {
        $idx = array_search($key, $order_of, true);
        $payload['progress'][] = [
            'key'   => $key,
            'label' => $label,
            'done'  => $currentIndex !== false && $idx <= $currentIndex,
            'current' => $key === $order['status'],
        ];
    }
    $payload['is_cancelled'] = $order['status'] === 'cancelled';
    $payload['is_terminal']  = in_array($order['status'], ['completed', 'cancelled'], true);

    // Rough "minutes until ready" for the pending states.
    $payload['eta_minutes'] = null;
    if (!$payload['is_terminal'] && $order['status'] !== 'ready' && !empty($order['pickup_at'])) {
        $tz  = new DateTimeZone(bb_config('timezone', 'America/New_York'));
        $eta = new DateTime($order['pickup_at'], $tz);
        $diff = (int) round(($eta->getTimestamp() - bb_now()->getTimestamp()) / 60);
        $payload['eta_minutes'] = max(0, $diff);
        $payload['pickup_label'] = $eta->format('g:i A');
    }

    bb_ok(['order' => $payload]);

} catch (Throwable $e) {
    bb_handle_exception($e, 'Could not load your order status. Please call the shop.');
}
