<?php
/**
 * POST /api/order-create.php
 *
 * The one endpoint that turns a browser cart into a real order.
 *
 * Security posture: the request body is treated as hostile. Item ids, option
 * ids and quantities are the ONLY things taken from it — every price, tax
 * figure and total is recomputed server-side by bb_price_cart(). A client that
 * sends {"price": 1} gets charged full freight.
 *
 * Request:
 *   { location, name, phone, email, pickup_type, pickup_at, notes,
 *     tip_cents, payment_method, items:[{item_id, qty, notes, options:{gid:[oid]}}],
 *     g-recaptcha-response }
 *
 * Response:
 *   { success, order_code, track_token, track_url, total, requires_payment,
 *     client_secret?, publishable_key? }
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/order-flow.php';
require_once __DIR__ . '/../includes/order-preview.php';
require_once __DIR__ . '/../includes/stripe.php';

bb_require_post();

try {
    $cfg   = bb_config();
    $in    = bb_input();
    $pdo   = bb_db();

    /* ---------------- preview gate ----------------
       While ordering.public is false the endpoint accepts orders ONLY from a
       visitor holding the preview cookie. Without this the storefront would
       be hidden but the API still open, so anyone who found the URL could
       place a real order into the kitchen. */
    $isPreviewOrder = false;
    if (!bb_ordering_is_public()) {
        if (!bb_preview_active()) {
            bb_fail('Online ordering isn\'t open yet. Please order through DoorDash or Grubhub, or give us a call.', 403);
        }
        $isPreviewOrder = true;
    }

    /* ---------------- rate limit ---------------- */
    $limit = (int) bb_config('ordering.rate_limit_per_hour', 12);
    if (!bb_rate_limit('order:' . bb_client_ip(), $limit)) {
        bb_fail('That\'s a lot of orders from one place. Please give us a call so we can help directly.', 429);
    }

    /* ---------------- honeypot ---------------- */
    // Bots fill every field they find. Real customers never see this one.
    if (!empty($in['website'])) {
        bb_fail('Could not place your order. Please call the shop.', 400);
    }

    /* ---------------- location ---------------- */
    $location = (string) ($in['location'] ?? '');
    if (!isset($cfg['locations'][$location])) {
        bb_fail('Please choose which location you\'re picking up from.');
    }

    /* ---------------- customer ---------------- */
    $name  = trim((string) ($in['name'] ?? ''));
    $phone = trim((string) ($in['phone'] ?? ''));
    $email = trim((string) ($in['email'] ?? ''));

    if (mb_strlen($name) < 2)  bb_fail('Please enter your name so we can call it out.');
    if (mb_strlen($name) > 80) $name = mb_substr($name, 0, 80);

    $phoneDigits = preg_replace('/\D+/', '', $phone);
    if (strlen($phoneDigits) < 10) {
        bb_fail('Please enter a valid phone number — we\'ll only use it if there\'s a question about your order.');
    }
    // Normalize to (732) 646-4455 so the ticket and the KDS read consistently.
    if (strlen($phoneDigits) === 11 && $phoneDigits[0] === '1') $phoneDigits = substr($phoneDigits, 1);
    $phone = strlen($phoneDigits) === 10
        ? sprintf('(%s) %s-%s', substr($phoneDigits, 0, 3), substr($phoneDigits, 3, 3), substr($phoneDigits, 6))
        : $phoneDigits;

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        bb_fail('That email address doesn\'t look right. Fix it, or leave it blank.');
    }
    if ($email === '') $email = null;

    /* ---------------- pickup timing ---------------- */
    $pickupType = ($in['pickup_type'] ?? 'asap') === 'scheduled' ? 'scheduled' : 'asap';
    $canOrder   = bb_can_order_now($location);
    $pickupAt   = null;
    $quoted     = null;

    // Gate on online_ordering BEFORE the branch. bb_validate_pickup_time()
    // only checks opening hours and closed_dates, so without this a location
    // with online ordering switched off would still accept scheduled orders.
    if (empty($cfg['locations'][$location]['online_ordering'])) {
        bb_fail('Online ordering isn\'t available at this location yet. Please give us a call.', 409);
    }

    if ($pickupType === 'scheduled') {
        $requested = (string) ($in['pickup_at'] ?? '');
        $valid     = bb_validate_pickup_time($location, $requested);
        if (!$valid) {
            bb_fail('That pickup time is no longer available. Please pick another time.');
        }
        $pickupAt = $valid . ':00';
    } else {
        // ASAP requires the store to be open and taking orders right now.
        if (!$canOrder['ok']) {
            bb_fail($canOrder['message'] ?: 'We\'re not taking online orders right now.', 409);
        }
        $quoted   = (int) $canOrder['prep_minutes'];
        $pickupAt = bb_now()->modify("+{$quoted} minutes")->format('Y-m-d H:i:s');
    }

    // Even a scheduled order needs the store to be generally accepting.
    $state = bb_store_state($location);
    if (empty($state['accepting'])) {
        bb_fail('We\'ve paused online orders at this location. Please give us a call.', 409);
    }

    /* ---------------- 86'd items ---------------- */
    $rawItems = $in['items'] ?? [];
    if (!is_array($rawItems) || !$rawItems) {
        bb_fail('Your cart is empty.');
    }

    $unavailable = array_flip(bb_unavailable_items($location));
    $itemIndex   = bb_item_index();
    $blocked     = [];
    foreach ($rawItems as $line) {
        $id = (string) ($line['item_id'] ?? '');
        if (isset($unavailable[$id])) {
            $blocked[] = $itemIndex[$id]['name'] ?? $id;
        }
    }
    if ($blocked) {
        bb_fail('Sorry — we just ran out of: ' . implode(', ', array_unique($blocked)) . '. Please remove those and reorder.', 409, [
            'unavailable' => array_values(array_unique($blocked)),
        ]);
    }

    /* ---------------- PRICE IT (server-side, authoritative) ---------------- */
    $tipCents = (int) ($in['tip_cents'] ?? 0);
    if (!bb_config('tips.enabled', true)) $tipCents = 0;

    $priced = bb_price_cart(['items' => $rawItems], $tipCents);
    if (!$priced['ok']) {
        bb_fail(implode(' ', $priced['errors']), 400, ['errors' => $priced['errors']]);
    }

    $minOrder = (int) bb_config('ordering.min_order_cents', 0);
    if ($minOrder > 0 && $priced['subtotal_cents'] < $minOrder) {
        bb_fail('Minimum online order is ' . bb_money($minOrder) . '.');
    }

    /* ---------------- reCAPTCHA ---------------- */
    // Reuses the site-wide Enterprise setup. bb_verify_recaptcha() already
    // no-ops when the keys aren't filled in, so this fails open only on a
    // genuinely unconfigured install — same behaviour as the catering form.
    //
    // bb_smtp_config() rather than a bare `require`: the config was already
    // loaded once this request, and re-executing the file would become fatal
    // the day someone adds a function or const to it.
    $smtpConfig = bb_smtp_config();
    if ($smtpConfig) {
        require_once __DIR__ . '/../php/recaptcha-verify.php';
        $captcha = bb_verify_recaptcha($in['g-recaptcha-response'] ?? '', 'place_order', $smtpConfig);
        if (!$captcha['ok']) {
            bb_fail($captcha['message'], 400);
        }
    } else {
        error_log('BB ORDERING: php/smtp-config.php missing — reCAPTCHA and order emails are both OFF.');
    }

    /* ---------------- payment method ---------------- */
    $wantsStripe   = ($in['payment_method'] ?? '') === 'stripe';
    $stripeReady   = bb_stripe_enabled();
    $allowInStore  = (bool) bb_config('allow_pay_in_store', true);

    if ($wantsStripe && !$stripeReady) {
        bb_fail('Card payment isn\'t available right now. Please choose "Pay at pickup".', 409);
    }
    if (!$wantsStripe && !$allowInStore) {
        bb_fail('Please pay by card to complete your order.', 409);
    }

    $paymentMethod = $wantsStripe ? 'stripe' : 'in_store';
    // Card orders stay invisible to the kitchen until Stripe confirms payment.
    $initialStatus = $wantsStripe ? 'pending_payment' : 'new';

    $notes = trim((string) ($in['notes'] ?? ''));
    if (mb_strlen($notes) > 500) $notes = mb_substr($notes, 0, 500);

    /* ---------------- persist ---------------- */
    $orderCode  = bb_generate_order_code($pdo);
    $trackToken = bb_token();

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO bb_orders
             (order_code, track_token, location_id, status,
              customer_name, customer_phone, customer_email,
              pickup_type, pickup_at, quoted_minutes,
              subtotal_cents, tax_cents, tip_cents, total_cents, item_count,
              payment_method, payment_status, order_notes,
              source, customer_ip, user_agent, confirmed_at)
             VALUES (?,?,?,?, ?,?,?, ?,?,?, ?,?,?,?,?, ?,?,?, ?,?,?,?)'
        );
        $st->execute([
            $orderCode, $trackToken, $location, $initialStatus,
            $name, $phone, $email,
            $pickupType, $pickupAt, $quoted,
            $priced['subtotal_cents'], $priced['tax_cents'], $priced['tip_cents'],
            $priced['total_cents'], $priced['item_count'],
            $paymentMethod, 'unpaid', $notes !== '' ? $notes : null,
            // Stamped so a test order can never be mistaken for a real one —
            // the KDS shows a TEST badge, and it's filterable in reporting.
            $isPreviewOrder ? 'preview' : 'web',
            @inet_pton(bb_client_ip()) ?: null,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $initialStatus === 'new' ? date('Y-m-d H:i:s') : null,
        ]);

        $orderId = (int) $pdo->lastInsertId();

        $itemSt = $pdo->prepare(
            'INSERT INTO bb_order_items
             (order_id, menu_item_id, category_id, name, qty, unit_cents, line_cents, notes, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $optSt = $pdo->prepare(
            'INSERT INTO bb_order_item_options
             (order_item_id, group_id, group_name, option_id, option_name, price_cents, sort_order)
             VALUES (?,?,?,?,?,?,?)'
        );

        foreach ($priced['lines'] as $line) {
            $itemSt->execute([
                $orderId, $line['menu_item_id'], $line['category_id'], $line['name'],
                $line['qty'], $line['unit_cents'], $line['line_cents'],
                $line['notes'], $line['sort_order'],
            ]);
            $itemRowId = (int) $pdo->lastInsertId();

            foreach ($line['options'] as $opt) {
                $optSt->execute([
                    $itemRowId, $opt['group_id'], $opt['group_name'],
                    $opt['option_id'], $opt['option_name'],
                    $opt['price_cents'], $opt['sort_order'],
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    bb_log_event($pdo, $orderId, 'created', 'via web, ' . $paymentMethod, 'customer');

    $order = bb_fetch_order($pdo, $orderId);
    $site  = rtrim(bb_config('site_url', 'https://bagelboyznj.com'), '/');
    $trackUrl = $site . '/track.php?code=' . urlencode($orderCode) . '&t=' . urlencode($trackToken);

    /* ---------------- pay-at-pickup: it's live immediately ---------------- */
    if (!$wantsStripe) {
        bb_finalize_new_order($pdo, $order);

        bb_ok([
            'order_code'       => $orderCode,
            'track_token'      => $trackToken,
            'track_url'        => $trackUrl,
            'total'            => bb_money($order['total_cents']),
            'total_cents'      => (int) $order['total_cents'],
            'requires_payment' => false,
        ]);
    }

    /* ---------------- card: hand back a PaymentIntent ---------------- */
    $intent = bb_stripe_create_intent($order, 'bb_order_' . $orderId);
    if (!$intent['ok']) {
        // Order row stays as pending_payment; the kitchen never sees it.
        bb_log_event($pdo, $orderId, 'payment_intent_failed', $intent['error'], 'system');
        bb_fail('We couldn\'t start the card payment. Please try again, or choose "Pay at pickup".', 502);
    }

    $pdo->prepare('UPDATE bb_orders SET stripe_payment_intent = ? WHERE id = ?')
        ->execute([$intent['data']['id'], $orderId]);

    bb_ok([
        'order_code'       => $orderCode,
        'track_token'      => $trackToken,
        'track_url'        => $trackUrl,
        'total'            => bb_money($order['total_cents']),
        'total_cents'      => (int) $order['total_cents'],
        'requires_payment' => true,
        'client_secret'    => $intent['data']['client_secret'],
        'publishable_key'  => bb_config('stripe.publishable_key'),
    ]);

} catch (Throwable $e) {
    bb_handle_exception($e, 'We couldn\'t place your order. Please try again, or call the shop and we\'ll take it over the phone.');
}
