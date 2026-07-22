<?php
/**
 * Bagel Boyz NJ — customer order tracking.
 *
 * Reached from the confirmation screen and the receipt email:
 *   /track.php?code=BB-4821&t=<track_token>
 *
 * Both parameters are required — the token is what authorizes reading the
 * order, so a guessed order code on its own gets nothing.
 */
require_once __DIR__ . '/includes/order-lib.php';

$code  = trim((string) ($_GET['code'] ?? ''));
$token = trim((string) ($_GET['t'] ?? ''));
$valid = $code !== '' && $token !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
  <title>Track Your Order | Bagel Boyz NJ</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="css/order.css">
  <link rel="stylesheet" href="css/track.css">
</head>
<body class="track-page">

  <?php include 'includes/nav.php'; ?>

  <main class="track-wrap">
    <div class="container track-container">

      <?php if (!$valid): ?>
        <div class="track-card track-missing">
          <i class="fas fa-link-slash"></i>
          <h1>We need your order link</h1>
          <p>Open the link from your confirmation email, or give us a call and we'll check on it for you.</p>
          <div class="track-actions">
            <a href="tel:7326464455" class="btn btn-primary"><i class="fas fa-phone"></i> (732) 646-4455</a>
            <a href="order.php" class="btn btn-secondary">Start a New Order</a>
          </div>
        </div>

      <?php else: ?>
        <div id="track-loading" class="track-card">
          <div class="spinner"></div>
          <p style="text-align:center;color:var(--bb-gray);">Checking on your order…</p>
        </div>

        <div id="track-error" class="track-card track-missing" hidden>
          <i class="fas fa-circle-question"></i>
          <h1>We couldn't find that order</h1>
          <p id="track-error-msg">Double-check the link from your confirmation email.</p>
          <div class="track-actions">
            <a href="tel:7326464455" class="btn btn-primary"><i class="fas fa-phone"></i> Call Us</a>
            <a href="order.php" class="btn btn-secondary">New Order</a>
          </div>
        </div>

        <div id="track-content" hidden>

          <!-- Status hero -->
          <div class="track-hero" id="track-hero">
            <div class="hero-icon" id="hero-icon"><i class="fas fa-receipt"></i></div>
            <div class="hero-status" id="hero-status">Order received</div>
            <p class="hero-blurb" id="hero-blurb"></p>
            <div class="hero-eta" id="hero-eta" hidden></div>
          </div>

          <!-- Progress rail -->
          <div class="track-card" id="progress-card">
            <div class="progress-rail" id="progress-rail"></div>
          </div>

          <!-- Order summary -->
          <div class="track-card">
            <div class="track-code-row">
              <div>
                <span class="track-label">Order Number</span>
                <div class="track-code" id="t-code">BB-0000</div>
              </div>
              <div class="track-right">
                <span class="track-label">Pickup</span>
                <div class="track-pickup" id="t-pickup">—</div>
              </div>
            </div>

            <div class="track-sep"></div>

            <div id="t-items" class="track-items"></div>

            <div class="track-sep"></div>

            <div id="t-totals"></div>

            <div class="track-pay" id="t-pay"></div>
          </div>

          <!-- Location -->
          <div class="track-card track-loc">
            <div>
              <span class="track-label">Pick up at</span>
              <div class="track-loc-name" id="t-loc-name">—</div>
              <div class="track-loc-addr" id="t-loc-addr">—</div>
            </div>
            <div class="track-loc-actions">
              <a href="#" id="t-loc-map" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                <i class="fas fa-diamond-turn-right"></i> Directions
              </a>
              <a href="#" id="t-loc-call" class="btn btn-secondary btn-sm">
                <i class="fas fa-phone"></i> Call
              </a>
            </div>
          </div>

          <p class="track-refresh" id="track-refresh">
            <i class="fas fa-rotate"></i> This page updates itself — keep it open.
          </p>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <?php include 'includes/footer.php'; ?>

  <?php if ($valid): ?>
  <script>
    window.BB_TRACK = {
      code:  <?= json_encode($code) ?>,
      token: <?= json_encode($token) ?>
    };
  </script>
  <script src="js/track.js"></script>
  <?php endif; ?>
</body>
</html>
