<?php
/**
 * GET /api/menu.php?location=holmdel
 *
 * Serves the menu to the storefront, annotated with what's currently 86'd
 * and whether the store can take an order right now.
 *
 * Prices are included for DISPLAY ONLY. api/order-create.php re-derives every
 * price from data/menu.php and ignores whatever the browser sends back.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    $cfg      = bb_config();
    $location = $_GET['location'] ?? '';

    if (!isset($cfg['locations'][$location])) {
        $location = array_key_first($cfg['locations']);
    }

    $menu = bb_menu();

    // Availability + store state need the DB. If it's down, degrade to a
    // browsable menu that simply can't be ordered from, rather than a blank page.
    $unavailable = [];
    $canOrder    = ['ok' => false, 'message' => 'Online ordering is temporarily unavailable. Please call the shop.', 'prep_minutes' => 15];
    $slots       = [];

    try {
        $unavailable = bb_unavailable_items($location);
        $canOrder    = bb_can_order_now($location);
        $slots       = bb_pickup_slots($location);
    } catch (Throwable $e) {
        error_log('BB API menu: degraded mode — ' . $e->getMessage());
    }

    $unavailableMap = array_flip($unavailable);

    // Strip internal-only keys and stamp availability onto each item.
    $categories = [];
    foreach ($menu['categories'] as $cat) {
        $items = [];
        foreach ($cat['items'] as $it) {
            $items[] = [
                'id'        => $it['id'],
                'name'      => $it['name'],
                'desc'      => $it['desc'] ?? '',
                'price'     => (int) $it['price'],
                'price_fmt' => bb_money($it['price']),
                'popular'   => !empty($it['popular']),
                'groups'    => $it['groups'] ?? [],
                'variant_prices' => $it['variant_prices'] ?? null,
                'available' => !isset($unavailableMap[$it['id']]),
            ];
        }
        $categories[] = [
            'id'    => $cat['id'],
            'name'  => $cat['name'],
            'icon'  => $cat['icon'] ?? '',
            'desc'  => $cat['desc'] ?? '',
            // Sent so the cart's tax preview matches what bb_price_cart()
            // actually charges. Without it the client had to guess, and the
            // checkout total could disagree with the tracking page.
            'tax_exempt' => !empty($cat['tax_exempt']),
            'items' => $items,
        ];
    }

    $locations = [];
    foreach ($cfg['locations'] as $id => $l) {
        $locations[] = [
            'id'      => $id,
            'name'    => $l['name'],
            'address' => $l['address'],
            'phone'   => $l['phone'],
            'enabled' => !empty($l['online_ordering']),
        ];
    }

    bb_ok([
        'location'        => $location,
        'locations'       => $locations,
        'categories'      => $categories,
        'modifier_groups' => $menu['modifier_groups'],
        'ordering' => [
            'open'           => (bool) $canOrder['ok'],
            'message'        => $canOrder['message'],
            'prep_minutes'   => (int) $canOrder['prep_minutes'],
            'allow_scheduled'=> count($slots) > 0,
            'slots'          => $slots,
        ],
        'payment' => [
            'stripe_enabled'   => bb_stripe_ready(),
            'publishable_key'  => bb_stripe_ready() ? bb_config('stripe.publishable_key') : null,
            'allow_in_store'   => (bool) bb_config('allow_pay_in_store', true),
        ],
        'tips' => [
            'enabled' => (bool) bb_config('tips.enabled', true),
            'presets' => bb_config('tips.presets', [0, 10, 15, 20]),
            'default' => (int) bb_config('tips.default', 0),
        ],
        'tax_rate'        => (float) bb_config('tax.rate', 0.0625),
        'tax_honor_exempt'=> (bool) bb_config('tax.honor_exempt', true),
    ]);

} catch (Throwable $e) {
    bb_handle_exception($e, 'Could not load the menu. Please refresh, or call the shop to order.');
}

/** Lazy-load the Stripe helper only when we need to answer this question. */
function bb_stripe_ready() {
    require_once __DIR__ . '/../includes/stripe.php';
    return bb_stripe_enabled();
}
