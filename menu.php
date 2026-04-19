<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
  <title>Menu | Bagel Boyz NJ - Bagels, Breakfast Sandwiches, Deli in Hazlet</title>
  <meta name="description" content="Full menu for Bagel Boyz NJ. Fresh bagels, egg sandwiches with choice of meat, omelets, Boar's Head deli sandwiches, wraps, and more. Prices and items for both Hazlet locations.">
  <meta property="og:title" content="Menu | Bagel Boyz NJ">
  <meta property="og:description" content="Fresh bagels, breakfast sandwiches, omelets, deli, wraps & more. View our full menu with prices.">
  <meta property="og:image" content="img/BBLOGO.1000px.png">
  <link rel="canonical" href="https://bagelboyznj.com/menu.php">

  <!-- Menu Schema -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Menu",
    "name": "Bagel Boyz NJ Menu",
    "description": "Full breakfast and deli menu",
    "hasMenuSection": [
      {
        "@type": "MenuSection",
        "name": "Bagels",
        "hasMenuItem": [
          {"@type": "MenuItem", "name": "Single Bagel", "offers": {"@type": "Offer", "price": "1.50", "priceCurrency": "USD"}},
          {"@type": "MenuItem", "name": "Bagel with Cream Cheese", "offers": {"@type": "Offer", "price": "3.75", "priceCurrency": "USD"}},
          {"@type": "MenuItem", "name": "Dozen Bagels", "offers": {"@type": "Offer", "price": "16.95", "priceCurrency": "USD"}}
        ]
      },
      {
        "@type": "MenuSection",
        "name": "Breakfast Sandwiches",
        "hasMenuItem": [
          {"@type": "MenuItem", "name": "Egg & Cheese", "offers": {"@type": "Offer", "price": "5.99", "priceCurrency": "USD"}},
          {"@type": "MenuItem", "name": "Egg with Meat & Cheese", "offers": {"@type": "Offer", "price": "8.50", "priceCurrency": "USD"}}
        ]
      }
    ]
  }
  </script>
