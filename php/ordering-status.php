<?php
/**
 * Bagel Boyz NJ — Online Ordering self-check
 *   https://bagelboyznj.com/php/ordering-status.php
 *
 * Answers "why can't I see the ordering system?" without needing to read
 * server logs. Same spirit as php/recaptcha-test.php.
 *
 * SAFE TO LEAVE UP: reports only booleans — "key is set" / "key is missing" —
 * never the values themselves. No password, DB credential, Stripe key or
 * preview key is ever printed.
 */

$checks = [];
$fatal  = false;

function chk(&$out, $label, $state, $detail, $fix = '') {
    // state: ok | warn | fail
    $out[] = ['label' => $label, 'state' => $state, 'detail' => $detail, 'fix' => $fix];
}

/* ---------------------------------------------------------------
   1. Did the code deploy at all?
   --------------------------------------------------------------- */
$root = dirname(__DIR__);

$codeFiles = [
    'order.php'                 => 'Storefront page',
    'includes/order-lib.php'    => 'Pricing engine',
    'data/menu.php'             => 'Menu data',
    'api/order-create.php'      => 'Order API',
    'kds/index.php'             => 'Kitchen display',
];
$missingCode = [];
foreach ($codeFiles as $f => $what) {
    if (!file_exists($root . '/' . $f)) $missingCode[] = $f;
}

if ($missingCode) {
    $fatal = true;
    chk($checks, 'Ordering code deployed', 'fail',
        'Missing: ' . implode(', ', $missingCode),
        'The code has not reached the server. Run <code>git push origin main</code> and wait a few seconds for Hostinger to pull.');
} else {
    chk($checks, 'Ordering code deployed', 'ok', 'All core files present.');
}

/* ---------------------------------------------------------------
   2. The config file (hand-uploaded — never deploys via git)
   --------------------------------------------------------------- */
$configPath = $root . '/includes/order-config.php';
$hasConfig  = file_exists($configPath);

if (!$hasConfig) {
    $fatal = true;
    chk($checks, 'includes/order-config.php', 'fail',
        'Not found on the server.',
        'This file is gitignored on purpose (it holds passwords) so it does <strong>not</strong> deploy with git. Upload it by hand: hPanel &rarr; File Manager &rarr; <code>public_html/includes/</code>.');
} else {
    chk($checks, 'includes/order-config.php', 'ok', 'Found.');
}

/* Everything below needs the config loaded. */
$cfg = null;
if (!$missingCode) {
    require_once $root . '/includes/order-lib.php';
    require_once $root . '/includes/order-preview.php';
    $cfg = bb_config();
}

