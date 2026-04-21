<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
  <title>Contact Us | Bagel Bistro Hillsborough - Get in Touch</title>
  <meta name="description" content="Contact Bagel Bistro in Hillsborough, NJ. Call (908) 359-7929, email bagelbistro450@gmail.com, or stop by 450 Amwell Rd. Open 6:30 AM - 2 PM every day.">
  <meta property="og:title" content="Contact Bagel Bistro | Hillsborough, NJ">
  <meta property="og:description" content="Get in touch with Bagel Bistro. Phone, email, or stop by 450 Amwell Rd in Hillsborough.">
  <link rel="canonical" href="https://bagelboyznj.com/bagelbistro/contact.php">
</head>
<body>

  <?php include 'includes/nav.php'; ?>

  <!-- Page Header -->
  <div class="page-header">
    <h1>Get in Touch</h1>
    <p class="subtitle">Questions, feedback, special requests</p>
  </div>

  <!-- Contact Info + Form -->
  <section class="section">
    <div class="container">
      <div class="story-section" style="align-items: flex-start;">

        <!-- Info Column -->
        <div class="story-content">
          <span class="section-label">Reach Out</span>
          <h2>How to Find Us</h2>
          <p>Got a question about a menu item, a catering request, or something special you want us to cook up? We're a phone call (or walk in) away.</p>

          <div style="background: var(--bb-white); border-radius: var(--radius-lg); padding: var(--space-lg); box-shadow: var(--shadow-sm); margin-top: var(--space-lg);">
            <div class="location-details">
              <div class="detail">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                  <strong style="color: var(--bb-charcoal);">Visit</strong><br>
                  <span>450 Amwell Rd<br>Hillsborough Township, NJ 08844</span>
                </div>
              </div>
              <div class="detail" style="margin-top: var(--space-md);">
                <i class="fas fa-phone"></i>
                <div>
                  <strong style="color: var(--bb-charcoal);">Call</strong><br>
                  <a href="tel:9083597929">(908) 359-7929</a>
                </div>
              </div>
              <div class="detail" style="margin-top: var(--space-md);">
                <i class="fas fa-envelope"></i>
                <div>
                  <strong style="color: var(--bb-charcoal);">Email</strong><br>
                  <a href="mailto:bagelbistro450@gmail.com">bagelbistro450@gmail.com</a>
                </div>
              </div>
              <div class="detail" style="margin-top: var(--space-md);">
                <i class="fas fa-clock"></i>
                <div>
                  <strong style="color: var(--bb-charcoal);">Hours</strong><br>
                  <span>Mon - Sun: 6:30 AM - 2:00 PM</span>
                </div>
              </div>
            </div>

            <div style="margin-top: var(--space-lg); display: flex; gap: var(--space-sm); flex-wrap: wrap;">
              <a href="https://maps.google.com/?q=450+Amwell+Rd+Hillsborough+NJ+08844" target="_blank" rel="noopener" class="btn btn-primary btn-sm"><i class="fas fa-directions"></i> Directions</a>
              <a href="tel:9083597929" class="btn btn-secondary btn-sm"><i class="fas fa-phone"></i> Call</a>
            </div>
          </div>

          <div style="margin-top: var(--space-lg); display: flex; gap: var(--space-md); align-items: center;">
            <span style="color: var(--bb-charcoal); font-weight: 600;">Follow Us:</span>
            <a href="https://www.instagram.com/bagelbistroboro" target="_blank" rel="noopener" aria-label="Instagram" style="width: 40px; height: 40px; border-radius: 50%; background: var(--bb-red); color: var(--bb-white); display: inline-flex; align-items: center; justify-content: center;"><i class="fab fa-instagram"></i></a>
            <a href="https://www.facebook.com/BagelBistro" target="_blank" rel="noopener" aria-label="Facebook" style="width: 40px; height: 40px; border-radius: 50%; background: var(--bb-red); color: var(--bb-white); display: inline-flex; align-items: center; justify-content: center;"><i class="fab fa-facebook-f"></i></a>
          </div>
        </div>

        <!-- Form Column -->
        <div>
          <div style="background: var(--bb-white); border-radius: var(--radius-lg); padding: var(--space-xl); box-shadow: var(--shadow-md);">
            <h2 style="margin-bottom: var(--space-md);">Send a Message</h2>
            <p style="margin-bottom: var(--space-lg); color: var(--bb-gray);">We read every message and respond as soon as we can.</p>

            <form action="php/contact-message.php" method="POST" data-ajax>
              <input type="text" name="website" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">

              <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Your name" required>
              </div>

              <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com" required>
              </div>

              <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" class="form-control" placeholder="(908) 555-1234">
              </div>

              <div class="form-group">
                <label for="subject">Subject *</label>
                <select id="subject" name="subject" class="form-control" required>
                  <option value="">Select a topic</option>
                  <option value="general">General Question</option>
                  <option value="feedback">Feedback / Compliment</option>
                  <option value="order-issue">Order Issue</option>
                  <option value="large-order">Large / Special Order</option>
                  <option value="catering">Catering Inquiry</option>
                  <option value="media">Press / Media</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message" name="message" class="form-control" placeholder="How can we help?" required></textarea>
              </div>

              <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
                <i class="fas fa-paper-plane"></i> Send Message
              </button>
            </form>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Map embed -->
  <section style="padding: 0;">
    <iframe
      src="https://www.google.com/maps?q=450+Amwell+Rd,+Hillsborough+Township,+NJ+08844&output=embed"
      width="100%"
      height="400"
      style="border:0; display: block;"
      allowfullscreen=""
      loading="lazy"
      referrerpolicy="no-referrer-when-downgrade"
      title="Bagel Bistro - 450 Amwell Rd"></iframe>
  </section>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
