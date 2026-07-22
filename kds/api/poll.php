<?php
/**
 * GET /kds/api/poll.php?since=<order_id>
 *
 * The heartbeat the iPad runs every few seconds. Returns the live board plus
 * a `new_order_ids` list — that list is what triggers the repeating
 * "YOU HAVE A NEW ORDER" announcement in js/kds.js.
 *
 * Shared hosting can't hold a WebSocket open, so this is short polling. At a
 * 5-second interval it's a handful of indexed reads per tick — nothing.
 */

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../includes/order-flow.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$session = bb_kds_require_session();

try {
    $pdo      = bb_db();
    $location = $session['location_id'];
    $since    = (int) ($_GET['since'] ?? 0);

    /* ---- the board: everything not yet handed to a customer ---- */
    $st = $pdo->prepare(
        "SELECT * FROM bb_orders
          WHERE location_id = ?
            AND status IN ('new','in_progress','ready')
          ORDER BY
            CASE pickup_type WHEN 'scheduled' THEN pickup_at ELSE created_at END ASC,
            id ASC
          LIMIT 100"
    );
    $st->execute([$location]);
    $rows = $st->fetchAll();

    // Two queries for the whole board, not three per order — this runs every
    // 5 seconds on every iPad, so the N+1 version was the one real scaling
    // risk in the system.
    $rows = bb_hydrate_orders($pdo, $rows);

    $orders       = [];
    $newOrderIds  = [];
    $maxId        = $since;
    $tz           = new DateTimeZone(bb_config('timezone', 'America/New_York'));
    $nowTs        = time();

    foreach ($rows as $order) {
        $pub = bb_order_public($order, true);

        $pub['id']           = (int) $order['id'];
        $pub['print_status'] = $order['print_status'];
        // Orders placed while the system is in preview — flagged so staff
        // never start making a test sandwich.
        $pub['is_test']      = ($order['source'] ?? 'web') === 'preview';

        // Minutes since the order landed — drives the colour-coded age badge.
        $pub['age_minutes'] = max(0, (int) round(($nowTs - strtotime($order['created_at'])) / 60));

        if (!empty($order['pickup_at'])) {
            $pu = new DateTime($order['pickup_at'], $tz);
            $pub['pickup_label']   = $pu->format('g:i A');
            $pub['minutes_until']  = (int) round(($pu->getTimestamp() - $nowTs) / 60);
            // A scheduled order isn't "late" until its promised time passes.
            $pub['is_due']         = $pub['minutes_until'] <= 5;
            $pub['is_late']        = $pub['minutes_until'] < 0;
        } else {
            $pub['pickup_label'] = 'ASAP';
            $pub['is_due']       = $pub['age_minutes'] >= 10;
            $pub['is_late']      = $pub['age_minutes'] >= 20;
        }

        $orders[] = $pub;

        if ((int) $order['id'] > $since) {
            $maxId = max($maxId, (int) $order['id']);
            // Only a genuinely-unstarted order should set off the alarm.
            if ($order['status'] === 'new') {
                $newOrderIds[] = (int) $order['id'];
            }
        }
    }

    /* ---- counts for the header ---- */
    $counts = ['new' => 0, 'in_progress' => 0, 'ready' => 0];
    foreach ($orders as $o) {
        if (isset($counts[$o['status']])) $counts[$o['status']]++;
    }

    /* ---- store state, so the iPad can show/flip the switches ---- */
    $state = bb_store_state($location);

    bb_kds_poll_out([
        'success'       => true,
        'orders'        => $orders,
        'counts'        => $counts,
        'new_order_ids' => $newOrderIds,
        'cursor'        => $maxId,
        // A first poll (since=0) must never trigger the alarm for the whole
        // existing board — the client uses this to prime its cursor silently.
        'primed'        => $since === 0,
        'store' => [
            'accepting'    => (int) $state['accepting'] === 1,
            'prep_minutes' => (int) $state['prep_minutes'],
            'pause_until'  => $state['pause_until'],
            'pause_reason' => $state['pause_reason'],
        ],
        'location'      => $location,
        'location_name' => bb_config('locations.' . $location . '.name', $location),
        'server_time'   => bb_now()->format('g:i A'),
    ]);

} catch (Throwable $e) {
    error_log('BB KDS poll: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load orders.']);
}

function bb_kds_poll_out($data) {
    // INVALID_UTF8_SUBSTITUTE so one bad byte in a customer note can't blank
    // the entire kitchen board with an empty 200.
    $json = json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        error_log('BB KDS poll: json_encode failed — ' . json_last_error_msg());
        http_response_code(500);
        echo '{"success":false,"message":"Could not load orders."}';
        exit;
    }

    echo $json;
    exit;
}