if ($cfg) {
    /* -----------------------------------------------------------
       3. Preview gate
       ----------------------------------------------------------- */
    $isPublic   = !empty($cfg['ordering']['public']);
    $previewKey = (string) ($cfg['ordering']['preview_key'] ?? '');
    $keyIsReal  = $previewKey !== '' && $previewKey !== 'CHANGE_ME_TO_A_PREVIEW_PASSWORD';

    if ($isPublic) {
        chk($checks, 'Ordering visibility', 'warn',
            'LIVE &mdash; every customer sees online ordering.',
            'If you did not mean to go live yet, set <code>ordering.public =&gt; false</code>.');
    } else {
        chk($checks, 'Ordering visibility', 'ok',
            'Preview only &mdash; customers still see the DoorDash/Grubhub page.');
    }

    if (!$isPublic && !$keyIsReal) {
        $fatal = true;
        chk($checks, 'Preview key', 'fail',
            'Not set (still the placeholder).',
            'Without a real key the preview link can never work &mdash; that is deliberate, so a key sitting in the public repo cannot let anyone in. Set <code>ordering.preview_key</code> in <code>includes/order-config.php</code>.');
    } elseif (!$isPublic) {
        chk($checks, 'Preview key', 'ok', 'Set. Use your preview link to view the storefront.');
    }

    chk($checks, 'Preview cookie on this browser',
        bb_preview_active() ? 'ok' : 'warn',
        bb_preview_active()
            ? 'Active &mdash; you will see the new ordering system.'
            : 'Not set on this device.',
        bb_preview_active() ? '' : 'Visit <code>/order.php?preview=YOUR_KEY</code> once on each device you want to test from.');

    /* -----------------------------------------------------------
       4. Database
       ----------------------------------------------------------- */
    $dbOk = false;
    try {
        $pdo = bb_db();
        $dbOk = true;
        chk($checks, 'Database connection', 'ok', 'Connected.');
    } catch (Throwable $e) {
        chk($checks, 'Database connection', 'fail',
            'Could not connect.',
            'Check the <code>db</code> block in <code>includes/order-config.php</code> against hPanel &rarr; Databases. Browsing the menu still works without this; placing an order does not.');
    }

    if ($dbOk) {
        $needed = ['bb_orders', 'bb_order_items', 'bb_order_item_options', 'bb_order_events',
                   'bb_print_jobs', 'bb_item_availability', 'bb_store_state', 'bb_kds_sessions',
                   'bb_rate_limit'];
        $found = [];
        try {
            foreach ($pdo->query('SHOW TABLES') as $row) $found[] = array_values($row)[0];
        } catch (Throwable $e) { /* fall through */ }

        $missingTables = array_values(array_diff($needed, $found));
        if ($missingTables) {
            chk($checks, 'Database tables', 'fail',
                'Missing ' . count($missingTables) . ': ' . implode(', ', $missingTables),
                'Import <code>db/schema.sql</code> via hPanel &rarr; phpMyAdmin &rarr; Import.');
        } else {
            chk($checks, 'Database tables', 'ok', 'All 9 tables present.');

            try {
                $n = (int) $pdo->query('SELECT COUNT(*) FROM bb_orders')->fetchColumn();
                $t = (int) $pdo->query("SELECT COUNT(*) FROM bb_orders WHERE source = 'preview'")->fetchColumn();
                chk($checks, 'Orders so far', 'ok', "{$n} total ({$t} test).");
            } catch (Throwable $e) { /* non-critical */ }
        }
    }

    /* -----------------------------------------------------------
       5. Menu data
       ----------------------------------------------------------- */
    try {
        $menu  = bb_menu();
        $items = 0;
        foreach ($menu['categories'] as $c) $items += count($c['items']);
        chk($checks, 'Menu data', 'ok',
            count($menu['categories']) . ' categories, ' . $items . ' items, ' .
            count($menu['modifier_groups']) . ' option groups.');
    } catch (Throwable $e) {
        chk($checks, 'Menu data', 'fail', 'Could not load data/menu.php.', 'Re-deploy.');
    }

    /* -----------------------------------------------------------
       6. Optional services
       ----------------------------------------------------------- */
    $stripeOn = !empty($cfg['stripe']['enabled'])
             && !empty($cfg['stripe']['secret_key'])
             && !empty($cfg['stripe']['publishable_key']);
    chk($checks, 'Stripe (card payment)', $stripeOn ? 'ok' : 'warn',
        $stripeOn ? 'Keys are set.' : 'Not configured.',
        $stripeOn ? '' : 'Expected for now &mdash; customers pay at pickup. Card payment stays hidden until you add keys.');

    $smtp = file_exists($root . '/php/smtp-config.php');
    chk($checks, 'Email (order receipts)', $smtp ? 'ok' : 'warn',
        $smtp ? 'smtp-config.php found.' : 'php/smtp-config.php not found.',
        $smtp ? '' : 'Order emails and reCAPTCHA are both off without it. Upload it the same way as the ordering config.');

    $pollKey = (string) ($cfg['printing']['poll_key'] ?? '');
    $printOk = $pollKey !== '' && $pollKey !== 'CHANGE_ME_TO_A_LONG_RANDOM_STRING';
    chk($checks, 'Printing key', $printOk ? 'ok' : 'warn',
        $printOk ? 'Set.' : 'Not set.',
        $printOk ? '' : 'Needed by the print bridge. Tickets still show on the iPad without it.');

    $taxRate = (float) ($cfg['tax']['rate'] ?? 0);
    chk($checks, 'Sales tax rate',
        abs($taxRate - 0.06625) < 0.00001 ? 'ok' : 'warn',
        number_format($taxRate * 100, 3) . '%',
        abs($taxRate - 0.06625) < 0.00001 ? '' : 'NJ state rate is 6.625%. Confirm this is what you want.');
}

