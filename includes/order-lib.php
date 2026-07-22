<?php
/**
 * Bagel Boyz NJ — ordering domain logic.
 *
 * The important thing in this file is bb_price_cart(). The browser sends only
 * item ids, option ids and quantities — never prices. Every dollar figure an
 * order carries is recomputed here from data/menu.php. A tampered cart can
 * change WHAT is ordered but never WHAT IT COSTS.
 */

require_once __DIR__ . '/db.php';

/* =====================================================================
   MENU INDEXING
   ===================================================================== */

/** Flat map: item_id => item (+ _category_id, _tax_exempt). */
function bb_item_index() {
    static $idx = null;
    if ($idx !== null) return $idx;

    $idx = [];
    foreach (bb_menu()['categories'] as $cat) {
        foreach ($cat['items'] as $item) {
            $item['_category_id']   = $cat['id'];
            $item['_category_name'] = $cat['name'];
            $item['_tax_exempt']    = !empty($cat['tax_exempt']);
            $idx[$item['id']] = $item;
        }
    }
    return $idx;
}

function bb_group_index() {
    return bb_menu()['modifier_groups'];
}

/**
 * Reorder an item's group list so every show_if dependency is evaluated
 * BEFORE the group that depends on it.
 *
 * bb_price_cart() resolves show_if against groups it has already seen. If a
 * dependent group came first in the menu array, its condition would read as
 * unmet, the group would be skipped, and any paid option in it (e.g. the
 * +$2.00 gluten-free bagel) would silently go uncharged. Rather than relying
 * on whoever edits data/menu.php getting the order right, sort it here.
 */
function bb_sort_groups_by_dependency(array $itemGroups, array $groups) {
    $ordered = [];
    $placed  = [];
    $pending = $itemGroups;

    // Repeatedly take any group whose dependency is already placed (or isn't
    // part of this item at all). Bounded by count() passes.
    for ($pass = 0; $pass < count($itemGroups) + 1 && $pending; $pass++) {
        $deferred = [];

        foreach ($pending as $gid) {
            $dep = $groups[$gid]['show_if']['group'] ?? null;

            $needsWait = $dep !== null
                && !isset($placed[$dep])
                && in_array($dep, $itemGroups, true);

            if ($needsWait) {
                $deferred[] = $gid;
            } else {
                $ordered[] = $gid;
                $placed[$gid] = true;
            }
        }

        // No progress means a dependency cycle in the menu data.
        if (count($deferred) === count($pending)) {
            error_log('BB MENU: show_if cycle among groups: ' . implode(', ', $deferred));
            foreach ($deferred as $gid) $ordered[] = $gid;
            return $ordered;
        }
        $pending = $deferred;
    }

    return $ordered;
}

function bb_money($cents) {
    return '$' . number_format(((int) $cents) / 100, 2);
}

/* =====================================================================
   PRICING ENGINE
   ---------------------------------------------------------------------
   Input  : ['items' => [ ['item_id'=>..,'qty'=>..,'notes'=>..,'options'=>[gid=>[oid,..]]], .. ]]
   Output : ['ok'=>true, 'lines'=>[..], 'subtotal_cents'=>..,'tax_cents'=>..,..]
            or ['ok'=>false, 'errors'=>[..]]
   ===================================================================== */
