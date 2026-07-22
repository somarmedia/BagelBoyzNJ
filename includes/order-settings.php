<?php
/**
 * Bagel Boyz NJ — Online Ordering: BASE SETTINGS
 * ==============================================
 * THIS FILE IS COMMITTED AND DEPLOYS WITH GIT. Nothing here should ever be a
 * real credential.
 *
 * Anything genuinely secret — database password, Stripe secret key — goes in
 * `includes/order-config.php`, which is gitignored and uploaded by hand. When
 * that file exists its values are merged OVER these, key by key, so you only
 * need to list the handful of things you're actually overriding.
 *
 *     order-settings.php   (this file, public)   ← base
 *     order-config.php     (gitignored, private) ← overrides, optional
 *
 * The split exists so the system runs the moment you `git push`, with no
 * manual upload, while still having a home for secrets when you add Stripe.
 */

return [

    /* =================================================================
       DATABASE
       -----------------------------------------------------------------
       Ships as SQLite so ordering works immediately with zero setup — no
       database to create, no credentials to enter. The file is created
       automatically on first use and lives in db/ (web access denied by
       db/.htaccess) and is gitignored.

       SQLite is genuinely fine for a two-store bagel shop: it comfortably
       handles this volume. Move to MySQL when you want the data in phpMyAdmin
       or you outgrow a single file — override the whole `db` block in
       includes/order-config.php:

           'db' => [
               'driver' => 'mysql',
               'host' => 'localhost',
               'name' => 'uXXXXXXXX_bagelboyz',
               'user' => 'uXXXXXXXX_bbadmin',
               'pass' => '...',
           ],

       then import db/schema.sql via phpMyAdmin.
       ================================================================= */
    'db' => [
        'driver'  => 'sqlite',
        'sqlite_path' => __DIR__ . '/../db/bagelboyz.sqlite',

        // Used only when driver is 'mysql'.
        'host'    => 'localhost',
        'name'    => '',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    /* =================================================================
       STORE
       ================================================================= */
    'timezone' => 'America/New_York',

    'locations' => [
        'holmdel' => [
            'name'            => 'Holmdel Rd',
            'address'         => '694 Holmdel Rd, Hazlet, NJ 07730',
            'phone'           => '(732) 646-4455',
            'phone_e164'      => '+17326464455',
            'online_ordering' => true,
        ],
        'airport' => [
            'name'            => 'Airport Plaza',
            'address'         => '1352 NJ-36, Hazlet, NJ 07730',
            'phone'           => '(732) 335-1300',
            'phone_e164'      => '+17323351300',
            'online_ordering' => true,
        ],
    ],

    // 0 = Sunday … 6 = Saturday. Both stores: 6 AM – 3 PM, 7 days.
    'hours' => [
        0 => ['06:00', '15:00'], 1 => ['06:00', '15:00'], 2 => ['06:00', '15:00'],
        3 => ['06:00', '15:00'], 4 => ['06:00', '15:00'], 5 => ['06:00', '15:00'],
        6 => ['06:00', '15:00'],
    ],

    // Closed all day. 'YYYY-MM-DD' => 'Reason shown to customer'.
    'closed_dates' => [
        // '2026-12-25' => 'Christmas Day',
    ],

    /* =================================================================
       ORDERING
       ================================================================= */
    'ordering' => [
        /* -------------------------------------------------------------
           THE BIG SWITCH.
           false = customers keep seeing the old DoorDash/Grubhub page;
                   only someone with the preview key sees online ordering.
           true  = online ordering goes live for everyone.

           Preview (once per device, remembered 30 days):
               https://bagelboyznj.com/order.php?preview=boil-boyz-bd6c9d
           Leave preview:
               https://bagelboyznj.com/order.php?preview=off
           ------------------------------------------------------------- */
        'public'      => false,

        // Low-value on purpose: all it unlocks is an early look at your own
        // order page. Change it here any time.
        'preview_key' => 'boil-boyz-bd6c9d',

        'default_prep_minutes' => 15,   // ASAP quote; the iPad can raise this live
        'min_lead_minutes'     => 10,   // earliest a scheduled pickup may be
        'max_days_ahead'       => 2,    // how far out scheduling is allowed
        'slot_interval_minutes'=> 15,   // scheduled pickup granularity
        'last_order_before_close_minutes' => 20,
        'max_items_per_order'  => 60,
        'max_qty_per_line'     => 25,
        'min_order_cents'      => 0,
        'rate_limit_per_hour'  => 12,   // orders per IP per hour
    ],

    /* =================================================================
       TAX
       -----------------------------------------------------------------
       >>> Set to 6.25% as requested. THE NJ STATE SALES TAX RATE IS 6.625%.
       >>> If 6.25% was a typo, fix it here — at 6.25% you under-collect and
       >>> the shop covers the difference (~$0.38 per $100 of taxable sales).
       >>> The only NJ rate below 6.625% is the 3.3125% Urban Enterprise Zone
       >>> rate, and Hazlet is not a UEZ.

       Prepared food is taxable; unheated bakery sold as-is is generally
       exempt, which is why the `bagels` category is marked tax_exempt in
       data/menu.php.

       >>> CONFIRM BOTH WITH YOUR ACCOUNTANT BEFORE GOING LIVE. <<<
       ================================================================= */
    'tax' => [
        'rate'         => 0.0625,   // 6.25% — NJ state rate is 0.06625
        'honor_exempt' => true,     // false = tax everything
    ],

    'tips' => [
        'enabled' => true,
        'presets' => [0, 10, 15, 20],
        'default' => 0,
    ],

    /* =================================================================
       STRIPE — leave blank here. Put real keys in order-config.php.
       While blank, card payment is hidden and everyone pays at pickup.
       ================================================================= */
    'stripe' => [
        'enabled'              => false,
        'publishable_key'      => '',
        'secret_key'           => '',
        'webhook_secret'       => '',
        'currency'             => 'usd',
        'statement_descriptor' => 'BAGEL BOYZ NJ',
    ],

    'allow_pay_in_store' => true,

    /* =================================================================
       PRINTING
       ================================================================= */
    'printing' => [
        'enabled'         => true,
        'bridge_enabled'  => true,   // recommended: backend/src/print-bridge.js
        'webprnt_enabled' => true,   // iPad push via Star WebPRNT Browser
        'star_enabled'    => true,   // CloudPRNT — TSP100IV and up only
        'epson_enabled'   => true,   // Epson Server Direct Print

        // Authorizes fetching kitchen tickets. Fine while in preview; set a
        // private one in order-config.php before you go live.
        'poll_key'        => 'LDI2jXvkQmDGBrvcTSnuZJsEORh9SqcQFIVHh2yAW_Q',

        'copies'          => 1,
        'chars_per_line'  => 42,     // 42 for 80mm paper, 32 for 58mm
        'reprint_window_hours' => 12,
    ],

    /* =================================================================
       KITCHEN DISPLAY
       ================================================================= */
    'kds' => [
        /* >>> This PIN is in the git repo, so treat it as public. It is fine
           >>> for preview, but the KDS shows customer names and phone
           >>> numbers — set a private PIN in includes/order-config.php
           >>> before real customers start ordering:
           >>>     'kds' => ['pin' => '4821'],
           >>> The self-check page warns until you do. */
        'pin'                  => '0221',

        'session_hours'        => 18,   // survives a full shift
        'poll_seconds'         => 5,
        'alert_repeat_seconds' => 6,
        'alert_voice_text'     => 'You have a new order',
        'auto_accept'          => false,
    ],

    /* =================================================================
       EMAIL — credentials come from php/smtp-config.php
       ================================================================= */
    'email' => [
        'enabled'      => true,
        'send_receipt' => true,
        'send_ready'   => true,
        'notify_store' => true,
        'store_to'     => 'orders@bagelboyznj.com',
    ],

    'site_url' => 'https://bagelboyznj.com',

    // NEVER true in production — surfaces real PHP errors in API responses.
    'debug' => false,
];
