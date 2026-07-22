<?php
/**
 * Bagel Boyz NJ — Online Ordering
 *
 * The menu, cart and checkout all live in js/order.js against the /api
 * endpoints. This page renders the shell plus a no-JS fallback so the page
 * is never a dead end.
 */
require_once __DIR__ . '/includes/order-lib.php';
require_once __DIR__ . '/includes/order-preview.php';

$cfg       = bb_config();
$locations = $cfg['locations'] ?? [];

// Only offer locations that actually take online orders, and default to the
// first of those — not simply the first configured location.
$orderable = array_filter($locations, function ($l) { return !empty($l['online_ordering']); });
$defaultLocation = $orderable ? array_key_first($orderable) : array_key_first($locations);

// Until ordering.public is true, only a visitor holding the preview key sees
// the new system. Everyone else gets the original page, unchanged.
$showOrdering = bb_ordering_visible();
$isPreview    = bb_in_preview_mode();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
<?php if ($showOrdering): ?>
  <title>Order Online | Bagel Boyz NJ — Pickup from Hazlet</title>
  <meta name="description" content="Order Bagel Boyz NJ online for pickup. Build your bagel or breakfast sandwich exactly how you want it, pay online, and skip the line at either Hazlet location.">
  <meta property="og:title" content="Order Online | Bagel Boyz NJ">
  <meta property="og:description" content="Order ahead for pickup from either Hazlet location. Customize everything, pay online, skip the line.">
  <link rel="stylesheet" href="css/order.css">
  <?php if ($isPreview): ?>
  <!-- Never let a preview page be indexed or shared as the real thing. -->
  <meta name="robots" content="noindex, nofollow">
  <?php endif; ?>
<?php else: ?>
  <title>Order Online | Bagel Boyz NJ - DoorDash &amp; Grubhub Delivery</title>
  <meta name="description" content="Order Bagel Boyz NJ online through DoorDash or Grubhub. Delivery and pickup available from both Hazlet locations. Fresh bagels and breakfast delivered to your door.">
  <meta property="og:title" content="Order Online | Bagel Boyz NJ">
  <meta property="og:description" content="Order through DoorDash or Grubhub. Delivery from both Hazlet locations.">
<?php endif; ?>
  <meta property="og:image" content="img/BBLOGO.1000px.png">
  <link rel="canonical" href="https://bagelboyznj.com/order.php">
</head>
<body class="<?= $showOrdering ? 'ordering-page' : '' ?>">

  <?php include 'includes/nav.php'; ?>

<?php if (!$showOrdering): ?>
  <?php
    /* ---------------------------------------------------------------
       What every customer sees today. Identical to the page that was
       live before online ordering existed.
       --------------------------------------------------------------- */
    include 'includes/order-legacy.php';
    include 'includes/footer.php';
  ?>
