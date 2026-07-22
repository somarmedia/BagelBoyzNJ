<?php
/**
 * POST /kds/api/update.php
 *
 * Every action a staff member can take from the iPad:
 *   { action: 'status',    order_id, status, note }
 *   { action: 'reprint',   order_id }
 *   { action: 'availability', item_id, available }        ← the 86 board
 *   { action: 'store',     accepting, prep_minutes, pause_minutes, pause_reason }
 *   { action: 'ticket',    order_id }                     ← HTML for AirPrint
 */

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../includes/order-flow.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$session = bb_kds_require_session();

function bb_upd_out($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bb_upd_out(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$in       = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action   = $in['action'] ?? '';
$location = $session['location_id'];
$actor    = 'kds:' . ($session['device_label'] ?: 'ipad');

try {
    $pdo = bb_db();

    /**
     * Scope every order action to the signed-in location, so a session at one
     * store can never touch the other store's board.
     */
    $loadOrder = function ($orderId) use ($pdo, $location) {
        $order = bb_fetch_order($pdo, (int) $orderId);
        if (!$order || $order['location_id'] !== $location) return null;
        return $order;
    };

    switch ($action) {

        /* ============================================================= */
        case 'status': {
            $order = $loadOrder($in['order_id'] ?? 0);
            if (!$order) bb_upd_out(['success' => false, 'message' => 'Order not found.'], 404);

            $result = bb_set_order_status(
                $pdo, (int) $order['id'],
                (string) ($in['status'] ?? ''),
                $actor,
                isset($in['note']) ? (string) $in['note'] : null
            );

            if (!$result['ok']) {
                bb_upd_out(['success' => false, 'message' => $result['message']], 409);
            }

            bb_upd_out([
                'success' => true,
                'message' => $result['message'],
                'order'   => bb_order_public($result['order'], true) + ['id' => (int) $result['order']['id']],
            ]);
        }

        /* ============================================================= */
        case 'reprint': {
            $order = $loadOrder($in['order_id'] ?? 0);
            if (!$order) bb_upd_out(['success' => false, 'message' => 'Order not found.'], 404);

            $st = $pdo->prepare(
                'INSERT INTO bb_print_jobs (order_id, location_id, copies, status, job_token) VALUES (?,?,?,?,?)'
            );
            $st->execute([
                $order['id'], $location,
                max(1, (int) bb_config('printing.copies', 1)),
                'queued', bb_token(),
            ]);

            $pdo->prepare("UPDATE bb_orders SET print_status = 'queued' WHERE id = ?")->execute([$order['id']]);
            bb_log_event($pdo, $order['id'], 'reprint_requested', null, $actor);

            bb_upd_out(['success' => true, 'message' => 'Ticket queued for reprint.']);
        }

        /* ============================================================= */
        case 'ticket': {
            // Rendered HTML for the AirPrint fallback / on-screen preview.
            $order = $loadOrder($in['order_id'] ?? 0);
            if (!$order) bb_upd_out(['success' => false, 'message' => 'Order not found.'], 404);

            bb_upd_out([
                'success' => true,
                'html'    => bb_ticket_html($order),
                'text'    => bb_ticket_text($order),
                'order_code' => $order['order_code'],
            ]);
        }

        /* ============================================================= */
        case 'availability': {
            $itemId    = (string) ($in['item_id'] ?? '');
            $available = !empty($in['available']) ? 1 : 0;

            $index = bb_item_index();
            if (!isset($index[$itemId])) {
                bb_upd_out(['success' => false, 'message' => 'Unknown menu item.'], 404);
            }

            if ($available) {
                $pdo->prepare('DELETE FROM bb_item_availability WHERE location_id = ? AND menu_item_id = ?')
                    ->execute([$location, $itemId]);
            } else {
                // 86'd until someone turns it back on. Swap NULL for CURDATE()
                // below if you'd rather everything auto-restores each morning.
                $pdo->prepare(
                    'INSERT INTO bb_item_availability (location_id, menu_item_id, available, until_date)
                     VALUES (?,?,0,NULL)
                     ON DUPLICATE KEY UPDATE available = 0, until_date = NULL'
                )->execute([$location, $itemId]);
            }

            bb_upd_out([
                'success'   => true,
                'message'   => $index[$itemId]['name'] . ($available ? ' is back on.' : ' is 86\'d.'),
                'item_id'   => $itemId,
                'available' => (bool) $available,
            ]);
        }

        /* ============================================================= */
        case 'store': {
            $fields = [];
            $params = [];

            if (array_key_exists('accepting', $in)) {
                $fields[] = 'accepting = ?';
                $params[] = !empty($in['accepting']) ? 1 : 0;
            }
            if (array_key_exists('prep_minutes', $in)) {
                // Clamp: a 0-minute or 3-hour quote is always a mis-tap.
                $prep = max(5, min(120, (int) $in['prep_minutes']));
                $fields[] = 'prep_minutes = ?';
                $params[] = $prep;
            }
            if (array_key_exists('pause_minutes', $in)) {
                $mins = (int) $in['pause_minutes'];
                if ($mins > 0) {
                    // Clamped to an int before interpolation — MySQL won't
                    // reliably bind the INTERVAL quantity as a parameter.
                    $mins = max(1, min(240, $mins));
                    $fields[] = "pause_until = DATE_ADD(NOW(), INTERVAL {$mins} MINUTE)";
                    $fields[] = 'pause_reason = ?';
                    $params[] = mb_substr((string) ($in['pause_reason'] ?? 'We\'re slammed right now'), 0, 190);
                } else {
                    $fields[] = 'pause_until = NULL';
                    $fields[] = 'pause_reason = NULL';
                }
            }

            if (!$fields) bb_upd_out(['success' => false, 'message' => 'Nothing to update.'], 400);

            // Seed the row first: a plain UPDATE affects 0 rows for a location
            // that was added to the config but never inserted by schema.sql,
            // and would then report success while changing nothing.
            $pdo->prepare('INSERT IGNORE INTO bb_store_state (location_id) VALUES (?)')
                ->execute([$location]);

            $params[] = $location;
            $pdo->prepare('UPDATE bb_store_state SET ' . implode(', ', $fields) . ' WHERE location_id = ?')
                ->execute($params);

            $state = bb_store_state($location);
            bb_upd_out([
                'success' => true,
                'message' => 'Updated.',
                'store'   => [
                    'accepting'    => (int) $state['accepting'] === 1,
                    'prep_minutes' => (int) $state['prep_minutes'],
                    'pause_until'  => $state['pause_until'],
                    'pause_reason' => $state['pause_reason'],
                ],
            ]);
        }

        /* ============================================================= */
        case 'print_done': {
            // WebPRNT push succeeded on the iPad — retire the queued job.
            $token = (string) ($in['job_token'] ?? '');
            if ($token !== '') {
                $st = $pdo->prepare('SELECT * FROM bb_print_jobs WHERE job_token = ? AND location_id = ? LIMIT 1');
                $st->execute([$token, $location]);
                $job = $st->fetch();
                if ($job) {
                    require_once __DIR__ . '/../../print/_queue.php';
                    bb_complete_job($pdo, $job, !empty($in['ok']) ? '200' : 'failed');
                }
            }
            bb_upd_out(['success' => true]);
        }

        default:
            bb_upd_out(['success' => false, 'message' => 'Unknown action.'], 400);
    }

} catch (Throwable $e) {
    error_log('BB KDS update: ' . $e->getMessage());
    bb_upd_out(['success' => false, 'message' => 'That didn\'t go through. Try again.'], 500);
}
