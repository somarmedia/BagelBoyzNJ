<?php
/**
 * Bagel Boyz NJ — minimal Stripe REST client.
 *
 * Hostinger shared hosting has no Composer, and the full Stripe PHP SDK is
 * ~4MB of code to call three endpoints. This is a plain cURL wrapper over the
 * two calls the ordering system actually makes, plus webhook verification.
 *
 * If you later move to a host with Composer, swapping this for the official
 * SDK is a drop-in — the call sites only use the four functions below.
 */

require_once __DIR__ . '/db.php';

function bb_stripe_enabled() {
    return (bool) bb_config('stripe.enabled', false)
        && bb_config('stripe.secret_key')
        && bb_config('stripe.publishable_key');
}

/**
 * Signed request to the Stripe API.
 * Returns ['ok'=>bool, 'status'=>int, 'data'=>array, 'error'=>string]
 */
function bb_stripe_request($method, $path, array $params = [], $idempotencyKey = null) {
    $secret = bb_config('stripe.secret_key');
    if (!$secret) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Stripe is not configured.'];
    }

    $url     = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $headers = [
        'Authorization: Bearer ' . $secret,
        'Stripe-Version: 2024-06-20',
        'Content-Type: application/x-www-form-urlencoded',
    ];
    if ($idempotencyKey) {
        // Protects against a double-submit creating two charges.
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init();
    if (strtoupper($method) === 'GET') {
        if ($params) $url .= '?' . http_build_query($params);
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        error_log('BB STRIPE: curl error — ' . $err);
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Could not reach the payment processor.'];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        error_log('BB STRIPE: non-JSON response — ' . substr($body, 0, 400));
        return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'Unexpected payment processor response.'];
    }

    if ($status >= 400 || isset($data['error'])) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $status);
        error_log('BB STRIPE: API error — ' . $msg);
        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
}

/**
 * Create a PaymentIntent for an order.
 * The order id is stamped into metadata so the webhook can find it again.
 */
function bb_stripe_create_intent(array $order, $idempotencyKey = null) {
    $descriptor = substr((string) bb_config('stripe.statement_descriptor', 'BAGEL BOYZ NJ'), 0, 22);

    $params = [
        'amount'   => (int) $order['total_cents'],
        'currency' => bb_config('stripe.currency', 'usd'),
        'automatic_payment_methods' => ['enabled' => 'true'],
        'description' => 'Bagel Boyz NJ order ' . $order['order_code'],
        'metadata' => [
            'order_id'    => (string) $order['id'],
            'order_code'  => $order['order_code'],
            'location_id' => $order['location_id'],
        ],
        'statement_descriptor_suffix' => $descriptor,
    ];

    if (!empty($order['customer_email'])) {
        $params['receipt_email'] = $order['customer_email'];
    }

    return bb_stripe_request('POST', 'payment_intents', $params, $idempotencyKey);
}

function bb_stripe_get_intent($intentId) {
    return bb_stripe_request('GET', 'payment_intents/' . urlencode($intentId));
}

/**
 * Verify a webhook signature (Stripe's scheme v1, HMAC-SHA256).
 * Returns the decoded event array, or null if the signature doesn't check out.
 *
 * Never trust an unverified webhook — it's the thing that marks orders PAID.
 */
function bb_stripe_verify_webhook($payload, $sigHeader, $tolerance = 300) {
    $secret = bb_config('stripe.webhook_secret');
    if (!$secret || !$sigHeader) return null;

    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 't')  $timestamp = $kv[1];
        if ($kv[0] === 'v1') $signatures[] = $kv[1];
    }

    if (!$timestamp || !$signatures) return null;

    // Reject replays of an old, previously-valid payload.
    if (abs(time() - (int) $timestamp) > $tolerance) {
        error_log('BB STRIPE: webhook timestamp outside tolerance.');
        return null;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    $matched = false;
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) { $matched = true; break; }
    }
    if (!$matched) {
        error_log('BB STRIPE: webhook signature mismatch.');
        return null;
    }

    $event = json_decode($payload, true);
    return is_array($event) ? $event : null;
}