</body>
</html>
<?php return; endif; ?>

  <?= bb_preview_banner() ?>

  <!-- ================================================================
       ORDER BAR — location, timing, and the live open/closed state
       ================================================================ -->
  <div class="order-bar" id="order-bar">
    <div class="container order-bar-inner">
      <div class="ob-group">
        <span class="ob-label">Pickup from</span>
        <div class="ob-locations" id="ob-locations">
          <?php foreach ($orderable as $id => $loc): ?>
            <button type="button" class="ob-loc<?= $id === $defaultLocation ? ' active' : '' ?>" data-loc="<?= htmlspecialchars($id) ?>">
              <?= htmlspecialchars($loc['name']) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="ob-group ob-status" id="ob-status">
        <span class="ob-dot"></span>
        <span class="ob-status-text">Checking…</span>
      </div>
    </div>
  </div>

  <div class="closed-notice" id="closed-notice" hidden>
    <div class="container">
      <i class="fas fa-circle-info"></i>
      <span id="closed-text"></span>
    </div>
  </div>

  <!-- ================================================================
       MENU
       ================================================================ -->
  <div class="order-layout">
    <div class="container order-container">

      <div class="order-main">
        <!-- Sticky category rail -->
        <nav class="cat-nav" id="cat-nav" aria-label="Menu categories"></nav>

        <div class="menu-loading" id="menu-loading">
          <div class="spinner"></div>
          <p>Loading the menu…</p>
        </div>

        <div id="menu-root"></div>

        <!-- Shown only if the API can't be reached at all. Keeps every route
             to placing an order that this page had before online ordering
             existed, so a backend outage is never a dead end. -->
        <div class="menu-error" id="menu-error" hidden>
          <i class="fas fa-triangle-exclamation"></i>
          <h2>Online ordering is temporarily unavailable</h2>
          <p>You can still order — call us directly, or use DoorDash or Grubhub.</p>
          <div class="menu-error-actions">
            <a href="tel:7326464455" class="btn btn-primary"><i class="fas fa-phone"></i> Holmdel Rd &mdash; (732) 646-4455</a>
            <a href="tel:7323351300" class="btn btn-primary"><i class="fas fa-phone"></i> Airport Plaza &mdash; (732) 335-1300</a>
          </div>
          <div class="menu-error-actions" style="margin-top: var(--space-md);">
            <a href="https://www.grubhub.com/restaurant/bagel-boyz-694-holmdel-road-hazlet/12946592" target="_blank" rel="noopener" class="btn btn-secondary">
              <i class="fas fa-external-link-alt"></i> Grubhub (Holmdel Rd)
            </a>
          </div>
        </div>
      </div>

      <!-- Desktop cart -->
      <aside class="cart-rail" id="cart-rail">
        <div class="cart-card">
          <h3 class="cart-title"><i class="fas fa-basket-shopping"></i> Your Order</h3>
          <div id="cart-lines-desktop" class="cart-lines"></div>
          <div id="cart-totals-desktop" class="cart-totals"></div>
          <button type="button" class="btn-checkout" id="btn-checkout-desktop" disabled>
            Checkout
          </button>
        </div>
      </aside>

    </div>
  </div>

  <!-- ================================================================
       MOBILE CART BAR
       ================================================================ -->
  <button type="button" class="cart-fab" id="cart-fab" hidden>
    <span class="fab-count" id="fab-count">0</span>
    <span class="fab-label">View Order</span>
    <span class="fab-total" id="fab-total">$0.00</span>
  </button>

  <!-- ================================================================
       ITEM CUSTOMIZATION
       ================================================================ -->
  <div class="sheet" id="item-sheet" hidden>
    <div class="sheet-backdrop" data-close></div>
    <div class="sheet-panel">
      <header class="sheet-head">
        <button type="button" class="sheet-x" data-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        <div>
          <h2 id="is-name">Item</h2>
          <p id="is-desc" class="sheet-desc"></p>
        </div>
      </header>

      <div class="sheet-body" id="is-body"></div>

      <footer class="sheet-foot">
        <div class="qty-stepper">
          <button type="button" id="is-minus" aria-label="Decrease quantity"><i class="fas fa-minus"></i></button>
          <span id="is-qty">1</span>
          <button type="button" id="is-plus" aria-label="Increase quantity"><i class="fas fa-plus"></i></button>
        </div>
        <button type="button" class="btn-add" id="is-add">
          Add to Order &middot; <span id="is-price">$0.00</span>
        </button>
      </footer>
    </div>
  </div>

  <!-- ================================================================
       CART (mobile sheet)
       ================================================================ -->
  <div class="sheet" id="cart-sheet" hidden>
    <div class="sheet-backdrop" data-close></div>
    <div class="sheet-panel">
      <header class="sheet-head">
        <button type="button" class="sheet-x" data-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        <div><h2>Your Order</h2></div>
      </header>
      <div class="sheet-body">
        <div id="cart-lines-mobile" class="cart-lines"></div>
        <div id="cart-totals-mobile" class="cart-totals"></div>
      </div>
      <footer class="sheet-foot">
        <button type="button" class="btn-add" id="btn-checkout-mobile">Checkout</button>
      </footer>
    </div>
  </div>

  <!-- ================================================================
       CHECKOUT
       ================================================================ -->
  <div class="sheet" id="checkout-sheet" hidden>
    <div class="sheet-backdrop" data-close></div>
    <div class="sheet-panel">
      <header class="sheet-head">
        <button type="button" class="sheet-x" data-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        <div><h2>Checkout</h2><p class="sheet-desc" id="co-location"></p></div>
      </header>

      <div class="sheet-body">
        <form id="checkout-form" novalidate>
          <!-- Bots fill this; humans never see it. -->
          <div class="hp-field"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

          <div class="co-section">
            <h3 class="co-h">When do you want it?</h3>
            <div class="pickup-toggle" id="pickup-toggle">
              <button type="button" class="pt-btn active" data-type="asap">
                <span class="pt-title">ASAP</span>
                <span class="pt-sub" id="pt-asap-sub">Ready soon</span>
              </button>
              <button type="button" class="pt-btn" data-type="scheduled">
                <span class="pt-title">Schedule</span>
                <span class="pt-sub">Pick a time</span>
              </button>
            </div>
            <div class="slot-picker" id="slot-picker" hidden>
              <select id="slot-day" class="field-select"></select>
              <select id="slot-time" class="field-select"></select>
            </div>
          </div>

          <div class="co-section">
            <h3 class="co-h">Who's it for?</h3>
            <label class="field">
              <span class="field-label">Name <em>*</em></span>
              <input type="text" id="co-name" autocomplete="name" placeholder="First and last" required>
            </label>
            <label class="field">
              <span class="field-label">Mobile number <em>*</em></span>
              <input type="tel" id="co-phone" autocomplete="tel" inputmode="tel" placeholder="(732) 555-0100" required>
              <span class="field-help">Only if we have a question about your order.</span>
            </label>
            <label class="field">
              <span class="field-label">Email</span>
              <input type="email" id="co-email" autocomplete="email" placeholder="you@email.com">
              <span class="field-help">For your receipt and a "your order is ready" heads-up.</span>
            </label>
            <label class="field">
              <span class="field-label">Anything else we should know?</span>
              <textarea id="co-notes" rows="2" placeholder="Allergies, where you'll park, etc."></textarea>
            </label>
          </div>

          <div class="co-section" id="tip-section">
            <h3 class="co-h">Add a tip for the crew?</h3>
            <div class="tip-row" id="tip-row"></div>
          </div>

          <div class="co-section" id="payment-section">
            <h3 class="co-h">How do you want to pay?</h3>
            <div class="pay-options" id="pay-options"></div>
            <!-- Stripe Payment Element mounts here when card is selected -->
            <div id="stripe-element" class="stripe-mount" hidden></div>
            <p class="pay-note" id="pay-note"></p>
          </div>

          <div class="co-totals" id="co-totals"></div>
          <p class="co-error" id="co-error" hidden></p>
        </form>
      </div>

      <footer class="sheet-foot">
        <!-- The label lives in its own span so JS can rewrite the button text
             without destroying #co-total-btn. -->
        <button type="button" class="btn-add" id="btn-place-order">
          <span id="co-btn-label">Place Order</span> &middot; <span id="co-total-btn">$0.00</span>
        </button>
      </footer>
    </div>
  </div>

  <!-- ================================================================
       CONFIRMATION
       ================================================================ -->
  <div class="sheet" id="confirm-sheet" hidden>
    <div class="sheet-backdrop"></div>
    <div class="sheet-panel sheet-confirm">
      <div class="confirm-body">
        <div class="confirm-check"><i class="fas fa-check"></i></div>
        <h2>Order placed!</h2>
        <p class="confirm-sub">We're on it. Here's your order number:</p>
        <div class="confirm-code" id="confirm-code">BB-0000</div>
        <p class="confirm-when" id="confirm-when"></p>
        <a href="#" class="btn btn-primary btn-block" id="confirm-track">
          <i class="fas fa-location-crosshairs"></i> Track Your Order
        </a>
        <a href="order.php" class="confirm-again">Order something else</a>
      </div>
    </div>
  </div>

  <div class="toast" id="toast" hidden></div>

  <?php include 'includes/footer.php'; ?>

  <script>
    window.BB_ORDER = {
      defaultLocation: <?= json_encode($defaultLocation) ?>,
      orderableLocations: <?= json_encode(array_keys($orderable)) ?>
    };
  </script>
  <script src="https://js.stripe.com/v3/"></script>
  <script src="js/order.js"></script>
</body>
</html>
