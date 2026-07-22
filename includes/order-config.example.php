<?php
/**
 * Bagel Boyz NJ — Online Ordering Configuration
 * =============================================
 * COPY THIS FILE TO `order-config.php` AND FILL IT IN.
 *
 *   cp includes/order-config.example.php includes/order-config.php
 *
 * `order-config.php` is gitignored — it holds live secrets and must be
 * uploaded to Hostinger by hand via File Manager. It will NOT auto-deploy.
 */

return [

    /* =================================================================
       DATABASE  (hPanel → Databases → MySQL Databases)
       ================================================================= */
    'db' => [
        'host'     => 'localhost',
        'name'     => 'uXXXXXXXX_bagelboyz',
        'user'     => 'uXXXXXXXX_bbadmin',
        'pass'     => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
    ],

    /* =================================================================
       STORE
       ================================================================= */
    'timezone' => 'America/New_York',

    'locations' => [
        'holmdel' => [
            'name'     => 'Holmdel Rd',
            'address'  => '694 Holmdel Rd, Hazlet, NJ 07730',
            'phone'    => '(732) 646-4455',
            'phone_e164' => '+17326464455',
            'online_ordering' => true,
        ],
        'airport' => [
            'name'     => 'Airport Plaza',
            'address'  => '1352 NJ-36, Hazlet, NJ 07730',
            'phone'    => '(732) 335-1300',
            'phone_e164' => '+17323351300',
            'online_ordering' => true,
        ],
    ],

    // 0 = Sunday … 6 = Saturday. Both stores: 6 AM – 3 PM, 7 days.
    'hours' => [
        0 => ['06:00', '15:00'], 1 => ['06:00', '15:00'], 2 => ['06:00', '15:00'],
        3 => ['06:00', '15:00'], 4 => ['06:00', '15:00'], 5 => ['06:00', '15:00'],
        6 => ['06:00', '15:00'],
    ],

    // Closed all day. Format: 'YYYY-MM-DD' => 'Reason shown to customer'.
    'closed_dates' => [
        // '2026-12-25' => 'Christmas Day',
    ],

    'ordering' => [
        /* -------------------------------------------------------------
           THE BIG SWITCH.
           false = customers keep seeing the old DoorDash/Grubhub page.
                   Only someone holding the preview key below sees the new
                   ordering system. Use this to test on the live site.
           true  = online ordering goes live for everyone.

           Preview access (once per device, then it's remembered 30 days):
               https://bagelboyznj.com/order.php?preview=YOUR_PREVIEW_KEY
           Leave preview:
               https://bagelboyznj.com/order.php?preview=off
           ------------------------------------------------------------- */
        'public'      => false,
        'preview_key' => 'CHANGE_ME_TO_A_PREVIEW_PASSWORD',

        'default_prep_minutes' => 15,   // ASAP quote; iPad can raise this live
        'min_lead_minutes'     => 10,   // earliest a scheduled pickup may be
        'max_days_ahead'       => 2,    // how far out scheduling is allowed
        'slot_interval_minutes'=> 15,   // scheduled pickup granularity
        'last_order_before_close_minutes' => 20, // stop taking orders 20 min before close
        'max_items_per_order'  => 60,
        'max_qty_per_line'     => 25,
        'min_order_cents'      => 0,
        'rate_limit_per_hour'  => 12,   // orders per IP per hour
    ],

    /* =================================================================
       TAX
       -----------------------------------------------------------------
       >>> HEADS UP: set to 6.25% as requested, but the NJ STATE SALES TAX
       >>> RATE IS 6.625%. If 6.25% was a typo, change it below — at 6.25%
       >>> you under-collect and the shop eats the difference at filing
       >>> time (roughly $0.38 per $100 of taxable sales).
       >>> The only NJ rate lower than 6.625% is the 3.3125% Urban
       >>> Enterprise Zone rate, and Hazlet is not a UEZ.

       Prepared food is taxable; unheated bakery items sold as-is are
       generally exempt, which is why the `bagels` category carries
       'tax_exempt' => true in data/menu.php.

       >>> CONFIRM BOTH THE RATE AND THE EXEMPTION WITH YOUR ACCOUNTANT. <<<
       ================================================================= */
    'tax' => [
        'rate'          => 0.0625,   // 6.25% — see note above, NJ state rate is 0.06625
        'honor_exempt'  => true,     // false = tax everything, simplest and safest-for-you
    ],

    /* =================================================================
       TIPPING
       ================================================================= */
    'tips' => [
        'enabled'  => true,
        'presets'  => [0, 10, 15, 20],   // percent
        'default'  => 0,
    ],

    /* =================================================================
       STRIPE  —  dashboard.stripe.com/apikeys
       Leave blank to run in "pay at pickup only" mode. The checkout page
       hides the card option automatically until these are filled in.
       ================================================================= */
    'stripe' => [
        'enabled'         => false,
        'publishable_key' => '',   // pk_live_… (or pk_test_… while testing)
        'secret_key'      => '',   // sk_live_…  NEVER expose this to the browser
        'webhook_secret'  => '',   // whsec_…    from the webhook endpoint you create
        'currency'        => 'usd',
        'statement_descriptor' => 'BAGEL BOYZ NJ', // max 22 chars
    ],

    // Allow customers to skip online payment and pay at the counter.
    // Keep TRUE until Stripe is live, or nobody can order at all.
    'allow_pay_in_store' => true,

    /* =================================================================
       PRINTING
       -----------------------------------------------------------------
       Both drivers read the same bb_print_jobs queue. Turn on whichever
       printer you end up buying — or leave both on, they don't conflict.

       Star  : configure the printer's CloudPRNT URL to
               https://bagelboyznj.com/print/cloudprnt.php?loc=holmdel&key=YOUR_KEY
       Epson : configure Server Direct Print URL to
               https://bagelboyznj.com/print/epos.php?loc=holmdel&key=YOUR_KEY
       ================================================================= */
    'printing' => [
        'enabled'     => true,

        // RECOMMENDED for the Star TSP143IIIW: a small always-on machine on
        // the shop wifi pulls tickets and writes them to the printer on TCP
        // 9100. No browser, so no HTTPS→HTTP mixed-content problem, and
        // tickets print whether or not anyone is looking at the iPad.
        //   → backend/src/print-bridge.js
        'bridge_enabled' => true,

        // WebPRNT: the iPad pushes to the printer directly. Works, but only
        // from inside Star's WebPRNT Browser app (every iOS browser, Chrome
        // included, blocks HTTPS→HTTP).
        'webprnt_enabled' => true,

        // CloudPRNT: printer pulls jobs on its own. TSP100IV and up only —
        // the TSP100III has no CloudPRNT firmware.
        'star_enabled'  => true,

        // Epson Server Direct Print, if a TM-m30 ever replaces the Star.
        'epson_enabled' => true,

        // Shared secret for the bridge / printer URLs. Make this long and
        // random — it authorizes fetching kitchen tickets.
        'poll_key'    => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
        'copies'      => 1,          // tickets per order
        'chars_per_line' => 42,      // 42 for 80mm paper, 32 for 58mm
        'reprint_window_hours' => 12,
    ],

    /* =================================================================
       KITCHEN DISPLAY (iPad)
       ================================================================= */
    'kds' => [
        // Staff type this to unlock the iPad. Change it from the default.
        'pin'                 => '2021',
        'session_hours'       => 18,      // survives a full shift without re-login
        'poll_seconds'        => 5,
        'alert_repeat_seconds'=> 6,       // how often the voice alert repeats
        'alert_voice_text'    => 'You have a new order',
        'auto_accept'         => false,   // true = orders skip straight to in_progress
    ],

    /* =================================================================
       EMAIL — reuses your existing php/smtp-config.php credentials.
       ================================================================= */
    'email' => [
        'enabled'        => true,
        'send_receipt'   => true,   // to the customer on order placed
        'send_ready'     => true,   // to the customer when marked ready
        'notify_store'   => true,   // to the shop on every new order
        'store_to'       => 'orders@bagelboyznj.com',
    ],

    /* =================================================================
       SITE
       ================================================================= */
    'site_url' => 'https://bagelboyznj.com',

    // Set true to see real PHP errors in API responses. NEVER true in production.
    'debug' => false,
];