function bb_price_cart(array $cart, $tipCents = 0) {
    $cfg    = bb_config();
    $items  = bb_item_index();
    $groups = bb_group_index();
    $errors = [];
    $lines  = [];

    $rawLines = $cart['items'] ?? [];
    if (!is_array($rawLines) || count($rawLines) === 0) {
        return ['ok' => false, 'errors' => ['Your cart is empty.']];
    }

    $maxItems = (int) bb_config('ordering.max_items_per_order', 60);
    $maxQty   = (int) bb_config('ordering.max_qty_per_line', 25);

    $totalUnits = 0;
    $sort = 0;

    foreach ($rawLines as $raw) {
        $sort++;
        $itemId = isset($raw['item_id']) ? (string) $raw['item_id'] : '';

        if (!isset($items[$itemId])) {
            $errors[] = "We no longer offer one of the items in your cart. Please remove it and try again.";
            continue;
        }
        $item = $items[$itemId];

        $qty = isset($raw['qty']) ? (int) $raw['qty'] : 1;
        if ($qty < 1)       $qty = 1;
        if ($qty > $maxQty) $qty = $maxQty;
        $totalUnits += $qty;

        $selected   = (isset($raw['options']) && is_array($raw['options'])) ? $raw['options'] : [];
        // Dependencies first, so show_if never reads a group we haven't
        // resolved yet (which would drop it and undercharge).
        $itemGroups = bb_sort_groups_by_dependency($item['groups'] ?? [], $groups);

        $baseCents   = (int) $item['price'];
        $modCents    = 0;
        $chosen      = [];        // flat list written to bb_order_item_options
        $activePicks = [];        // group_id => [option_id,...] for show_if resolution
        $optSort     = 0;

        foreach ($itemGroups as $gid) {
            if (!isset($groups[$gid])) continue;         // stale reference in menu data
            $group = $groups[$gid];

            // --- conditional groups (e.g. bagel_type only when bread = bagel) ---
            if (isset($group['show_if'])) {
                $depGroup  = $group['show_if']['group'];
                $depOption = $group['show_if']['option'];
                $depPicks  = $activePicks[$depGroup] ?? [];
                if (!in_array($depOption, $depPicks, true)) {
                    continue;   // group not applicable; ignore anything sent for it
                }
            }

            $picks = isset($selected[$gid]) && is_array($selected[$gid])
                ? array_values(array_unique(array_map('strval', $selected[$gid])))
                : [];

            // Keep only ids that genuinely exist in this group.
            $optionsById = [];
            foreach ($group['options'] as $o) $optionsById[$o['id']] = $o;
            $picks = array_values(array_filter($picks, function ($p) use ($optionsById) {
                return isset($optionsById[$p]);
            }));

            $min = (int) ($group['min'] ?? 0);
            $max = (int) ($group['max'] ?? 0);   // 0 = unlimited

            if ($max > 0 && count($picks) > $max) {
                $picks = array_slice($picks, 0, $max);
            }

            if ($min > 0 && count($picks) < $min) {
                $errors[] = sprintf('"%s" — please choose %s.', $item['name'], $group['name']);
                continue;
            }

            $activePicks[$gid] = $picks;
            $isVariant = (($group['mode'] ?? 'add') === 'variant');

            foreach ($picks as $oid) {
                $opt = $optionsById[$oid];

                if ($isVariant) {
                    // A variant REPLACES the base price. Per-item override wins
                    // (deli sandwich vs per-lb), else the group's own price.
                    if (isset($item['variant_prices'][$oid])) {
                        $baseCents = (int) $item['variant_prices'][$oid];
                    } elseif ((int) $opt['price'] > 0) {
                        $baseCents = (int) $opt['price'];
                    } else {
                        // Neither source has a price for this variant. Silently
                        // keeping the base price is how a "per pound" order ends
                        // up charged at the sandwich price, so refuse instead.
                        error_log(sprintf(
                            'BB MENU: item "%s" has no price for variant "%s" in group "%s" — add it to variant_prices.',
                            $item['id'], $oid, $gid
                        ));
                        $errors[] = 'We couldn\'t price one of your items. Please call the shop and we\'ll sort it out.';
                        continue;
                    }
                    $priceShown = 0;
                } else {
                    $priceShown = (int) $opt['price'];
                    $modCents  += $priceShown;
                }

                $chosen[] = [
                    'group_id'    => $gid,
                    'group_name'  => $group['name'],
                    'option_id'   => $oid,
                    'option_name' => $opt['name'],
                    'price_cents' => $priceShown,
                    'sort_order'  => $optSort++,
                ];
            }
        }

        $unitCents = $baseCents + $modCents;
        if ($unitCents < 0) $unitCents = 0;

        // mb_* here on purpose: a byte-wise substr can slice a multi-byte
        // character in half, and the resulting invalid UTF-8 makes json_encode
        // return false further down the line — an empty API response.
        $notes = isset($raw['notes']) ? trim((string) $raw['notes']) : '';
        if (mb_strlen($notes) > 200) $notes = mb_substr($notes, 0, 200);

        $lines[] = [
            'menu_item_id' => $item['id'],
            'category_id'  => $item['_category_id'],
            'name'         => $item['name'],
            'qty'          => $qty,
            'unit_cents'   => $unitCents,
            'line_cents'   => $unitCents * $qty,
            'notes'        => $notes !== '' ? $notes : null,
            'sort_order'   => $sort,
            'tax_exempt'   => $item['_tax_exempt'],
            'options'      => $chosen,
        ];
    }

    if ($totalUnits > $maxItems) {
        $errors[] = "That's a big order — over {$maxItems} items. Please call the shop so we can take care of you properly.";
    }
    if ($errors) return ['ok' => false, 'errors' => array_values(array_unique($errors))];

    /* ---- totals ---- */
    $subtotal      = 0;
    $taxableBase   = 0;
    $honorExempt   = (bool) bb_config('tax.honor_exempt', true);

    foreach ($lines as $l) {
        $subtotal += $l['line_cents'];
        if (!$honorExempt || !$l['tax_exempt']) {
            $taxableBase += $l['line_cents'];
        }
    }

    $rate = (float) bb_config('tax.rate', 0.0625);
    $tax  = (int) round($taxableBase * $rate);

    $tipCents = max(0, (int) $tipCents);
    // Guard against a runaway tip field; 100% of subtotal is already generous.
    if ($tipCents > $subtotal * 2) $tipCents = 0;

    return [
        'ok'             => true,
        'lines'          => $lines,
        'item_count'     => $totalUnits,
        'subtotal_cents' => $subtotal,
        'taxable_cents'  => $taxableBase,
        'tax_cents'      => $tax,
        'tip_cents'      => $tipCents,
        'total_cents'    => $subtotal + $tax + $tipCents,
    ];
}