</head>
<body>

  <?php include 'includes/nav.php'; ?>

  <!-- Page Header -->
  <div class="page-header">
    <h1>Our Menu</h1>
    <p class="subtitle">Fresh baked, made to order, every single day</p>
  </div>

  <!-- Menu Navigation -->
  <section class="section" style="padding-bottom: 0;">
    <div class="container">
      <div class="menu-nav">
        <button class="menu-nav-btn active" data-target="bagels">Bagels</button>
        <button class="menu-nav-btn" data-target="spreads">Bagel Spreads</button>
        <button class="menu-nav-btn" data-target="breakfast">Breakfast</button>
        <button class="menu-nav-btn" data-target="omelets">Omelets</button>
        <button class="menu-nav-btn" data-target="deli">Boar's Head Deli</button>
        <button class="menu-nav-btn" data-target="salads">Salad Sandwiches</button>
        <button class="menu-nav-btn" data-target="wraps">Wraps</button>
        <button class="menu-nav-btn" data-target="specialty">Specialty</button>
        <button class="menu-nav-btn" data-target="extras">Extras</button>
        <button class="menu-nav-btn" data-target="sides">Sides</button>
        <button class="menu-nav-btn" data-target="beverages">Beverages</button>
      </div>
    </div>
  </section>

  <!-- Menu Content -->
  <section class="section">
    <div class="container">

      <!-- BAGELS -->
      <div class="menu-category" id="bagels">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#129473;</div>
          <div>
            <h2>Bagels</h2>
            <p style="margin:0; color: var(--bb-gray);">Boiled &amp; baked fresh daily, the NJ way.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel</div></div>
            <div class="menu-item-price">$1.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">&frac12; Dozen</div></div>
            <div class="menu-item-price">$8.65</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">1 Dozen</div></div>
            <div class="menu-item-price">$16.95</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Specialty Bagel</div></div>
            <div class="menu-item-price">$2.29</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Gluten Free</div></div>
            <div class="menu-item-price">$3.50</div>
          </div>
        </div>
        <div style="margin-top: var(--space-md); padding: var(--space-md); background: rgba(212,144,30,0.06); border-radius: var(--radius-md);">
          <p style="margin: 0; font-size: 0.9rem;"><strong>Varieties:</strong> Plain, Everything, Poppy, Sesame, Pumpernickel, Pumpernickel Everything, Egg, Egg Everything, Salt, Multi-Grain, Multi-Grain Everything, Garlic, Onion, Cinnamon Raisin &amp; Gluten Free</p>
        </div>
      </div>

      <!-- BAGEL SPREADS -->
      <div class="menu-category" id="spreads">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#129472;</div>
          <div>
            <h2>Bagel Spreads</h2>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Butter</div></div>
            <div class="menu-item-price">$3.25</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Cream Cheese</div></div>
            <div class="menu-item-price">$3.75</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Gourmet Cream Cheese</div></div>
            <div class="menu-item-price">$4.68</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Specialty Bagel w/ Butter</div></div>
            <div class="menu-item-price">$4.15</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Specialty Bagel w/ Cream Cheese</div></div>
            <div class="menu-item-price">$4.60</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Specialty Bagel w/ Gourmet Cream Cheese</div></div>
            <div class="menu-item-price">$5.60</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Specialty Bagel w/ Lox Spread</div></div>
            <div class="menu-item-price">$7.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Jelly</div></div>
            <div class="menu-item-price">$3.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Butter &amp; Jelly</div></div>
            <div class="menu-item-price">$3.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Peanut Butter</div></div>
            <div class="menu-item-price">$3.89</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Cream Cheese &amp; Jelly</div></div>
            <div class="menu-item-price">$3.95</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Peanut Butter &amp; Jelly</div></div>
            <div class="menu-item-price">$4.05</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Lox Spread</div></div>
            <div class="menu-item-price">$6.79</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bagel w/ Sliced Lox, Cream Cheese, L/T/O</div></div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">&mdash; Add Gourmet Specialty Cream Cheese</div>
            </div>
            <div class="menu-item-price">$11.99</div>
          </div>
        </div>
      </div>

      <!-- BREAKFAST SANDWICHES -->
      <div class="menu-category" id="breakfast">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#127869;</div>
          <div>
            <h2>Breakfast Sandwiches</h2>
            <p style="margin:0; color: var(--bb-gray);">On your choice of bagel. Roll or wrap add $0.75. Egg whites or turkey bacon add $0.99.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Eggs</div></div>
            <div class="menu-item-price">$4.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Eggs &amp; Cheese</div></div>
            <div class="menu-item-price">$5.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Eggs w/ Choice of Meat</div>
            </div>
            <div class="menu-item-price">$8.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Eggs w/ Choice of Meat &amp; Cheese</div>
            </div>
            <div class="menu-item-price">$8.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">OBO</div>
              <p class="menu-item-desc">Egg, cheese &amp; hash brown</p>
            </div>
            <div class="menu-item-price">$8.25</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">POBO</div>
              <p class="menu-item-desc">Pork roll, egg, cheese &amp; hash brown</p>
            </div>
            <div class="menu-item-price">$9.35</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">SOBO</div>
              <p class="menu-item-desc">Sausage, egg, cheese &amp; hash brown</p>
            </div>
            <div class="menu-item-price">$9.35</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">BOBO</div>
              <p class="menu-item-desc">Bacon, egg, cheese &amp; hash brown</p>
            </div>
            <div class="menu-item-price">$9.35</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">BLT</div></div>
            <div class="menu-item-price">$7.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Grilled Cheese</div></div>
            <div class="menu-item-price">$6.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Healthy Turkey</div>
              <p class="menu-item-desc">Egg whites, Oven Gold Turkey, spinach &amp; choice of cheese</p>
            </div>
            <div class="menu-item-price">$9.35</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Healthy Chicken</div>
              <p class="menu-item-desc">Egg whites, EverRoast Chicken, spinach &amp; avocado</p>
            </div>
            <div class="menu-item-price">$9.35</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Healthy Healthy</div>
              <p class="menu-item-desc">Egg whites, turkey bacon, cheese &amp; avocado</p>
            </div>
            <div class="menu-item-price">$9.35</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">RJ Special</div>
              <p class="menu-item-desc">Egg whites, turkey, pepper jack cheese, bacon &amp; spinach with chipotle mayo and hot sauce</p>
            </div>
            <div class="menu-item-price">$9.35</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Big Robby &ldquo;G&rdquo; Belly Buster</div>
              <p class="menu-item-desc">Eggs with pork roll, bacon, sausage, hash brown &amp; cheese</p>
            </div>
            <div class="menu-item-price">$12.99</div>
          </div>
        </div>
      </div>

      <!-- OMELETS -->
      <div class="menu-category" id="omelets">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#129370;</div>
          <div>
            <h2>Omelets</h2>
            <p style="margin:0; color: var(--bb-gray);">All omelets made with 3 large eggs. Served with home fries and choice of bagel or toast. Egg whites or turkey bacon add $0.99.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Eggs</div></div>
            <div class="menu-item-price">$7.79</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Eggs w/ Cheese</div></div>
            <div class="menu-item-price">$8.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Eggs w/ Choice of Meat</div></div>
            <div class="menu-item-price">$10.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">3 Eggs w/ Choice of Meat &amp; Cheese</div></div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Veggie</div>
              <p class="menu-item-desc">Onions, peppers, tomatoes &amp; spinach</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Greek</div>
              <p class="menu-item-desc">Spinach, feta, onion &amp; tomatoes</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Western</div>
              <p class="menu-item-desc">Ham, cheese, peppers &amp; onions</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Cheesesteak</div>
              <p class="menu-item-desc">Steak, cheese, peppers &amp; onions</p>
            </div>
            <div class="menu-item-price">$11.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Meat</div>
              <p class="menu-item-desc">Sausage, bacon, diced ham &amp; choice of cheese</p>
            </div>
            <div class="menu-item-price">$12.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Healthy</div>
              <p class="menu-item-desc">Egg whites, grilled chicken, sauteed spinach, melted cheese &amp; avocado</p>
            </div>
            <div class="menu-item-price">$12.99</div>
          </div>
        </div>
      </div>

      <!-- BOAR'S HEAD DELI SANDWICHES -->
      <div class="menu-category" id="deli">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#129386;</div>
          <div>
            <h2>Boar's Head Deli Sandwiches</h2>
            <p style="margin:0; color: var(--bb-gray);">Served on a bagel. Roll or wrap add $0.75.</p>
          </div>
        </div>
        <div class="menu-items" style="display: grid; grid-template-columns: 1fr;">
          <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 0; font-weight: 600; padding: 0.5rem var(--space-md); color: var(--bb-gray); font-size: 0.85rem;">
            <span></span><span style="text-align:right; min-width: 80px;">Sandwich</span><span style="text-align:right; min-width: 80px;">Per Lb</span>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Roast Beef</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$18.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Pastrami</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$18.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Pepperoni</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$14.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head EverRoast Chicken</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$14.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Blazing Buffalo Chicken</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$14.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Oven Gold Turkey</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$14.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Maple Honey Turkey</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$15.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Genoa Salami</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$14.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Deluxe Ham</div></div>
            <div class="menu-item-price">$10.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$15.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Cheese (American)</div></div>
            <div class="menu-item-price">$6.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$11.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Boar's Head Cheese (Others)</div></div>
            <div class="menu-item-price">$7.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$12.99</div>
          </div>
        </div>
      </div>

      <!-- SALAD SANDWICHES -->
      <div class="menu-category" id="salads">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#129367;</div>
          <div>
            <h2>Salad Sandwiches</h2>
            <p style="margin:0; color: var(--bb-gray);">Served on a bagel. Roll, wrap, white or wheat add $0.75. Rye bread add $1.00.</p>
          </div>
        </div>
        <div class="menu-items" style="display: grid; grid-template-columns: 1fr;">
          <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 0; font-weight: 600; padding: 0.5rem var(--space-md); color: var(--bb-gray); font-size: 0.85rem;">
            <span></span><span style="text-align:right; min-width: 80px;">Sandwich</span><span style="text-align:right; min-width: 80px;">&frac12; Lb</span>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Egg Salad</div></div>
            <div class="menu-item-price">$7.99</div>
            <div class="menu-item-price" style="min-width: 80px;">$5.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Tuna Salad</div></div>
            <div class="menu-item-price">$9.35</div>
            <div class="menu-item-price" style="min-width: 80px;">$7.99</div>
          </div>
          <div class="menu-item" style="display: grid; grid-template-columns: 1fr auto auto;">
            <div class="menu-item-info"><div class="menu-item-name">Chicken Salad</div></div>
            <div class="menu-item-price">$9.35</div>
            <div class="menu-item-price" style="min-width: 80px;">$7.99</div>
          </div>
        </div>
      </div>

      <!-- WRAPS -->
      <div class="menu-category" id="wraps">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#127790;</div>
          <div>
            <h2>Wraps &mdash; $10.99</h2>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 1: Ham &amp; Swiss</div>
              <p class="menu-item-desc">Domestic ham &amp; Swiss cheese topped with tomato, lettuce, bacon &amp; mayo</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 2: Chicken, Tuna, or Egg Salad</div>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 3: Italian</div>
              <p class="menu-item-desc">Genoa salami, capicola ham, pepperoni, provolone, tomato, lettuce, onion, oil &amp; vinegar</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 4: Turkey or Chicken</div>
              <p class="menu-item-desc">Ovengold Turkey or EverRoast Chicken w/ bacon, avocado, spinach &amp; mayo</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 5: Buffalo Chicken</div>
              <p class="menu-item-desc">Buffalo EverRoast Chicken, pepper jack cheese w/ ranch dressing</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 6: Grilled Chicken</div>
              <p class="menu-item-desc">Grilled chicken, spinach, provolone &amp; onions</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 7: Turkey Club</div>
              <p class="menu-item-desc">Ovengold Turkey, American cheese, bacon, lettuce, tomato &amp; mayo</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 8: Chicken Fajita</div>
              <p class="menu-item-desc">Onions, peppers, grilled chicken &amp; mozzarella cheese</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 9: Greek</div>
              <p class="menu-item-desc">Grilled marinated chicken, lettuce, tomato, onion &amp; feta cheese with drizzled tzatziki</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Wrap 10: Hot Roast Beef</div>
              <p class="menu-item-desc">Hot roast beef with American cheese, grilled onions &amp; grilled peppers</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
        </div>
      </div>

      <!-- SPECIALTY SANDWICHES -->
      <div class="menu-category" id="specialty">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#11088;</div>
          <div>
            <h2>Specialty Sandwiches &mdash; $10.99</h2>
            <p style="margin:0; color: var(--bb-gray);">All served on your choice of bagel. Roll or wrap add $0.75.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Bagel Boyz Sloppy</div>
              <p class="menu-item-desc">Grilled Ovengold Turkey &amp; roast beef w/ choice of cheese, cole slaw &amp; Russian dressing</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Tommy Pastrami</div>
              <p class="menu-item-desc">Pastrami w/ choice of cheese (hot or cold), cole slaw &amp; Russian dressing</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Kevin Special</div>
              <p class="menu-item-desc">Grilled chicken cutlet, bacon, pepper jack cheese, a fried egg, jalapenos with chipotle mayo</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Alex's Delight</div>
              <p class="menu-item-desc">Turkey, bacon, melted muenster cheese &amp; Russian dressing</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Veggie</div>
              <p class="menu-item-desc">Grilled onions, peppers, spinach, lettuce, tomato &amp; avocado</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Peter Style</div>
              <p class="menu-item-desc">Roast beef, muenster cheese &amp; onions with horseradish sauce</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Bagel Boyz Cheesesteak</div>
              <p class="menu-item-desc">Cheesesteak w/ peppers &amp; onions</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Noah's Italian</div>
              <p class="menu-item-desc">Genoa salami, cappy ham, pepperoni, provolone, lettuce, tomato &amp; onions, oil &amp; vinegar</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Turkey or Chicken Special</div>
              <p class="menu-item-desc">Ovengold Turkey or EverRoast Chicken w/ choice of cheese, bacon, lettuce, tomato &amp; onions</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Hot &amp; Spicy</div>
              <p class="menu-item-desc">Blazing Buffalo Chicken, pepper jack cheese, hot sliced cherry peppers &amp; jalapenos</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">The Classic</div>
              <p class="menu-item-desc">Turkey, choice of cheese w/ onions, lettuce &amp; tomato topped with oil &amp; vinegar</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Alexander the Great</div>
              <p class="menu-item-desc">Grilled turkey, melted Swiss cheese, honey mustard sauce with lettuce &amp; tomatoes</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Grand Supreme</div>
              <p class="menu-item-desc">EverRoast Chicken, Ovengold Turkey, Genoa salami, pepperoni, ham, bacon, lettuce, tomato, onions &amp; balsamic dressing</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
        </div>
      </div>

      <!-- EXTRAS -->
      <div class="menu-category" id="extras">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#10133;</div>
          <div>
            <h2>Extras</h2>
          </div>
        </div>
        <div class="menu-items" style="display: grid; grid-template-columns: 1fr 1fr;">
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Lettuce</div></div>
            <div class="menu-item-price">$0.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Hash Brown</div></div>
            <div class="menu-item-price">$1.75</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Tomato</div></div>
            <div class="menu-item-price">$0.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Spinach</div></div>
            <div class="menu-item-price">$1.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Onions</div></div>
            <div class="menu-item-price">$0.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Avocado</div></div>
            <div class="menu-item-price">$1.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Peppers</div></div>
            <div class="menu-item-price">$0.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Cheese</div></div>
            <div class="menu-item-price">$1.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Meat</div></div>
            <div class="menu-item-price">$3.00</div>
          </div>
        </div>
      </div>

      <!-- SIDES -->
      <div class="menu-category" id="sides">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#127839;</div>
          <div>
            <h2>Sides</h2>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Butter</div></div>
            <div class="menu-item-price">$0.75</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Cream Cheese</div></div>
            <div class="menu-item-price">$1.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Gourmet Cream Cheese</div></div>
            <div class="menu-item-price">$1.25</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Coleslaw</div></div>
            <div class="menu-item-price">$2.50 / &frac12; lb</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Sausage, Turkey Bacon, Bacon, or Pork Roll</div></div>
            <div class="menu-item-price">$3.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Home Fries</div></div>
            <div class="menu-item-price">$3.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">French Fries</div></div>
            <div class="menu-item-price">$3.99</div>
          </div>
        </div>
      </div>

      <!-- BEVERAGES -->
      <div class="menu-category" id="beverages">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#9749;</div>
          <div>
            <h2>Beverages</h2>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Hot Beverages</div>
              <p class="menu-item-desc">Coffee, Tea, Hot Chocolate &amp; Cappuccino &mdash; Small (12 oz) / Medium (16 oz) / Large (20 oz)</p>
            </div>
            <div class="menu-item-price">$3.00 / $3.25 / $3.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Cold Brewed Iced Coffee</div>
              <p class="menu-item-desc">24 oz</p>
            </div>
            <div class="menu-item-price">$4.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Soda, Juice, Water</div></div>
            <div class="menu-item-price">Varies</div>
          </div>
        </div>
      </div>

      <div style="margin-top: var(--space-lg); padding: var(--space-md); text-align: center; color: var(--bb-gray); font-size: 0.85rem;">
        <p>All prices subject to change without notice. No substitutions.</p>
      </div>

    </div>
  </section>

  <!-- Order CTA -->
  <section class="cta-banner">
    <div class="container">
      <h2>Ready to Order?</h2>
      <p>Skip the line and order ahead through DoorDash or Grubhub, or give us a call.</p>
      <div style="display: flex; gap: var(--space-md); justify-content: center; flex-wrap: wrap;">
        <a href="order.php" class="btn btn-white btn-lg"><i class="fas fa-shopping-bag"></i> Order Online</a>
        <a href="tel:7326464455" class="btn btn-dark btn-lg"><i class="fas fa-phone"></i> Call Holmdel Rd</a>
        <a href="tel:7323351300" class="btn btn-dark btn-lg"><i class="fas fa-phone"></i> Call Airport Plaza</a>
      </div>
    </div>
  </section>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
