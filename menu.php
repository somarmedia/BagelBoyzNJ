<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
  <title>Menu | Bagel Boyz NJ - Bagels, Breakfast Sandwiches, Deli in Hazlet</title>
  <meta name="description" content="Full menu for Bagel Boyz NJ. Fresh bagels, Taylor Ham egg & cheese, omelets, Boar's Head deli sandwiches, wraps, and more. Prices and items for both Hazlet locations.">
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
          {"@type": "MenuItem", "name": "Plain Bagel", "offers": {"@type": "Offer", "price": "1.50", "priceCurrency": "USD"}},
          {"@type": "MenuItem", "name": "Bagel with Butter", "offers": {"@type": "Offer", "price": "2.50", "priceCurrency": "USD"}},
          {"@type": "MenuItem", "name": "Bagel with Cream Cheese", "offers": {"@type": "Offer", "price": "3.50", "priceCurrency": "USD"}}
        ]
      },
      {
        "@type": "MenuSection",
        "name": "Breakfast Sandwiches",
        "hasMenuItem": [
          {"@type": "MenuItem", "name": "Egg & Cheese", "offers": {"@type": "Offer", "price": "4.99", "priceCurrency": "USD"}},
          {"@type": "MenuItem", "name": "Taylor Ham, Egg & Cheese", "offers": {"@type": "Offer", "price": "6.99", "priceCurrency": "USD"}}
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
        <button class="menu-nav-btn" data-target="breakfast">Breakfast Sandwiches</button>
        <button class="menu-nav-btn" data-target="omelets">Omelets</button>
        <button class="menu-nav-btn" data-target="deli">Deli Sandwiches</button>
        <button class="menu-nav-btn" data-target="wraps">Wraps & Specialty</button>
        <button class="menu-nav-btn" data-target="sides">Sides & Extras</button>
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
            <p style="margin:0; color: var(--bb-gray);">15+ varieties baked fresh daily. Boiled &amp; baked the NJ way.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Plain Bagel</div>
            </div>
            <div class="menu-item-price">$1.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Bagel with Butter</div>
            </div>
            <div class="menu-item-price">$2.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Bagel with Cream Cheese</div>
              <p class="menu-item-desc">Choose from 13 flavors: plain, scallion, veggie, walnut raisin, jalape&ntilde;o, lox spread, olive pimento, sundried tomato &amp; more</p>
            </div>
            <div class="menu-item-price">$3.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Bagel with Lox & Cream Cheese</div>
              <p class="menu-item-desc">Premium smoked salmon with cream cheese, capers, red onion, and tomato</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Baker's Dozen</div>
              <p class="menu-item-desc">13 bagels of your choice</p>
            </div>
            <div class="menu-item-price">$14.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Half Dozen</div>
              <p class="menu-item-desc">6 bagels of your choice</p>
            </div>
            <div class="menu-item-price">$7.99</div>
          </div>
        </div>
        <div style="margin-top: var(--space-md); padding: var(--space-md); background: rgba(212,144,30,0.06); border-radius: var(--radius-md);">
          <p style="margin: 0; font-size: 0.9rem;"><strong>Bagel Varieties:</strong> Plain, Everything, Sesame, Poppy, Onion, Garlic, Salt, Pumpernickel, Egg, Cinnamon Raisin, French Toast, Multi-Grain, Jalape&ntilde;o Cheddar, Asiago, Whole Wheat &amp; Gluten-Free</p>
        </div>
      </div>

      <!-- BREAKFAST SANDWICHES -->
      <div class="menu-category" id="breakfast">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#127869;</div>
          <div>
            <h2>Breakfast Sandwiches</h2>
            <p style="margin:0; color: var(--bb-gray);">Served on your choice of bagel, roll, or bread. Add SPK for the full Jersey experience.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Egg & Cheese</div>
              <p class="menu-item-desc">On your choice of bagel, roll, or bread</p>
            </div>
            <div class="menu-item-price">$4.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Taylor Ham, Egg & Cheese</div>
              <p class="menu-item-desc">The NJ classic. Add SPK (salt, pepper, ketchup)</p>
            </div>
            <div class="menu-item-price">$6.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Bacon, Egg & Cheese</div>
              <p class="menu-item-desc">Crispy bacon with egg and melted cheese</p>
            </div>
            <div class="menu-item-price">$6.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Sausage, Egg & Cheese</div>
              <p class="menu-item-desc">Pork sausage patty with egg and cheese</p>
            </div>
            <div class="menu-item-price">$6.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Steak, Egg & Cheese</div>
              <p class="menu-item-desc">Chopped steak with egg and melted cheese</p>
            </div>
            <div class="menu-item-price">$8.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Veggie Egg & Cheese</div>
              <p class="menu-item-desc">Peppers, onions, mushrooms, tomato with egg and cheese</p>
            </div>
            <div class="menu-item-price">$7.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">The Works</div>
              <p class="menu-item-desc">Taylor ham, bacon, sausage, egg &amp; cheese. For the hungry ones.</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Avocado, Egg & Cheese</div>
              <p class="menu-item-desc">Fresh avocado with egg and cheese on your choice of bagel</p>
            </div>
            <div class="menu-item-price">$8.49</div>
          </div>
        </div>
        <div style="margin-top: var(--space-md); padding: var(--space-md); background: rgba(212,144,30,0.06); border-radius: var(--radius-md);">
          <p style="margin: 0; font-size: 0.9rem;"><strong>Add-ons:</strong> Extra Meat +$2.00 | Extra Cheese +$1.00 | Avocado +$2.00 | Home Fries +$2.50</p>
        </div>
      </div>

      <!-- OMELETS -->
      <div class="menu-category" id="omelets">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#129370;</div>
          <div>
            <h2>Omelets</h2>
            <p style="margin:0; color: var(--bb-gray);">Three-egg omelets served with home fries and toast.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Plain Cheese Omelet</div>
              <p class="menu-item-desc">Three eggs with your choice of cheese</p>
            </div>
            <div class="menu-item-price">$7.29</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Western Omelet</div>
              <p class="menu-item-desc">Ham, peppers, onions &amp; cheese</p>
            </div>
            <div class="menu-item-price">$9.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Veggie Omelet</div>
              <p class="menu-item-desc">Peppers, onions, mushrooms, tomatoes &amp; cheese</p>
            </div>
            <div class="menu-item-price">$9.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Meat Lover's Omelet</div>
              <p class="menu-item-desc">Taylor ham, bacon, sausage &amp; cheese</p>
            </div>
            <div class="menu-item-price">$11.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Greek Omelet</div>
              <p class="menu-item-desc">Spinach, tomato, feta cheese &amp; olives</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Build Your Own Omelet</div>
              <p class="menu-item-desc">Start with cheese, add up to 4 toppings</p>
            </div>
            <div class="menu-item-price">From $8.99</div>
          </div>
        </div>
      </div>

      <!-- DELI SANDWICHES -->
      <div class="menu-category" id="deli">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#129386;</div>
          <div>
            <h2>Deli Sandwiches</h2>
            <p style="margin:0; color: var(--bb-gray);">Made with Boar's Head premium meats and cheeses. Served on your choice of bread or bagel.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Turkey</div>
              <p class="menu-item-desc">Boar's Head turkey with lettuce, tomato &amp; mayo</p>
            </div>
            <div class="menu-item-price">$8.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Roast Beef</div>
              <p class="menu-item-desc">Boar's Head roast beef with lettuce, tomato &amp; horseradish</p>
            </div>
            <div class="menu-item-price">$8.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Ham & Cheese</div>
              <p class="menu-item-desc">Boar's Head ham with your choice of cheese</p>
            </div>
            <div class="menu-item-price">$7.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Italian Sub</div>
              <p class="menu-item-desc">Ham, salami, capicola, provolone, lettuce, tomato, onion, oil &amp; vinegar</p>
            </div>
            <div class="menu-item-price">$8.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Tuna Salad</div>
              <p class="menu-item-desc">Homemade tuna salad on your choice of bread</p>
            </div>
            <div class="menu-item-price">$7.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Chicken Salad</div>
              <p class="menu-item-desc">Homemade chicken salad with lettuce &amp; tomato</p>
            </div>
            <div class="menu-item-price">$7.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">BLT</div>
              <p class="menu-item-desc">Crispy bacon, lettuce, tomato &amp; mayo</p>
            </div>
            <div class="menu-item-price">$7.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Grilled Chicken</div>
              <p class="menu-item-desc">Grilled chicken breast with lettuce, tomato &amp; your choice of sauce</p>
            </div>
            <div class="menu-item-price">$8.99</div>
          </div>
        </div>
      </div>

      <!-- WRAPS & SPECIALTY -->
      <div class="menu-category" id="wraps">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#127790;</div>
          <div>
            <h2>Wraps & Specialty Sandwiches</h2>
            <p style="margin:0; color: var(--bb-gray);">Signature creations in a flour tortilla or on your choice of bread.</p>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Buffalo Chicken Wrap</div>
              <p class="menu-item-desc">Crispy chicken, buffalo sauce, lettuce, tomato &amp; blue cheese</p>
            </div>
            <div class="menu-item-price">$9.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Caesar Chicken Wrap</div>
              <p class="menu-item-desc">Grilled chicken, romaine, parmesan &amp; caesar dressing</p>
            </div>
            <div class="menu-item-price">$9.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Turkey Club Wrap</div>
              <p class="menu-item-desc">Turkey, bacon, lettuce, tomato &amp; mayo</p>
            </div>
            <div class="menu-item-price">$9.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Veggie Wrap</div>
              <p class="menu-item-desc">Hummus, avocado, roasted peppers, spinach &amp; feta</p>
            </div>
            <div class="menu-item-price">$9.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Chipotle Chicken Wrap</div>
              <p class="menu-item-desc">Grilled chicken, pepper jack, lettuce, tomato &amp; chipotle mayo</p>
            </div>
            <div class="menu-item-price">$9.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info">
              <div class="menu-item-name">Philly Cheesesteak</div>
              <p class="menu-item-desc">Chopped steak, peppers, onions &amp; melted cheese on a hero</p>
            </div>
            <div class="menu-item-price">$10.99</div>
          </div>
        </div>
      </div>

      <!-- SIDES & EXTRAS -->
      <div class="menu-category" id="sides">
        <div class="menu-category-header">
          <div class="menu-category-icon">&#127839;</div>
          <div>
            <h2>Sides & Extras</h2>
          </div>
        </div>
        <div class="menu-items">
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Home Fries</div></div>
            <div class="menu-item-price">$3.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">French Fries</div></div>
            <div class="menu-item-price">$3.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Muffin</div></div>
            <div class="menu-item-price">$2.49</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Croissant</div></div>
            <div class="menu-item-price">$2.99</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Side of Cream Cheese (4 oz)</div></div>
            <div class="menu-item-price">$2.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Cream Cheese Tub (8 oz)</div></div>
            <div class="menu-item-price">$4.99</div>
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
            <div class="menu-item-info"><div class="menu-item-name">Coffee (Regular or Decaf)</div><p class="menu-item-desc">Small / Medium / Large</p></div>
            <div class="menu-item-price">$2.00 - $3.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Iced Coffee</div></div>
            <div class="menu-item-price">$3.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Hot Tea</div></div>
            <div class="menu-item-price">$2.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Hot Chocolate</div></div>
            <div class="menu-item-price">$3.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Orange Juice</div></div>
            <div class="menu-item-price">$3.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Bottled Water</div></div>
            <div class="menu-item-price">$1.50</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Fountain Drinks</div></div>
            <div class="menu-item-price">$2.00</div>
          </div>
          <div class="menu-item">
            <div class="menu-item-info"><div class="menu-item-name">Snapple / Arizona</div></div>
            <div class="menu-item-price">$2.50</div>
          </div>
        </div>
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