/* =====================================================================
   HOURS, PICKUP SLOTS & STORE STATE
   ===================================================================== */

function bb_now() {
    return new DateTime('now', new DateTimeZone(bb_config('timezone', 'America/New_York')));
}

/** ['open'=>bool, 'reason'=>string, 'opens_at'=>DateTime|null, 'closes_at'=>DateTime|null] */
function bb_store_hours_for(DateTime $day) {
    $dateKey = $day->format('Y-m-d');
    $closed  = bb_config('closed_dates', []);
    if (isset($closed[$dateKey])) {
        return ['open' => false, 'reason' => $closed[$dateKey], 'opens_at' => null, 'closes_at' => null];
    }

    $hours = bb_config('hours', []);
    $dow   = (int) $day->format('w');
    if (!isset($hours[$dow])) {
        return ['open' => false, 'reason' => 'Closed today', 'opens_at' => null, 'closes_at' => null];
    }

    $tz    = new DateTimeZone(bb_config('timezone', 'America/New_York'));
    $open  = DateTime::createFromFormat('Y-m-d H:i', $dateKey . ' ' . $hours[$dow][0], $tz);
    $close = DateTime::createFromFormat('Y-m-d H:i', $dateKey . ' ' . $hours[$dow][1], $tz);

    return ['open' => true, 'reason' => '', 'opens_at' => $open, 'closes_at' => $close];
}

/** Per-location runtime switches, with sane defaults if the row is missing. */
function bb_store_state($locationId) {
    try {
        $st = bb_db()->prepare('SELECT * FROM bb_store_state WHERE location_id = ?');
        $st->execute([$locationId]);
        $row = $st->fetch();
    } catch (Exception $e) {
        $row = null;
    }
    if (!$row) {
        return [
            'location_id'  => $locationId,
            'accepting'    => 1,
            'prep_minutes' => (int) bb_config('ordering.default_prep_minutes', 15),
            'pause_until'  => null,
            'pause_reason' => null,
        ];
    }
    return $row;
}

