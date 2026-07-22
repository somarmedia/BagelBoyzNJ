<?php
/**
 * Bagel Boyz NJ — PRIVATE OVERRIDES (template)
 * ============================================
 * You do NOT need this file to run online ordering. Everything works from
 * `includes/order-settings.php`, which is committed and deploys with git.
 *
 * This file exists for things that must never sit in the repo — the Stripe
 * secret key, a MySQL password, a private KDS PIN. When present, its values
 * are deep-merged OVER order-settings.php, so list only what you're changing.
 *
 *   cp includes/order-config.example.php includes/order-config.php
 *
 * `order-config.php` is gitignored, so it will NOT deploy with git. Upload it
 * by hand: hPanel → File Manager → public_html/includes/
 * (Same as php/smtp-config.php.)
 */

return [

    /* =================================================================
       KDS PIN  — do this before real customers order.
       -----------------------------------------------------------------
       The default PIN lives in order-settings.php, which is in the repo,
       so treat it as public. The kitchen display shows customer names and
       phone numbers, so give it a private PIN here.
       ================================================================= */
    // 'kds' => [
    //     'pin' => '4821',
    // ],

    /* =================================================================
       STRIPE  — dashboard.stripe.com/apikeys
       -----------------------------------------------------------------
       While these are blank, card payment is hidden and everyone pays at
       pickup. Add all three AND create the webhook, or card orders will
       sit unpaid forever and never reach the kitchen.

       Webhook: https://bagelboyznj.com/api/stripe-webhook.php
       Events:  payment_intent.succeeded, payment_intent.payment_failed,
                charge.refunded
       ================================================================= */
    // 'stripe' => [
    //     'enabled'         => true,
    //     'publishable_key' => 'pk_live_...',
    //     'secret_key'      => 'sk_live_...',   // NEVER expose to the browser
    //     'webhook_secret'  => 'whsec_...',
    // ],

    /* =================================================================
       MYSQL  — only if you outgrow the default SQLite database.
       -----------------------------------------------------------------
       Ordering ships on SQLite: no setup, file created automatically at
       db/bagelboyz.sqlite. That's genuinely fine for two stores.

       Switch when you want the data in phpMyAdmin. Create the database in
       hPanel → Databases, import db/schema.sql via phpMyAdmin, then:
       ================================================================= */
    // 'db' => [
    //     'driver' => 'mysql',
    //     'host'   => 'localhost',
    //     'name'   => 'uXXXXXXXX_bagelboyz',
    //     'user'   => 'uXXXXXXXX_bbadmin',
    //     'pass'   => '...',
    // ],

    /* =================================================================
       PRINTER POLL KEY — authorizes fetching kitchen tickets.
       Set a private one before going live, and match it in backend/.env
       (BB_PRINT_KEY) on the machine running the print bridge.
       ================================================================= */
    // 'printing' => [
    //     'poll_key' => 'a-long-random-string',
    // ],

    /* =================================================================
       Anything else from order-settings.php can be overridden the same
       way — tax rate, hours, preview key, going live:
       ================================================================= */
    // 'ordering' => [
    //     'public' => true,
    // ],
];
