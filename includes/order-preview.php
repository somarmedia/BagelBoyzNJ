<?php
/**
 * Bagel Boyz NJ — preview gate for online ordering.
 * =================================================
 * Lets the new ordering system run on the live domain while real customers
 * continue to see the old DoorDash/Grubhub page. Nothing about the customer
 * experience changes until `ordering.public` is flipped to true.
 *
 * HOW YOU GET IN
 *   Visit once, on each device you want to test from:
 *       https://bagelboyznj.com/order.php?preview=YOUR_PREVIEW_KEY
 *   That drops a cookie, so from then on you just browse the site normally
 *   and see the new system. Everyone else sees the old page.
 *
 *   To leave preview:  https://bagelboyznj.com/order.php?preview=off
 *
 * WHAT'S DIFFERENT IN PREVIEW
 *   - A banner across the top so you never mistake it for the live site
 *   - Orders are stamped source='preview' and show a TEST badge on the iPad,
 *     so a test order is never confused with a real one
 *   - Everything else is the real thing: real pricing, real database, real
 *     tickets, real emails. That's the point — you're testing the system,
 *     not a mockup.
 *
 * GOING LIVE
 *   Set 'public' => true in includes/order-config.php. That's the whole
 *   switch. Set it back to false to instantly revert to the old page.
 */

require_once __DIR__ . '/db.php';

define('BB_PREVIEW_COOKIE', 'bb_preview');

/** Is online ordering switched on for everybody? */
function bb_ordering_is_public() {
    return (bool) bb_config('ordering.public', false);
}

/**
 * Does this visitor have preview access?
 * Handles the ?preview= handshake and remembers it in a cookie.
 */
function bb_preview_active() {
    static $active = null;
    if ($active !== null) return $active;

    $key = (string) bb_config('ordering.preview_key', '');

    // An empty or placeholder key must never grant access.
    if ($key === '' || $key === 'CHANGE_ME_TO_A_PREVIEW_PASSWORD') {
        return $active = false;
    }

    $requested = isset($_GET['preview']) ? (string) $_GET['preview'] : null;

    if ($requested !== null) {
        if ($requested === 'off') {
            setcookie(BB_PREVIEW_COOKIE, '', [
                'expires' => time() - 3600, 'path' => '/',
                'secure' => bb_preview_is_https(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
            return $active = false;
        }

        if (hash_equals($key, $requested)) {
            // 30 days — long enough to test without re-entering the key.
            setcookie(BB_PREVIEW_COOKIE, $key, [
                'expires' => time() + 30 * 86400, 'path' => '/',
                'secure' => bb_preview_is_https(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
            return $active = true;
        }

        return $active = false;   // wrong key
    }

    $cookie = $_COOKIE[BB_PREVIEW_COOKIE] ?? '';
    return $active = ($cookie !== '' && hash_equals($key, $cookie));
}

function bb_preview_is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

/** Should this visitor see the new ordering system at all? */
function bb_ordering_visible() {
    return bb_ordering_is_public() || bb_preview_active();
}

/** True when the system is being shown, but only because of a preview cookie. */
function bb_in_preview_mode() {
    return !bb_ordering_is_public() && bb_preview_active();
}

/** Banner markup shown at the top of every preview page. */
function bb_preview_banner() {
    if (!bb_in_preview_mode()) return '';

    return '<div class="preview-banner">'
         . '<span class="preview-tag">Preview</span>'
         . '<span class="preview-text">Only you can see this. Customers still see the '
         . 'DoorDash &amp; Grubhub page. Orders placed here are marked <strong>TEST</strong>.</span>'
         . '<a class="preview-exit" href="?preview=off">Exit</a>'
         . '</div>';
}
