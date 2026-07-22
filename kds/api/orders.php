<?php
/**
 * GET /kds/api/orders.php
 *
 * Order history / review. The KDS board only shows what's still being worked
 * on; this is how you look back at anything already handed over.
 *
 *   ?view=list   &from=YYYY-MM-DD &to=YYYY-MM-DD &status=all &q=&page=1
 *   ?view=detail &id=123
 *
 * Scoped to the signed-in location, same as the rest of the KDS.
 */

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../includes/order-flow.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$session = bb_kds_require_session();

function bb_orders_out($data, $status = 200) {
    http_response_code($status);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json === false ? '{"success":false,"message":"Could not encode orders."}' : $json;
    exit;
}

try {
    $pdo      = bb_db();
    $location = $session['location_id'];
    $view     = $_GET['view'] ?? 'list';
    $tz       = new DateTimeZone(bb_config('timezone', 'America/New_York'));

    /* =================================================================
       DETAIL — one order, plus its event timeline
       ================================================================= */
    if ($view === 'detail') {
        $order = bb_fetch_order($pdo, (int) ($_GET['id'] ?? 0));
        if (!$order || $order['location_id'] !== $location) {
            bb_orders_out(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $st = $pdo->prepare('SELECT * FROM bb_order_events WHERE order_id = ? ORDER BY created_at, id');
        $st->execute([$order['id']]);

        $timeline = [];
        foreach ($st->fetchAll() as $e) {
            $timeline[] = [
                'event' => $e['event'],
                'note'  => $e['note'],
                'actor' => $e['actor'],
                'at'    => (new DateTime($e['created_at'], $tz))->format('g:i:s A'),
            ];
        }

        $pub = bb_order_public($order, true);
        $pub['id']             = (int) $order['id'];
        $pub['is_test']        = ($order['source'] ?? 'web') === 'preview';
        $pub['print_status']   = $order['print_status'];
        $pub['customer_email'] = $order['customer_email'];
        $pub['cancel_reason']  = $order['cancel_reason'];
        $pub['placed_at']      = (new DateTime($order['created_at'], $tz))->format('D M j, g:i A');
        $pub['timeline']       = $timeline;

        // How long it actually took, start to handed over.
        if (!empty($order['completed_at'])) {
            $mins = (int) round((strtotime($order['completed_at']) - strtotime($order['created_at'])) / 60);
            $pub['fulfilment_minutes'] = max(0, $mins);
        }

        bb_orders_out(['success' => true, 'order' => $pub]);
    }

    /* =================================================================
       LIST
       ================================================================= */
    $from   = (string) ($_GET['from'] ?? '');
    $to     = (string) ($_GET['to'] ?? '');
    $status = (string) ($_GET['status'] ?? 'all');
    $q      = trim((string) ($_GET['q'] ?? ''));
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $per    = 50;

    // Default to today.
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $today;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $from;
    if ($to < $from) { $tmp = $from; $from = $to; $to = $tmp; }

    // Half-open range on a string comparison — works identically on SQLite
    // (TEXT) and MySQL (DATETIME), and avoids any date-function dialect.
    $lower = $from . ' 00:00:00';
    $upper = (new DateTime($to, $tz))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

    $where  = ['location_id = ?', 'created_at >= ?', 'created_at < ?'];
    $params = [$location, $lower, $upper];

    if ($status !== 'all') {
        if ($status === 'active') {
            $where[] = "status IN ('new','in_progress','ready')";
        } else {
            $where[] = 'status = ?';
            $params[] = $status;
        }
    }

    if ($q !== '') {
        // Order code, name, or phone. Digits-only so a phone matches however
        // it was typed.
        $digits = preg_replace('/\D+/', '', $q);
        $where[] = '(order_code LIKE ? OR customer_name LIKE ? OR ' .
                   "REPLACE(REPLACE(REPLACE(REPLACE(customer_phone,'(',''),')',''),' ',''),'-','') LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . ($digits !== '' ? $digits : $q) . '%';
    }

    $whereSql = implode(' AND ', $where);

    /* ---- totals for the range (before pagination) ---- */
    $st = $pdo->prepare(
        "SELECT COUNT(*) AS n,
                COALESCE(SUM(subtotal_cents),0) AS sub,
                COALESCE(SUM(tax_cents),0)      AS tax,
                COALESCE(SUM(tip_cents),0)      AS tip,
                COALESCE(SUM(total_cents),0)    AS tot
           FROM bb_orders
          WHERE {$whereSql} AND status != 'cancelled' AND source != 'preview'"
    );
    $st->execute($params);
    $sum = $st->fetch() ?: ['n' => 0, 'sub' => 0, 'tax' => 0, 'tip' => 0, 'tot' => 0];

    $st = $pdo->prepare("SELECT COUNT(*) FROM bb_orders WHERE {$whereSql}");
    $st->execute($params);
    $matched = (int) $st->fetchColumn();

    /* ---- the page of orders ---- */
    $offset = ($page - 1) * $per;
    $st = $pdo->prepare(
        "SELECT * FROM bb_orders WHERE {$whereSql} ORDER BY created_at DESC, id DESC LIMIT {$per} OFFSET {$offset}"
    );
    $st->execute($params);
    $rows = $st->fetchAll();

    $orders = [];
    foreach ($rows as $r) {
        $created = new DateTime($r['created_at'], $tz);
        $orders[] = [
            'id'             => (int) $r['id'],
            'order_code'     => $r['order_code'],
            'status'         => $r['status'],
            'status_label'   => bb_status_label($r['status']),
            'customer_name'  => $r['customer_name'],
            'customer_phone' => $r['customer_phone'],
            'item_count'     => (int) $r['item_count'],
            'total'          => bb_money($r['total_cents']),
            'payment_method' => $r['payment_method'],
            'payment_status' => $r['payment_status'],
            'pickup_type'    => $r['pickup_type'],
            'is_test'        => ($r['source'] ?? 'web') === 'preview',
            'date'           => $created->format('D M j'),
            'time'           => $created->format('g:i A'),
        ];
    }

    bb_orders_out([
        'success' => true,
        'orders'  => $orders,
        'range'   => ['from' => $from, 'to' => $to, 'today' => $today],
        'filters' => ['status' => $status, 'q' => $q],
        'paging'  => [
            'page'    => $page,
            'per'     => $per,
            'matched' => $matched,
            'pages'   => max(1, (int) ceil($matched / $per)),
        ],
        // Test orders and cancellations are excluded from the money figures.
        'totals'  => [
            'count'    => (int) $sum['n'],
            'subtotal' => bb_money($sum['sub']),
            'tax'      => bb_money($sum['tax']),
            'tips'     => bb_money($sum['tip']),
            'gross'    => bb_money($sum['tot']),
        ],
        'location_name' => bb_config('locations.' . $location . '.name', $location),
    ]);

} catch (Throwable $e) {
    error_log('BB KDS orders: ' . $e->getMessage());
    bb_orders_out(['success' => false, 'message' => 'Could not load orders.'], 500);
}
