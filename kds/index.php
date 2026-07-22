<?php
/**
 * Bagel Boyz NJ — Kitchen Display System (iPad)
 *
 * Bookmark this on the iPad home screen and it runs full-screen like an app.
 * Keep the iPad plugged in and set Settings → Display → Auto-Lock to Never.
 */

require_once __DIR__ . '/_auth.php';

$session   = bb_kds_session();
$cfg       = bb_config();
$locations = $cfg['locations'] ?? [];
$printCfg  = [
    'webprnt_default' => '',   // filled in per-device from localStorage
    'chars'           => (int) bb_config('printing.chars_per_line', 42),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="BB Kitchen">
  <meta name="robots" content="noindex, nofollow">
  <title>Kitchen — Bagel Boyz NJ</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../img/favicon-BabgelBoyz-32x32.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/kds.css">
</head>
<body class="<?= $session ? 'is-authed' : 'is-locked' ?>">

<!-- ==================================================================
     LOGIN
     ================================================================== -->
<div id="login-screen" class="login-screen" <?= $session ? 'hidden' : '' ?>>
  <div class="login-card">
    <div class="login-logo">
      <img src="../img/BBLOGO.1000px.png" alt="Bagel Boyz NJ">
    </div>
    <h1>Kitchen Display</h1>

    <label class="field-label">Location</label>
    <div class="loc-picker" id="loc-picker">
      <?php $first = true; foreach ($locations as $id => $loc): ?>
        <button type="button" class="loc-btn<?= $first ? ' active' : '' ?>" data-loc="<?= htmlspecialchars($id) ?>">
          <?= htmlspecialchars($loc['name']) ?>
        </button>
      <?php $first = false; endforeach; ?>
    </div>

    <label class="field-label">PIN</label>
    <div class="pin-display" id="pin-display">
      <span class="pin-dot"></span><span class="pin-dot"></span>
      <span class="pin-dot"></span><span class="pin-dot"></span>
    </div>

    <div class="keypad" id="keypad">
      <?php foreach ([1,2,3,4,5,6,7,8,9] as $n): ?>
        <button type="button" class="key" data-key="<?= $n ?>"><?= $n ?></button>
      <?php endforeach; ?>
      <button type="button" class="key key-muted" data-key="clear"><i class="fas fa-xmark"></i></button>
      <button type="button" class="key" data-key="0">0</button>
      <button type="button" class="key key-muted" data-key="back"><i class="fas fa-delete-left"></i></button>
    </div>

    <p class="login-error" id="login-error" hidden></p>
    <p class="login-hint">Tap in your PIN to start the shift.</p>
  </div>
</div>

<!-- ==================================================================
     SOUND GATE — iOS will not let a page make noise until the user has
     tapped something. This is that tap.
     ================================================================== -->
<div id="sound-gate" class="sound-gate" hidden>
  <div class="gate-card">
    <div class="gate-icon"><i class="fas fa-volume-high"></i></div>
    <h2>Turn On Order Alerts</h2>
    <p>Tap below so the iPad can announce new orders out loud. You only need to do this once per shift.</p>
    <button type="button" class="btn-gate" id="enable-sound">
      <i class="fas fa-bell"></i> Start Shift
    </button>
  </div>
</div>

<!-- ==================================================================
     MAIN BOARD
     ================================================================== -->
<div id="app" class="app" <?= $session ? '' : 'hidden' ?>>

  <header class="topbar">
    <div class="topbar-left">
      <img src="../img/BBLOGO.1000px.png" alt="" class="topbar-logo">
      <div>
        <div class="topbar-loc" id="topbar-loc">&nbsp;</div>
        <div class="topbar-time" id="topbar-time">&nbsp;</div>
      </div>
    </div>

    <div class="topbar-counts">
      <div class="count-chip count-new">
        <span class="count-num" id="count-new">0</span>
        <span class="count-label">New</span>
      </div>
      <div class="count-chip count-making">
        <span class="count-num" id="count-making">0</span>
        <span class="count-label">Making</span>
      </div>
      <div class="count-chip count-ready">
        <span class="count-num" id="count-ready">0</span>
        <span class="count-label">Ready</span>
      </div>
    </div>

    <div class="topbar-right">
      <span class="conn-dot" id="conn-dot" title="Connection"></span>
      <button type="button" class="icon-btn" id="btn-86" title="86 Board"><i class="fas fa-ban"></i></button>
      <button type="button" class="icon-btn" id="btn-settings" title="Settings"><i class="fas fa-gear"></i></button>
    </div>
  </header>

  <!-- Store-paused banner -->
  <div class="pause-banner" id="pause-banner" hidden>
    <i class="fas fa-triangle-exclamation"></i>
    <span id="pause-text"></span>
    <button type="button" class="banner-btn" id="btn-resume">Resume Orders</button>
  </div>

  <!-- The alarm. Stays until someone taps it. -->
  <div class="alert-bar" id="alert-bar" hidden>
    <div class="alert-pulse"></div>
    <i class="fas fa-bell alert-bell"></i>
    <span class="alert-text"><span id="alert-count">1</span> NEW ORDER</span>
    <button type="button" class="alert-ack" id="alert-ack">GOT IT</button>
  </div>

  <main class="board" id="board">
    <div class="empty-state" id="empty-state">
      <i class="fas fa-mug-hot"></i>
      <h2>All caught up</h2>
      <p>New online orders will pop up here and announce themselves.</p>
    </div>
    <div class="cards" id="cards"></div>
  </main>
</div>

<!-- ==================================================================
     ORDER DETAIL
     ================================================================== -->
<div class="modal" id="order-modal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-panel modal-order">
    <header class="modal-head">
      <div>
        <div class="modal-code" id="m-code">BB-0000</div>
        <div class="modal-sub" id="m-sub"></div>
      </div>
      <button type="button" class="modal-x" data-close><i class="fas fa-xmark"></i></button>
    </header>

    <div class="modal-body" id="m-body"></div>

    <footer class="modal-foot" id="m-foot"></footer>
  </div>
</div>

<!-- ==================================================================
     86 BOARD
     ================================================================== -->
<div class="modal" id="eightysix-modal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-panel modal-wide">
    <header class="modal-head">
      <div>
        <div class="modal-code">86 Board</div>
        <div class="modal-sub">Tap an item to take it off the online menu right now.</div>
      </div>
      <button type="button" class="modal-x" data-close><i class="fas fa-xmark"></i></button>
    </header>
    <div class="modal-body">
      <input type="search" class="search-input" id="86-search" placeholder="Search the menu…" autocomplete="off">
      <div id="86-list" class="eightysix-list"></div>
    </div>
  </div>
</div>

<!-- ==================================================================
     SETTINGS
     ================================================================== -->
<div class="modal" id="settings-modal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-panel">
    <header class="modal-head">
      <div><div class="modal-code">Settings</div></div>
      <button type="button" class="modal-x" data-close><i class="fas fa-xmark"></i></button>
    </header>

    <div class="modal-body">
      <div class="setting-row">
        <div>
          <div class="setting-title">Taking online orders</div>
          <div class="setting-help">Turn off to stop new orders coming in entirely.</div>
        </div>
        <label class="switch">
          <input type="checkbox" id="set-accepting" checked>
          <span class="slider"></span>
        </label>
      </div>

      <div class="setting-block">
        <div class="setting-title">Quoted wait time</div>
        <div class="setting-help">What we promise customers for ASAP pickup.</div>
        <div class="chip-row" id="prep-chips">
          <?php foreach ([10, 15, 20, 30, 45] as $m): ?>
            <button type="button" class="chip" data-prep="<?= $m ?>"><?= $m ?> min</button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="setting-block">
        <div class="setting-title">Pause for a rush</div>
        <div class="setting-help">Temporarily stop new orders, then auto-resume.</div>
        <div class="chip-row" id="pause-chips">
          <?php foreach ([15, 30, 60] as $m): ?>
            <button type="button" class="chip" data-pause="<?= $m ?>"><?= $m ?> min</button>
          <?php endforeach; ?>
          <button type="button" class="chip chip-clear" data-pause="0">Resume now</button>
        </div>
      </div>

      <div class="setting-block">
        <div class="setting-title">Receipt printer (Star TSP100III)</div>
        <div class="setting-help">
          The TSP100III can't pull jobs from the web, so this iPad pushes tickets to it
          over your shop wifi. Enter the printer's IP address — you can print it from the
          printer itself by holding FEED while powering on.
        </div>
        <div class="ip-row">
          <input type="text" class="text-input" id="set-printer-ip" placeholder="192.168.1.50" inputmode="decimal" autocomplete="off">
          <button type="button" class="btn-small" id="btn-test-print">Test</button>
        </div>
        <label class="check-row">
          <input type="checkbox" id="set-autoprint" checked>
          <span>Print each new order automatically</span>
        </label>
        <p class="setting-note" id="print-note"></p>
      </div>

      <div class="setting-block">
        <div class="setting-title">Sound</div>
        <div class="chip-row">
          <button type="button" class="chip" id="btn-test-sound"><i class="fas fa-volume-high"></i> Test alert</button>
        </div>
      </div>

      <button type="button" class="btn-danger-outline" id="btn-signout">
        <i class="fas fa-right-from-bracket"></i> Sign out of this iPad
      </button>
    </div>
  </div>
</div>

<!-- Hidden container the AirPrint fallback prints from -->
<div id="print-area" class="print-area" aria-hidden="true"></div>

<div class="toast" id="toast" hidden></div>

<script>
  window.BB_KDS = {
    locations: <?= json_encode(array_map(function ($l) { return $l['name']; }, $locations)) ?>,
    pollSeconds:  <?= (int) bb_config('kds.poll_seconds', 5) ?>,
    alertRepeat:  <?= (int) bb_config('kds.alert_repeat_seconds', 6) ?>,
    alertText:    <?= json_encode(bb_config('kds.alert_voice_text', 'You have a new order')) ?>,
    signedIn:     <?= $session ? 'true' : 'false' ?>,
    location:     <?= json_encode($session['location_id'] ?? null) ?>
  };
</script>
<script src="../js/kds.js"></script>
</body>
</html>
