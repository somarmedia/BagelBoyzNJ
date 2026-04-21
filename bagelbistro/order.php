<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
  <title>Order Online | Bagel Bistro Hillsborough - DoorDash &amp; Pickup</title>
  <meta name="description" content="Order Bagel Bistro on DoorDash or call ahead for pickup at 450 Amwell Rd, Hillsborough NJ. Open 6:30 AM - 2 PM every day.">
  <meta property="og:title" content="Order Online | Bagel Bistro">
  <meta property="og:description" content="Order Bagel Bistro on DoorDash or call ahead for pickup.">
  <link rel="canonical" href="https://bagelboyznj.com/bagelbistro/order.php">
</head>
<body>

  <?php include 'includes/nav.php'; ?>

  <!-- Page Header -->
  <div class="page-header">
    <h1>Order Online</h1>
    <p class="subtitle">Ready when you walk in</p>
  </div>

  <!-- Platforms -->
  <section class="section">
    <div class="container">
      <div class="text-center">
        <span class="section-label">Pick Your Platform</span>
        <h2 class="section-title">Two Easy Ways to Get Your Bagels</h2>
        <p class="section-subtitle">Delivery through DoorDash, or call ahead and we'll have it waiting on the counter.</p>
      </div>
      <div class="order-platforms">
        <div class="order-card animate-on-scroll">
          <div class="order-card-icon"><i class="fas fa-truck"></i></div>
          <h3>DoorDash</h3>
          <p>Full menu available for delivery across Hillsborough and the surrounding area.</p>
          <a href="https://www.doordash.com/store/bagel-bistro-hillsborough-township-955617/" target="_blank" rel="noopener" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
            <i class="fas fa-utensils"></i> Order on DoorDash
          </a>
        </div>
        <div class="order-card animate-on-scroll">
          <div class="order-card-icon"><i class="fas fa-phone"></i></div>
          <h3>Call Ahead Pickup</h3>
          <p>Give us a ring, we'll pack it up and have it boxed by the time you pull in. Fastest option for a big order.</p>
          <a href="tel:9083597929" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
            <i class="fas fa-phone"></i> (908) 359-7929
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- Hours + Address -->
  <section class="section section-dark">
    <div class="container">
      <div class="text-center">
        <span class="section-label" style="color: var(--bb-red-light);">Store Info</span>
        <h2 class="section-title">Hours &amp; Location</h2>
      </div>
      <div class="features-grid" style="max-width: 900px; margin: 0 auto;">
        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(200,16,46,0.2);">
          <div class="feature-icon" style="background: rgba(200,16,46,0.15);"><i class="fas fa-map-marker-alt" style="color: var(--bb-red-light);"></i></div>
          <h3 style="color: var(--bb-cream);">Address</h3>
          <p style="color: var(--bb-cream-dark);">450 Amwell Rd<br>Hillsborough Township, NJ 08844</p>
          <a href="https://maps.google.com/?q=450+Amwell+Rd+Hillsborough+NJ+08844" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="border-color: var(--bb-red-light); color: var(--bb-red-light);"><i class="fas fa-directions"></i> Directions</a>
        </div>
        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(200,16,46,0.2);">
          <div class="feature-icon" style="background: rgba(200,16,46,0.15);"><i class="fas fa-clock" style="color: var(--bb-red-light);"></i></div>
          <h3 style="color: var(--bb-cream);">Hours</h3>
          <p style="color: var(--bb-cream-dark);">Monday - Sunday<br>6:30 AM - 2:00 PM<br><strong style="color: var(--bb-red-light);">Open 7 Days</strong></p>
        </div>
        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(200,16,46,0.2);">
          <div class="feature-icon" style="background: rgba(200,16,46,0.15);"><i class="fas fa-phone" style="color: var(--bb-red-light);"></i></div>
          <h3 style="color: var(--bb-cream);">Phone</h3>
          <p style="color: var(--bb-cream-dark);"><a href="tel:9083597929" style="color: var(--bb-cream-dark);">(908) 359-7929</a></p>
          <a href="tel:9083597929" class="btn btn-secondary btn-sm" style="border-color: var(--bb-red-light); color: var(--bb-red-light);"><i class="fas fa-phone"></i> Call Now</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Catering callout -->
  <section class="cta-banner">
    <div class="container">
      <h2>Ordering for a Crew?</h2>
      <p>Office meetings, team breakfasts, family gatherings — check out our catering options for bagel platters and sandwich trays.</p>
      <a href="catering.php" class="btn btn-white btn-lg"><i class="fas fa-clipboard-list"></i> View Catering</a>
    </div>
  </section>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
