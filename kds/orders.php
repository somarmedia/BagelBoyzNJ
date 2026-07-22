<?php
/**
 * Bagel Boyz NJ — Order history / review
 *   https://bagelboyznj.com/kds/orders.php
 *
 * Everything the live board drops once an order is handed over. Same PIN
 * session as the kitchen display.
 */

require_once __DIR__ . '/_auth.php';

$session = bb_kds_session();
if (!$session) {
    header('Location: index.php');
    exit;
}
$cfg = bb_config();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="robots" content="noindex, nofollow">
  <title>Orders — Bagel Boyz NJ</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../img/favicon-BabgelBoyz-32x32.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/kds.css">
</head>
<body>

<div class="app">

  <header class="topbar">
    <div class="topbar-left">
      <a href="index.php" class="icon-btn" title="Back to the board"><i class="fas fa-arrow-left"></i></a>
      <div>
        <div class="topbar-loc">Orders</div>
        <div class="topbar-time" id="topbar-loc">&nbsp;</div>
      </div>
    </div>
    <div class="topbar-right">
      <button type="button" class="icon-btn" id="btn-print-day" title="Print this list"><i class="fas fa-print"></i></button>
    </div>
  </header>

  <!-- Filters -->
  <div class="ord-filters">

    <!-- Location toggle. Rendered from config so adding a third store
         needs no change here. -->
    <div class="loc-switch" id="loc-switch">
      <span class="loc-switch-glider" id="loc-glider"></span>
      <button type="button" class="loc-seg active" data-loc="all">Both</button>
      <?php foreach (($cfg['locations'] ?? []) as $id => $loc): ?>
        <button type="button" class="loc-seg" data-loc="<?= htmlspecialchars($id) ?>">
          <?= htmlspecialchars($loc['name']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="chip-row" id="range-chips">
      <button type="button" class="chip active" data-range="today">Today</button>
      <button type="button" class="chip" data-range="yesterday">Yesterday</button>
      <button type="button" class="chip" data-range="7">Last 7 days</button>
      <button type="button" class="chip" data-range="30">Last 30 days</button>
    </div>

    <div class="ord-filter-row">
      <input type="date" class="text-input" id="f-from">
      <span class="ord-dash">to</span>
      <input type="date" class="text-input" id="f-to">
    </div>

    <div class="ord-filter-row">
      <select class="text-input" id="f-status">
        <option value="all">All statuses</option>
        <option value="active">Still working on</option>
        <option value="completed">Picked up</option>
        <option value="cancelled">Cancelled</option>
        <option value="pending_payment">Awaiting payment</option>
      </select>
      <input type="search" class="text-input" id="f-q" placeholder="Order number, name or phone…" autocomplete="off">
    </div>
  </div>

  <!-- Day totals -->
  <div class="ord-totals" id="ord-totals"></div>

  <main class="board">
    <div id="ord-list"></div>
    <div class="empty-state" id="ord-empty" hidden>
      <i class="fas fa-receipt"></i>
      <h2>No orders</h2>
      <p>Nothing matches those filters.</p>
    </div>
    <div class="ord-paging" id="ord-paging"></div>
  </main>
</div>

<!-- Order detail -->
<div class="modal" id="detail-modal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-panel modal-order">
    <header class="modal-head">
      <div>
        <div class="modal-code" id="d-code">BB-0000</div>
        <div class="modal-sub" id="d-sub"></div>
      </div>
      <button type="button" class="modal-x" data-close><i class="fas fa-xmark"></i></button>
    </header>
    <div class="modal-body" id="d-body"></div>
    <footer class="modal-foot">
      <button type="button" class="btn-outline" id="d-reprint"><i class="fas fa-print"></i> Reprint ticket</button>
    </footer>
  </div>
</div>

<div id="print-area" class="print-area" aria-hidden="true"></div>
<div class="toast" id="toast" hidden></div>

<script src="../js/kds-orders.js"></script>
</body>
</html>
