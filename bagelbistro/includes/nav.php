<?php $page = basename($_SERVER['PHP_SELF'], '.php'); ?>
<nav class="navbar">
  <div class="container">
    <a href="index.php" class="nav-logo">
      <img src="img/bagelbistro-logo.png" alt="Bagel Bistro Hillsborough">
    </a>
    <div class="nav-links">
      <a href="index.php"<?= $page === 'index' ? ' class="active"' : '' ?>>Home</a>
      <a href="menu.php"<?= $page === 'menu' ? ' class="active"' : '' ?>>Menu</a>
      <a href="catering.php"<?= $page === 'catering' ? ' class="active"' : '' ?>>Catering</a>
      <a href="order.php"<?= $page === 'order' ? ' class="active"' : '' ?>>Order Online</a>
      <a href="reviews.php"<?= $page === 'reviews' ? ' class="active"' : '' ?>>Reviews</a>
      <a href="about.php"<?= $page === 'about' ? ' class="active"' : '' ?>>Our Story</a>
      <a href="careers.php"<?= $page === 'careers' ? ' class="active"' : '' ?>>Careers</a>
      <a href="contact.php"<?= $page === 'contact' ? ' class="active"' : '' ?>>Contact</a>
      <a href="order.php" class="nav-order-btn"><i class="fas fa-shopping-bag"></i> Order Now</a>
    </div>
    <button class="nav-toggle" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>
