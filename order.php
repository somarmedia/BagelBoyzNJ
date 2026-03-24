<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
  <title>Order Online | Bagel Boyz NJ - DoorDash & Grubhub Delivery</title>
  <meta name="description" content="Order Bagel Boyz NJ online through DoorDash or Grubhub. Delivery and pickup available from both Hazlet locations. Fresh bagels and breakfast delivered to your door.">
  <meta property="og:title" content="Order Online | Bagel Boyz NJ">
  <meta property="og:description" content="Order through DoorDash or Grubhub. Delivery from both Hazlet locations.">
  <link rel="canonical" href="https://bagelboyznj.com/order.php">
</head>
<body>

  <?php include 'includes/nav.php'; ?>

  <!-- Page Header -->
  <div class="page-header">
    <h1>Order Online</h1>
    <p class="subtitle">Delivery or pickup &mdash; your call</p>
  </div>

  <!-- Order Platforms -->
  <section class="section">
    <div class="container">
      <div class="text-center">
        <span class="section-label">Choose Your Platform</span>
        <h2 class="section-title">How Would You Like to Order?</h2>
        <p class="section-subtitle">Order through DoorDash or Grubhub for delivery, or call ahead for pickup at either location.</p>
      </div>

      <div class="order-platforms">
        <!-- DoorDash -->
        <div class="order-card animate-on-scroll">
          <div style="font-size: 3rem; color: #FF3008; margin-bottom: var(--space-md);"><i class="fas fa-motorcycle"></i></div>
          <h3>DoorDash</h3>
          <p>Fast delivery right to your door. Track your order in real time.</p>

          <h4 style="margin-bottom: var(--space-sm); color: var(--bb-gray);">Holmdel Rd Location</h4>
          <a href="#" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: var(--space-md);" id="doordash-holmdel">
            <i class="fas fa-external-link-alt"></i> Order on DoorDash
          </a>

          <h4 style="margin-bottom: var(--space-sm); color: var(--bb-gray);">Airport Plaza Location</h4>
          <a href="#" class="btn btn-primary" style="width: 100%; justify-content: center;" id="doordash-airport">
            <i class="fas fa-external-link-alt"></i> Order on DoorDash
          </a>
        </div>

        <!-- Grubhub -->
        <div class="order-card animate-on-scroll">
          <div style="font-size: 3rem; color: #F63440; margin-bottom: var(--space-md);"><i class="fas fa-utensils"></i></div>
          <h3>Grubhub</h3>
          <p>Order ahead for delivery or pickup. Earn Grubhub rewards points.</p>

          <h4 style="margin-bottom: var(--space-sm); color: var(--bb-gray);">Holmdel Rd Location</h4>
          <a href="https://www.grubhub.com/restaurant/bagel-boyz-694-holmdel-road-hazlet/12946592" target="_blank" rel="noopener" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: var(--space-md);" id="grubhub-holmdel">
            <i class="fas fa-external-link-alt"></i> Order on Grubhub
          </a>

          <h4 style="margin-bottom: var(--space-sm); color: var(--bb-gray);">Airport Plaza Location</h4>
          <span style="display: block; padding: 0.8rem; text-align: center; color: var(--bb-gray); font-size: 0.9rem;">Not yet on Grubhub — call to order</span>
          <a href="tel:7323351300" class="btn btn-secondary" style="width: 100%; justify-content: center;" id="grubhub-airport">
            <i class="fas fa-phone"></i> (732) 335-1300
          </a>
        </div>

        <!-- Call Ahead -->
        <div class="order-card animate-on-scroll">
          <div style="font-size: 3rem; color: var(--bb-gold); margin-bottom: var(--space-md);"><i class="fas fa-phone-alt"></i></div>
          <h3>Call Ahead</h3>
          <p>Call in your order and we'll have it ready when you walk in. No wait, no fee.</p>

          <h4 style="margin-bottom: var(--space-sm); color: var(--bb-gray);">Holmdel Rd Location</h4>
          <a href="tel:7326464455" class="btn btn-dark" style="width: 100%; justify-content: center; margin-bottom: var(--space-md);">
            <i class="fas fa-phone"></i> (732) 646-4455
          </a>

          <h4 style="margin-bottom: var(--space-sm); color: var(--bb-gray);">Airport Plaza Location</h4>
          <a href="tel:7323351300" class="btn btn-dark" style="width: 100%; justify-content: center;">
            <i class="fas fa-phone"></i> (732) 335-1300
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Tips -->
  <section class="section section-dark">
    <div class="container text-center">
      <h2>Pro Tips for Ordering</h2>
      <div class="features-grid" style="margin-top: var(--space-xl);">
        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(212,144,30,0.2);">
          <div class="feature-icon" style="background: rgba(212,144,30,0.15);"><i class="fas fa-clock" style="color: var(--bb-gold);"></i></div>
          <h3 style="color: var(--bb-cream);">Beat the Rush</h3>
          <p style="color: var(--bb-cream-dark);">The morning rush hits hardest between 7-9 AM on weekends. Order ahead or come in early for the shortest wait.</p>
        </div>
        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(212,144,30,0.2);">
          <div class="feature-icon" style="background: rgba(212,144,30,0.15);"><i class="fas fa-pepper-hot" style="color: var(--bb-gold);"></i></div>
          <h3 style="color: var(--bb-cream);">Don't Forget the SPK</h3>
          <p style="color: var(--bb-cream-dark);">Salt, Pepper, Ketchup — the holy trinity of NJ breakfast sandwiches. Ask for it on any egg sandwich.</p>
        </div>
        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(212,144,30,0.2);">
          <div class="feature-icon" style="background: rgba(212,144,30,0.15);"><i class="fas fa-users" style="color: var(--bb-gold);"></i></div>
          <h3 style="color: var(--bb-cream);">Feeding a Group?</h3>
          <p style="color: var(--bb-cream-dark);">Check out our <a href="catering.php" style="color: var(--bb-gold-light);">catering packages</a> for office meetings, parties, and events. We handle groups of any size.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Location Reminder -->
  <section class="cta-banner">
    <div class="container">
      <h2>Both Locations Open 7 Days</h2>
      <p>6:00 AM - 3:00 PM, every day of the week. Come hungry.</p>
      <a href="index.php#locations" class="btn btn-white btn-lg"><i class="fas fa-map-marker-alt"></i> See Locations & Directions</a>
    </div>
  </section>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