/**
 * Can we take an order for this location right now?
 * ['ok'=>bool,'message'=>string,'prep_minutes'=>int,'closes_at'=>DateTime|null]
 */
function bb_can_order_now($locationId) {
    $cfg = bb_config();

    if (empty($cfg['locations'][$locationId])) {
        return ['ok' => false, 'message' => 'Unknown location.', 'prep_minutes' => 15, 'closes_at' => null];
    }
    if (empty($cfg['locations'][$locationId]['online_ordering'])) {
        return ['ok' => false, 'message' => 'Online ordering isn\'t available at this location yet.', 'prep_minutes' => 15, 'closes_at' => null];
    }

    $state = bb_store_state($locationId);
    $prep  = (int) ($state['prep_minutes'] ?: 15);

    if (empty($state['accepting'])) {
        return ['ok' => false, 'message' => 'We\'ve paused online orders at this location. Please give us a call.', 'prep_minutes' => $prep, 'closes_at' => null];
    }

    $now = bb_now();

    if (!empty($state['pause_until'])) {
        $until = new DateTime($state['pause_until'], new DateTimeZone(bb_config('timezone', 'America/New_York')));
        if ($until > $now) {
            $reason = $state['pause_reason'] ?: 'We\'re slammed right now';
            return [
                'ok' => false,
                'message' => $reason . ' — online orders reopen at ' . $until->format('g:i A') . '.',
                'prep_minutes' => $prep, 'closes_at' => null,
            ];
        }
    }

    $today = bb_store_hours_for($now);
    if (!$today['open']) {
        return ['ok' => false, 'message' => $today['reason'] ?: 'We\'re closed today.', 'prep_minutes' => $prep, 'closes_at' => null];
    }

    if ($now < $today['opens_at']) {
        return [
            'ok' => false,
            'message' => 'We open at ' . $today['opens_at']->format('g:i A') . '. You can still schedule a pickup for later today.',
            'prep_minutes' => $prep, 'closes_at' => $today['closes_at'],
            'before_open' => true,
        ];
    }

    // Stop accepting shortly before close so the kitchen isn't cleaning up
    // with a fresh order on the rail.
    $cutoff = (clone $today['closes_at'])->modify('-' . (int) bb_config('ordering.last_order_before_close_minutes', 20) . ' minutes');
    if ($now >= $cutoff) {
        return [
            'ok' => false,
            'message' => 'We stop taking online orders at ' . $cutoff->format('g:i A') . '. See you tomorrow at ' . $today['opens_at']->format('g:i A') . '!',
            'prep_minutes' => $prep, 'closes_at' => $today['closes_at'],
        ];
    }

    return ['ok' => true, 'message' => '', 'prep_minutes' => $prep, 'closes_at' => $today['closes_at']];
}

/**
 * Bookable pickup slots, grouped by day, starting from now + lead time.
 * Returns [ ['date'=>'2026-07-21','label'=>'Today','slots'=>[['value'=>'2026-07-21 07:30','label'=>'7:30 AM'],..]], .. ]
 */
function bb_pickup_slots($locationId) {
    $tz       = new DateTimeZone(bb_config('timezone', 'America/New_York'));
    $now      = bb_now();
    $state    = bb_store_state($locationId);
    $prep     = (int) ($state['prep_minutes'] ?: 15);
    $lead     = max((int) bb_config('ordering.min_lead_minutes', 10), $prep);
    $interval = (int) bb_config('ordering.slot_interval_minutes', 15);
    $daysAhead= (int) bb_config('ordering.max_days_ahead', 2);

    $earliest = (clone $now)->modify("+{$lead} minutes");
    $out = [];

    for ($d = 0; $d <= $daysAhead; $d++) {
        $day   = (clone $now)->modify("+{$d} days");
        $hours = bb_store_hours_for($day);
        if (!$hours['open']) continue;

        $cursor = clone $hours['opens_at'];
        // Round the first slot up to the interval grid.
        $mins = (int) $cursor->format('i');
        if ($mins % $interval !== 0) {
            $cursor->modify('+' . ($interval - ($mins % $interval)) . ' minutes');
        }
        $lastSlot = (clone $hours['closes_at'])->modify('-' . $interval . ' minutes');

        $slots = [];
        while ($cursor <= $lastSlot) {
            if ($cursor >= $earliest) {
                $slots[] = [
                    'value' => $cursor->format('Y-m-d H:i'),
                    'label' => $cursor->format('g:i A'),
                ];
            }
            $cursor->modify("+{$interval} minutes");
        }
        if (!$slots) continue;

        $label = $d === 0 ? 'Today' : ($d === 1 ? 'Tomorrow' : $day->format('l, M j'));
        $out[] = ['date' => $day->format('Y-m-d'), 'label' => $label, 'slots' => $slots];
    }

    return $out;
}