// Plain closures rather than arrow functions — the rest of this codebase
// stays PHP 7.0-compatible so it can't trip over an older host setting.
$countState = function ($state) use ($checks) {
    $n = 0;
    foreach ($checks as $c) if ($c['state'] === $state) $n++;
    return $n;
};
$okCount   = $countState('ok');
$failCount = $countState('fail');
$warnCount = $countState('warn');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Ordering Self-Check | Bagel Boyz NJ</title>
<style>
  :root { --gold:#D4901E; --green:#27AE60; --red:#C0392B; --amber:#E8A83C;
          --cream:#FFF8F0; --brown:#3E2214; --gray:#7F8C8D; }
  *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;
         background:var(--cream); color:#1A1A1A; line-height:1.6; padding:24px 16px; }
  .wrap { max-width:760px; margin:0 auto; }
  h1 { font-family:Georgia,serif; font-size:1.6rem; margin-bottom:4px; }
  .sub { color:var(--gray); font-size:.9rem; margin-bottom:24px; }
  .summary { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px; }
  .pill { padding:8px 16px; border-radius:999px; font-weight:700; font-size:.85rem; }
  .pill.ok   { background:rgba(39,174,96,.12);  color:#1B7A45; }
  .pill.warn { background:rgba(232,168,60,.16); color:#8A5D00; }
  .pill.fail { background:rgba(192,57,43,.12);  color:#8B2A20; }
  .banner { padding:16px 18px; border-radius:12px; margin-bottom:24px; font-weight:600; }
  .banner.good { background:rgba(39,174,96,.1); color:#1B7A45; }
  .banner.bad  { background:rgba(192,57,43,.1); color:#8B2A20; }
  .row { background:#fff; border:1px solid #F5EBD8; border-radius:12px;
         padding:14px 16px; margin-bottom:10px; display:flex; gap:14px; align-items:flex-start; }
  .dot { width:11px; height:11px; border-radius:50%; flex-shrink:0; margin-top:7px; }
  .dot.ok{background:var(--green);} .dot.warn{background:var(--amber);} .dot.fail{background:var(--red);}
  .row-main { flex:1; min-width:0; }
  .row-label { font-weight:700; font-size:.98rem; }
  .row-detail { color:#4A4A4A; font-size:.9rem; margin-top:2px; }
  .row-fix { margin-top:8px; padding:9px 12px; border-radius:8px;
             background:var(--cream); font-size:.87rem; color:#5C3A26; }
  code { background:rgba(0,0,0,.06); padding:1px 5px; border-radius:4px;
         font-size:.87em; font-family:ui-monospace,Menlo,Consolas,monospace; }
  .foot { margin-top:26px; padding-top:18px; border-top:1px solid #F5EBD8;
          color:var(--gray); font-size:.84rem; }
  a { color:var(--gold); }
</style>
</head>
<body>
<div class="wrap">
  <h1>Online Ordering &mdash; Self-Check</h1>
  <p class="sub">Nothing secret is shown on this page &mdash; only whether each setting exists.</p>

  <div class="summary">
    <span class="pill ok"><?= $okCount ?> passing</span>
    <?php if ($warnCount): ?><span class="pill warn"><?= $warnCount ?> heads-up</span><?php endif; ?>
    <?php if ($failCount): ?><span class="pill fail"><?= $failCount ?> blocking</span><?php endif; ?>
  </div>

  <?php if ($fatal): ?>
    <div class="banner bad">Ordering can't run yet. Fix the red items below, top to bottom.</div>
  <?php elseif ($failCount): ?>
    <div class="banner bad">Something needs attention &mdash; see the red items below.</div>
  <?php else: ?>
    <div class="banner good">Everything checks out. You're good to go.</div>
  <?php endif; ?>

  <?php foreach ($checks as $c): ?>
    <div class="row">
      <span class="dot <?= $c['state'] ?>"></span>
      <div class="row-main">
        <div class="row-label"><?= $c['label'] ?></div>
        <div class="row-detail"><?= $c['detail'] ?></div>
        <?php if (!empty($c['fix'])): ?>
          <div class="row-fix"><?= $c['fix'] ?></div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="foot">
    Setup guide: <code>ORDERING.md</code> &middot;
    Storefront: <a href="../order.php">order.php</a> &middot;
    Kitchen: <a href="../kds/">kds/</a><br>
    Delete this file once everything is running, if you'd rather it not exist.
  </p>
</div>
</body>
</html>
