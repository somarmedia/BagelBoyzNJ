<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
  <title>Careers | Bagel Bistro Hillsborough - Join the Team</title>
  <meta name="description" content="Join the Bagel Bistro team in Hillsborough, NJ. Hiring counter, line, deli, and prep positions. Flexible schedules, morning shifts, great tips.">
  <meta property="og:title" content="Careers | Bagel Bistro Hillsborough">
  <meta property="og:description" content="Hiring for counter, line, deli, and prep positions at 450 Amwell Rd in Hillsborough.">
  <link rel="canonical" href="https://bagelboyznj.com/bagelbistro/careers.php">

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "JobPosting",
    "title": "Food Service Team Member",
    "description": "Bagel Bistro is hiring counter, line cook, deli, and prep team members. Morning shifts, flexible schedules, tip pool. Join a busy neighborhood bagel shop in Hillsborough, NJ.",
    "datePosted": "<?= date('Y-m-d') ?>",
    "validThrough": "<?= date('Y-m-d', strtotime('+90 days')) ?>",
    "employmentType": ["PART_TIME", "FULL_TIME"],
    "hiringOrganization": {
      "@type": "Organization",
      "name": "Bagel Bistro",
      "sameAs": "https://bagelboyznj.com/bagelbistro/"
    },
    "jobLocation": {
      "@type": "Place",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "450 Amwell Rd",
        "addressLocality": "Hillsborough Township",
        "addressRegion": "NJ",
        "postalCode": "08844",
        "addressCountry": "US"
      }
    }
  }
  </script>
</head>
<body>

  <?php include 'includes/nav.php'; ?>

  <!-- Page Header -->
  <div class="page-header">
    <h1>Join the Team</h1>
    <p class="subtitle">We're hiring. Come meet Jimmy.</p>
  </div>

  <!-- Why Work Here -->
  <section class="section">
    <div class="container">
      <div class="text-center">
        <span class="section-label">Work at Bagel Bistro</span>
        <h2 class="section-title">Morning Shifts, Fast-Paced, Good Crew</h2>
        <p class="section-subtitle">We open at 6:30 AM and wrap by 2 PM. That's your afternoons and evenings back — perfect for students, parents, side gigs, or anyone who likes a morning routine.</p>
      </div>

      <div class="benefits-grid">
        <div class="benefit-item"><i class="fas fa-sun"></i> Morning shifts only</div>
        <div class="benefit-item"><i class="fas fa-clock"></i> Off by 2 PM</div>
        <div class="benefit-item"><i class="fas fa-dollar-sign"></i> Competitive pay + tips</div>
        <div class="benefit-item"><i class="fas fa-utensils"></i> Free shift meals</div>
        <div class="benefit-item"><i class="fas fa-users"></i> Friendly, local crew</div>
        <div class="benefit-item"><i class="fas fa-calendar"></i> Flexible scheduling</div>
      </div>
    </div>
  </section>

  <!-- Open Roles -->
  <section class="section section-dark">
    <div class="container">
      <div class="text-center">
        <span class="section-label" style="color: var(--bb-red-light);">What We're Hiring For</span>
        <h2 class="section-title">Open Positions</h2>
      </div>

      <div class="features-grid">
        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(200,16,46,0.2);">
          <div class="feature-icon" style="background: rgba(200,16,46,0.15);"><i class="fas fa-cash-register" style="color: var(--bb-red-light);"></i></div>
          <h3 style="color: var(--bb-cream);">Counter / Cashier</h3>
          <p style="color: var(--bb-cream-dark);">Take orders, run the register, help the breakfast rush fly. Friendly attitude required. No experience necessary — we'll train you.</p>
        </div>

        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(200,16,46,0.2);">
          <div class="feature-icon" style="background: rgba(200,16,46,0.15);"><i class="fas fa-fire" style="color: var(--bb-red-light);"></i></div>
          <h3 style="color: var(--bb-cream);">Line Cook / Griddle</h3>
          <p style="color: var(--bb-cream-dark);">Eggs, Taylor Ham, omelets, pancakes, burgers. Fast-paced morning line. Some kitchen experience preferred.</p>
        </div>

        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(200,16,46,0.2);">
          <div class="feature-icon" style="background: rgba(200,16,46,0.15);"><i class="fas fa-medal" style="color: var(--bb-red-light);"></i></div>
          <h3 style="color: var(--bb-cream);">Deli / Sandwich Maker</h3>
          <p style="color: var(--bb-cream-dark);">Run the Boar's Head counter. Build heroes, paninis, clubs. Attention to detail and a clean station matter.</p>
        </div>

        <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(200,16,46,0.2);">
          <div class="feature-icon" style="background: rgba(200,16,46,0.15);"><i class="fas fa-bread-slice" style="color: var(--bb-red-light);"></i></div>
          <h3 style="color: var(--bb-cream);">Prep / Baker Assistant</h3>
          <p style="color: var(--bb-cream-dark);">Early mornings, bagel prep, cream cheese mixing, keeping the shop stocked. Great for someone who likes the quiet pre-open hours.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Application Form -->
  <section class="section" id="apply">
    <div class="container" style="max-width: 700px;">
      <div class="text-center">
        <span class="section-label">Apply</span>
        <h2 class="section-title">Application Form</h2>
        <p class="section-subtitle">Fill this out and Jimmy or a manager will reach out. Prefer to apply in person? Stop by 450 Amwell Rd and ask for the manager.</p>
      </div>

      <form action="php/careers-apply.php" method="POST" data-ajax>
        <input type="text" name="website" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">

        <div class="form-row">
          <div class="form-group">
            <label for="name">Full Name *</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="Your name" required>
          </div>
          <div class="form-group">
            <label for="phone">Phone Number *</label>
            <input type="tel" id="phone" name="phone" class="form-control" placeholder="(908) 555-1234" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com">
          </div>
          <div class="form-group">
            <label for="age">Age *</label>
            <input type="number" id="age" name="age" class="form-control" placeholder="18" min="14" max="99" required>
          </div>
        </div>

        <div class="form-group">
          <label for="position">Position *</label>
          <select id="position" name="position" class="form-control" required>
            <option value="">Select a position</option>
            <option value="counter">Counter / Cashier</option>
            <option value="line">Line Cook / Griddle</option>
            <option value="deli">Deli / Sandwich Maker</option>
            <option value="prep">Prep / Baker Assistant</option>
            <option value="anything">Anything Available</option>
          </select>
        </div>

        <div class="form-group">
          <label for="availability">Availability *</label>
          <select id="availability" name="availability" class="form-control" required>
            <option value="">Select availability</option>
            <option value="full-time">Full Time (5-6 days)</option>
            <option value="part-time">Part Time (3-4 days)</option>
            <option value="weekends">Weekends Only</option>
            <option value="weekdays">Weekdays Only</option>
            <option value="flexible">Flexible</option>
          </select>
        </div>

        <div class="form-group">
          <label for="start_date">Earliest Start Date</label>
          <input type="date" id="start_date" name="start_date" class="form-control">
        </div>

        <div class="form-group">
          <label for="experience">Relevant Experience</label>
          <textarea id="experience" name="experience" class="form-control" placeholder="Previous restaurants, cafés, delis, or food service work. Not required, just helpful."></textarea>
        </div>

        <!-- Hidden — required by existing handler -->
        <input type="hidden" name="preferred_location" value="450 Amwell Rd, Hillsborough">

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
          <i class="fas fa-paper-plane"></i> Submit Application
        </button>
      </form>
    </div>
  </section>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