/** Validate a submitted scheduled time against the real slot list. */
function bb_validate_pickup_time($locationId, $value) {
    foreach (bb_pickup_slots($locationId) as $day) {
        foreach ($day['slots'] as $slot) {
            if ($slot['value'] === $value) return $value;
        }
    }
    return null;
}

/* =====================================================================
   86 BOARD
   ===================================================================== */

/** item_id => false for anything currently unavailable at this location. */
function bb_unavailable_items($locationId) {
    try {
        $st = bb_db()->prepare(
            'SELECT menu_item_id FROM bb_item_availability
              WHERE location_id = ? AND available = 0
                AND (until_date IS NULL OR until_date >= CURDATE())'
        );
        $st->execute([$locationId]);
        return array_column($st->fetchAll(), 'menu_item_id');
    } catch (Exception $e) {
        error_log('BB ORDERING: availability lookup failed — ' . $e->getMessage());
        return [];
    }
}

/* =====================================================================
   IDENTIFIERS
   ===================================================================== */

/**
 * Human-callable order code. Retries on the (rare) unique-key collision
 * rather than trusting a single random draw.
 */
function bb_generate_order_code(PDO $pdo) {
    for ($i = 0; $i < 12; $i++) {
        $code = 'BB-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $st = $pdo->prepare('SELECT 1 FROM bb_orders WHERE order_code = ? LIMIT 1');
        $st->execute([$code]);
        if (!$st->fetchColumn()) return $code;
    }
    // Fall back to something guaranteed unique rather than failing the order.
    return 'BB-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function bb_token($bytes = 16) {
    return bin2hex(random_bytes($bytes));
}

/* =====================================================================
   STATUS
   ===================================================================== */

function bb_status_label($status) {
    $map = [
        'pending_payment' => 'Awaiting payment',
        'new'             => 'Order received',
        'in_progress'     => 'Being made',
        'ready'           => 'Ready for pickup',
        'completed'       => 'Picked up',
        'cancelled'       => 'Cancelled',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function bb_customer_status_blurb($status) {
    $map = [
        'pending_payment' => 'We\'re waiting on your payment to confirm.',
        'new'             => 'We got it! Your order is in the queue.',
        'in_progress'     => 'On the grill right now.',
        'ready'           => 'Ready! Come grab it at the counter.',
        'completed'       => 'Enjoy — thanks for coming to Bagel Boyz.',
        'cancelled'       => 'This order was cancelled. Call us if that\'s a surprise.',
    ];
    return $map[$status] ?? '';
}

function bb_log_event(PDO $pdo, $orderId, $event, $note = null, $actor = 'system') {
    try {
        $st = $pdo->prepare('INSERT INTO bb_order_events (order_id, event, note, actor) VALUES (?,?,?,?)');
        $st->execute([$orderId, $event, $note, $actor]);
    } catch (Exception $e) {
        error_log('BB ORDERING: event log failed — ' . $e->getMessage());
    }
}

/* =====================================================================
   ORDER FETCH (hydrated with items + options)
   ===================================================================== */

function bb_fetch_order(PDO $pdo, $orderId) {
    $st = $pdo->prepare('SELECT * FROM bb_orders WHERE id = ?');
    $st->execute([$orderId]);
    $order = $st->fetch();
    if (!$order) return null;

    $st = $pdo->prepare('SELECT * FROM bb_order_items WHERE order_id = ? ORDER BY sort_order, id');
    $st->execute([$orderId]);
    $items = $st->fetchAll();

    if ($items) {
        $ids = array_column($items, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $pdo->prepare("SELECT * FROM bb_order_item_options WHERE order_item_id IN ($in) ORDER BY sort_order, id");
        $st->execute($ids);
        $byItem = [];
        foreach ($st->fetchAll() as $o) $byItem[$o['order_item_id']][] = $o;
        foreach ($items as &$it) $it['options'] = $byItem[$it['id']] ?? [];
        unset($it);
    }

    $order['items'] = $items;
    return $order;
}

/**
 * Hydrate a set of already-SELECTed order rows with their items and options
 * in two queries total, instead of three per order.
 *
 * The KDS polls every 5 seconds; calling bb_fetch_order() in a loop over a
 * 40-order board is ~120 round trips per tick, per iPad, on shared hosting.
 * This keeps it at two.
 *
 * @param array $rows Rows from `SELECT * FROM bb_orders ...`
 * @return array Same rows, each with an 'items' key (items carry 'options').
 */
function bb_hydrate_orders(PDO $pdo, array $rows) {
    if (!$rows) return [];

    $orderIds = array_map('intval', array_column($rows, 'id'));
    $in       = implode(',', array_fill(0, count($orderIds), '?'));

    $st = $pdo->prepare("SELECT * FROM bb_order_items WHERE order_id IN ($in) ORDER BY sort_order, id");
    $st->execute($orderIds);
    $items = $st->fetchAll();

    $optsByItem = [];
    if ($items) {
        $itemIds = array_column($items, 'id');
        $in2     = implode(',', array_fill(0, count($itemIds), '?'));
        $st = $pdo->prepare("SELECT * FROM bb_order_item_options WHERE order_item_id IN ($in2) ORDER BY sort_order, id");
        $st->execute($itemIds);
        foreach ($st->fetchAll() as $o) {
            $optsByItem[$o['order_item_id']][] = $o;
        }
    }

    $itemsByOrder = [];
    foreach ($items as $it) {
        $it['options'] = $optsByItem[$it['id']] ?? [];
        $itemsByOrder[$it['order_id']][] = $it;
    }

    foreach ($rows as &$row) {
        $row['items'] = $itemsByOrder[$row['id']] ?? [];
    }
    unset($row);

    return $rows;
}

/** Shape an order for JSON consumers (KDS board, tracking page). */
function bb_order_public(array $order, $includeCustomer = true) {
    $out = [
        'order_code'   => $order['order_code'],
        'status'       => $order['status'],
        'status_label' => bb_status_label($order['status']),
        'blurb'        => bb_customer_status_blurb($order['status']),
        'location_id'  => $order['location_id'],
        'pickup_type'  => $order['pickup_type'],
        'pickup_at'    => $order['pickup_at'],
        'created_at'   => $order['created_at'],
        'ready_at'     => $order['ready_at'],
        'item_count'   => (int) $order['item_count'],
        'subtotal'     => bb_money($order['subtotal_cents']),
        'tax'          => bb_money($order['tax_cents']),
        'tip'          => bb_money($order['tip_cents']),
        'total'        => bb_money($order['total_cents']),
        'total_cents'  => (int) $order['total_cents'],
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'],
        'order_notes'  => $order['order_notes'],
    ];

    if ($includeCustomer) {
        $out['customer_name']  = $order['customer_name'];
        $out['customer_phone'] = $order['customer_phone'];
    }

    $out['items'] = [];
    foreach ($order['items'] ?? [] as $it) {
        $opts = [];
        foreach ($it['options'] ?? [] as $o) {
            $opts[] = [
                'group' => $o['group_name'],
                'name'  => $o['option_name'],
                'price' => (int) $o['price_cents'],
            ];
        }
        $out['items'][] = [
            'name'    => $it['name'],
            'qty'     => (int) $it['qty'],
            'price'   => bb_money($it['line_cents']),
            'notes'   => $it['notes'],
            'options' => $opts,
        ];
    }

    return $out;
}
