<?php
/**
 * Bagel Boyz NJ — Canonical Online Ordering Menu
 * =============================================
 * THIS FILE IS THE SINGLE SOURCE OF TRUTH FOR PRICING.
 *
 * The browser never gets to decide what anything costs. api/order-create.php
 * re-prices every submitted cart against this file before it writes an order.
 * If a price is wrong here, it is wrong everywhere — edit here and nowhere else.
 *
 * ALL MONEY IS IN INTEGER CENTS. Never floats. $1.50 => 150.
 *
 * Structure
 * ---------
 *   modifier_groups[] : reusable customization groups referenced by items
 *   categories[]      : display sections, each holding items[]
 *
 * Modifier group keys:
 *   name      Display heading
 *   min/max   How many options may be chosen (max 0 = unlimited)
 *   mode      'add'     option price ADDS to the item base price (default)
 *             'variant' option price REPLACES the item base price (sizes, per-lb)
 *   show_if   ['group'=>'x','option'=>'y'] — only offered when that option is picked
 *
 * Item keys:
 *   id/name/price/desc, groups[] (modifier group ids), popular (bool), tax_exempt (bool)
 */

return [

    /* =====================================================================
       MODIFIER GROUPS
       ---------------------------------------------------------------------
       NOTE FOR ROB & JESS: the printed menu doesn't spell out every cheese,
       cream cheese flavor, or condiment you carry. The lists below are the
       standard set — review them and delete/add to match what you actually
       stock. Prices on paid add-ons come straight off the Extras section.
       ===================================================================== */
    'modifier_groups' => [

        /* ---- Bread & bagel ------------------------------------------- */
        'bread_choice' => [
            'name' => 'Served On',
            'min' => 1, 'max' => 1,
            'options' => [
                ['id' => 'bagel',     'name' => 'Bagel',     'price' => 0],
                ['id' => 'hero_roll', 'name' => 'Hero Roll', 'price' => 75],
                ['id' => 'wrap',      'name' => 'Wrap',      'price' => 75],
                ['id' => 'toast',     'name' => 'Toast',     'price' => 0],
            ],
        ],

        'bagel_type' => [
            'name' => 'Choose Your Bagel',
            'min' => 1, 'max' => 1,
            'show_if' => ['group' => 'bread_choice', 'option' => 'bagel'],
            'options' => [
                ['id' => 'plain',            'name' => 'Plain',                  'price' => 0],
                ['id' => 'everything',       'name' => 'Everything',             'price' => 0],
                ['id' => 'poppy',            'name' => 'Poppy',                  'price' => 0],
                ['id' => 'sesame',           'name' => 'Sesame',                 'price' => 0],
                ['id' => 'pumpernickel',     'name' => 'Pumpernickel',           'price' => 0],
                ['id' => 'pump_everything',  'name' => 'Pumpernickel Everything','price' => 0],
                ['id' => 'egg',              'name' => 'Egg',                    'price' => 0],
                ['id' => 'egg_everything',   'name' => 'Egg Everything',         'price' => 0],
                ['id' => 'salt',             'name' => 'Salt',                   'price' => 0],
                ['id' => 'multigrain',       'name' => 'Multi-Grain',            'price' => 0],
                ['id' => 'multi_everything', 'name' => 'Multi-Grain Everything', 'price' => 0],
                ['id' => 'garlic',           'name' => 'Garlic',                 'price' => 0],
                ['id' => 'onion',            'name' => 'Onion',                  'price' => 0],
                ['id' => 'cinnamon_raisin',  'name' => 'Cinnamon Raisin',        'price' => 0],
                ['id' => 'gluten_free',      'name' => 'Gluten Free',            'price' => 200],
            ],
        ],

        // Standalone bagel orders (no bread swap offered — it IS the bagel).
        'bagel_type_only' => [
            'name' => 'Choose Your Bagel',
            'min' => 1, 'max' => 1,
            'options' => [
                ['id' => 'plain',            'name' => 'Plain',                  'price' => 0],
                ['id' => 'everything',       'name' => 'Everything',             'price' => 0],
                ['id' => 'poppy',            'name' => 'Poppy',                  'price' => 0],
                ['id' => 'sesame',           'name' => 'Sesame',                 'price' => 0],
                ['id' => 'pumpernickel',     'name' => 'Pumpernickel',           'price' => 0],
                ['id' => 'pump_everything',  'name' => 'Pumpernickel Everything','price' => 0],
                ['id' => 'egg',              'name' => 'Egg',                    'price' => 0],
                ['id' => 'egg_everything',   'name' => 'Egg Everything',         'price' => 0],
                ['id' => 'salt',             'name' => 'Salt',                   'price' => 0],
                ['id' => 'multigrain',       'name' => 'Multi-Grain',            'price' => 0],
                ['id' => 'multi_everything', 'name' => 'Multi-Grain Everything', 'price' => 0],
                ['id' => 'garlic',           'name' => 'Garlic',                 'price' => 0],
                ['id' => 'onion',            'name' => 'Onion',                  'price' => 0],
                ['id' => 'cinnamon_raisin',  'name' => 'Cinnamon Raisin',        'price' => 0],
                ['id' => 'gluten_free',      'name' => 'Gluten Free',            'price' => 200],
            ],
        ],

        // Dozen / half-dozen: let them mix. Free-form counts are handled in notes.
        'dozen_mix' => [
            'name' => 'Bagel Selection',
            'min' => 1, 'max' => 0,
            'options' => [
                ['id' => 'bakers_choice',    'name' => "Baker's Choice (we pick a good mix)", 'price' => 0],
                ['id' => 'plain',            'name' => 'Plain',                  'price' => 0],
                ['id' => 'everything',       'name' => 'Everything',             'price' => 0],
                ['id' => 'poppy',            'name' => 'Poppy',                  'price' => 0],
                ['id' => 'sesame',           'name' => 'Sesame',                 'price' => 0],
                ['id' => 'pumpernickel',     'name' => 'Pumpernickel',           'price' => 0],
                ['id' => 'egg',              'name' => 'Egg',                    'price' => 0],
                ['id' => 'salt',             'name' => 'Salt',                   'price' => 0],
                ['id' => 'multigrain',       'name' => 'Multi-Grain',            'price' => 0],
                ['id' => 'garlic',           'name' => 'Garlic',                 'price' => 0],
                ['id' => 'onion',            'name' => 'Onion',                  'price' => 0],
                ['id' => 'cinnamon_raisin',  'name' => 'Cinnamon Raisin',        'price' => 0],
            ],
        ],

        'toasted' => [
            'name' => 'Toasted?',
            'min' => 1, 'max' => 1,
            'options' => [
                ['id' => 'not_toasted', 'name' => 'Not Toasted',        'price' => 0],
                ['id' => 'toasted',     'name' => 'Toasted',            'price' => 0],
                ['id' => 'well_done',   'name' => 'Toasted Well Done',  'price' => 0],
            ],
        ],

        'scooped' => [
            'name' => 'Scooped?',
            'min' => 0, 'max' => 1,
            'options' => [
                ['id' => 'scooped', 'name' => 'Scoop it out', 'price' => 0],
            ],
        ],

        /* ---- Proteins & cheese --------------------------------------- */
        'meat_choice' => [
            'name' => 'Choose Your Meat',
            'min' => 1, 'max' => 1,
            'options' => [
                ['id' => 'bacon',        'name' => 'Bacon',                  'price' => 0],
                ['id' => 'sausage',      'name' => 'Sausage',                'price' => 0],
                ['id' => 'taylor_ham',   'name' => 'Taylor Ham (Pork Roll)', 'price' => 0],
                ['id' => 'ham',          'name' => 'Ham',                    'price' => 0],
                ['id' => 'turkey_bacon', 'name' => 'Turkey Bacon',           'price' => 99],
            ],
        ],

        'cheese_choice' => [
            'name' => 'Choose Your Cheese',
            'min' => 1, 'max' => 1,
            // Order matters: the storefront preselects the FIRST option of a
            // required single-choice group, so American stays the default and
            // "No Cheese" sits at the end as an opt-out. Without that opt-out
            // a customer who wants it plain can't complete the order at all.
            'options' => [
                ['id' => 'american',       'name' => 'American',       'price' => 0],
                ['id' => 'white_american', 'name' => 'White American', 'price' => 0],
                ['id' => 'swiss',          'name' => 'Swiss',          'price' => 0],
                ['id' => 'provolone',      'name' => 'Provolone',      'price' => 0],
                ['id' => 'cheddar',        'name' => 'Cheddar',        'price' => 0],
                ['id' => 'muenster',       'name' => 'Muenster',       'price' => 0],
                ['id' => 'pepper_jack',    'name' => 'Pepper Jack',    'price' => 0],
                ['id' => 'mozzarella',     'name' => 'Mozzarella',     'price' => 0],
                ['id' => 'no_cheese',      'name' => 'No Cheese',      'price' => 0],
            ],
        ],

        'egg_style' => [
            'name' => 'Egg Style',
            'min' => 1, 'max' => 1,
            'options' => [
                ['id' => 'regular',    'name' => 'Regular Eggs', 'price' => 0],
                ['id' => 'egg_whites', 'name' => 'Egg Whites',   'price' => 99],
            ],
        ],

        /* ---- Cream cheese -------------------------------------------- */
        'cream_cheese_flavor' => [
            'name' => 'Cream Cheese Flavor',
            'min' => 1, 'max' => 1,
            'options' => [
                ['id' => 'plain',     'name' => 'Plain',          'price' => 0],
                ['id' => 'scallion',  'name' => 'Scallion',       'price' => 0],
                ['id' => 'veggie',    'name' => 'Veggie',         'price' => 0],
                ['id' => 'olive',     'name' => 'Olive Pimento',  'price' => 0],
            ],
        ],

        'spread_amount' => [
            'name' => 'How Much Spread?',
            'min' => 0, 'max' => 1,
            'options' => [
                ['id' => 'light',  'name' => 'Light',       'price' => 0],
                ['id' => 'extra',  'name' => 'Extra Thick', 'price' => 0],
            ],
        ],

        /* ---- Add-ons (prices from the Extras section of the menu) ----- */
        'add_ons' => [
            'name' => 'Add Extras',
            'min' => 0, 'max' => 0,
            'options' => [
                ['id' => 'lettuce',    'name' => 'Lettuce',      'price' => 0],
                ['id' => 'tomato',     'name' => 'Tomato',       'price' => 50],
                ['id' => 'onions',     'name' => 'Onions',       'price' => 50],
                ['id' => 'peppers',    'name' => 'Peppers',      'price' => 50],
                ['id' => 'jalapenos',  'name' => 'Jalapeños',    'price' => 50],
                ['id' => 'pickles',    'name' => 'Pickles',      'price' => 50],
                ['id' => 'spinach',    'name' => 'Spinach',      'price' => 100],
                ['id' => 'avocado',    'name' => 'Avocado',      'price' => 150],
                ['id' => 'hash_brown', 'name' => 'Hash Brown',   'price' => 175],
                ['id' => 'extra_meat', 'name' => 'Extra Meat',   'price' => 250],
                ['id' => 'extra_chz',  'name' => 'Extra Cheese', 'price' => 75],
            ],
        ],

        /* ---- Condiments — all free ----------------------------------- */
        'condiments' => [
            'name' => 'Condiments',
            'min' => 0, 'max' => 0,
            'options' => [
                ['id' => 'spk',           'name' => 'SPK (Salt, Pepper, Ketchup)', 'price' => 0],
                ['id' => 'salt_pepper',   'name' => 'Salt & Pepper',   'price' => 0],
                ['id' => 'ketchup',       'name' => 'Ketchup',         'price' => 0],
                ['id' => 'mayo',          'name' => 'Mayo',            'price' => 0],
                ['id' => 'mustard',       'name' => 'Mustard',         'price' => 0],
                ['id' => 'honey_mustard', 'name' => 'Honey Mustard',   'price' => 0],
                ['id' => 'russian',       'name' => 'Russian Dressing','price' => 0],
                ['id' => 'chipotle_mayo', 'name' => 'Chipotle Mayo',   'price' => 0],
                ['id' => 'hot_sauce',     'name' => 'Hot Sauce',       'price' => 0],
                ['id' => 'oil_vinegar',   'name' => 'Oil & Vinegar',   'price' => 0],
                ['id' => 'butter',        'name' => 'Butter',          'price' => 0],
            ],
        ],

        /* ---- Deli / salad bread, only when it's actually a sandwich ----
           These mirror bread_choice + bagel_type + toasted, but hang off the
           format variant so ordering a POUND of roast beef never asks what
           bagel you want it on. ---------------------------------------- */
        'deli_bread' => [
            'name' => 'Served On',
            'min' => 1, 'max' => 1,
            'show_if' => ['group' => 'deli_format', 'option' => 'sandwich'],
            'options' => [
                ['id' => 'bagel',     'name' => 'Bagel',     'price' => 0],
                ['id' => 'hero_roll', 'name' => 'Hero Roll', 'price' => 75],
                ['id' => 'wrap',      'name' => 'Wrap',      'price' => 75],
                ['id' => 'toast',     'name' => 'Toast',     'price' => 0],
            ],
        ],

        'deli_bagel_type' => [
            'name' => 'Choose Your Bagel',
            'min' => 1, 'max' => 1,
            'show_if' => ['group' => 'deli_bread', 'option' => 'bagel'],
            'options' => [
                ['id' => 'plain',            'name' => 'Plain',                  'price' => 0],
                ['id' => 'everything',       'name' => 'Everything',             'price' => 0],
                ['id' => 'poppy',            'name' => 'Poppy',                  'price' => 0],
                ['id' => 'sesame',           'name' => 'Sesame',                 'price' => 0],
                ['id' => 'pumpernickel',     'name' => 'Pumpernickel',           'price' => 0],
                ['id' => 'pump_everything',  'name' => 'Pumpernickel Everything','price' => 0],
                ['id' => 'egg',              'name' => 'Egg',                    'price' => 0],
                ['id' => 'egg_everything',   'name' => 'Egg Everything',         'price' => 0],
                ['id' => 'salt',             'name' => 'Salt',                   'price' => 0],
                ['id' => 'multigrain',       'name' => 'Multi-Grain',            'price' => 0],
                ['id' => 'multi_everything', 'name' => 'Multi-Grain Everything', 'price' => 0],
                ['id' => 'garlic',           'name' => 'Garlic',                 'price' => 0],
                ['id' => 'onion',            'name' => 'Onion',                  'price' => 0],
                ['id' => 'cinnamon_raisin',  'name' => 'Cinnamon Raisin',        'price' => 0],
                ['id' => 'gluten_free',      'name' => 'Gluten Free',            'price' => 200],
            ],
        ],

        'deli_toasted' => [
            'name' => 'Toasted?',
            'min' => 1, 'max' => 1,
            'show_if' => ['group' => 'deli_format', 'option' => 'sandwich'],
            'options' => [
                ['id' => 'not_toasted', 'name' => 'Not Toasted',       'price' => 0],
                ['id' => 'toasted',     'name' => 'Toasted',           'price' => 0],
                ['id' => 'well_done',   'name' => 'Toasted Well Done', 'price' => 0],
            ],
        ],

        'salad_bread' => [
            'name' => 'Served On',
            'min' => 1, 'max' => 1,
            'show_if' => ['group' => 'salad_format', 'option' => 'sandwich'],
            'options' => [
                ['id' => 'bagel',     'name' => 'Bagel',     'price' => 0],
                ['id' => 'hero_roll', 'name' => 'Hero Roll', 'price' => 75],
                ['id' => 'wrap',      'name' => 'Wrap',      'price' => 75],
                ['id' => 'toast',     'name' => 'Toast',     'price' => 0],
            ],
        ],

        'salad_bagel_type' => [
            'name' => 'Choose Your Bagel',
            'min' => 1, 'max' => 1,
            'show_if' => ['group' => 'salad_bread', 'option' => 'bagel'],
            'options' => [
                ['id' => 'plain',            'name' => 'Plain',                  'price' => 0],
                ['id' => 'everything',       'name' => 'Everything',             'price' => 0],
                ['id' => 'poppy',            'name' => 'Poppy',                  'price' => 0],
                ['id' => 'sesame',           'name' => 'Sesame',                 'price' => 0],
                ['id' => 'pumpernickel',     'name' => 'Pumpernickel',           'price' => 0],
                ['id' => 'pump_everything',  'name' => 'Pumpernickel Everything','price' => 0],
                ['id' => 'egg',              'name' => 'Egg',                    'price' => 0],
                ['id' => 'egg_everything',   'name' => 'Egg Everything',         'price' => 0],
                ['id' => 'salt',             'name' => 'Salt',                   'price' => 0],
                ['id' => 'multigrain',       'name' => 'Multi-Grain',            'price' => 0],
                ['id' => 'multi_everything', 'name' => 'Multi-Grain Everything', 'price' => 0],
                ['id' => 'garlic',           'name' => 'Garlic',                 'price' => 0],
                ['id' => 'onion',            'name' => 'Onion',                  'price' => 0],
                ['id' => 'cinnamon_raisin',  'name' => 'Cinnamon Raisin',        'price' => 0],
                ['id' => 'gluten_free',      'name' => 'Gluten Free',            'price' => 200],
            ],
        ],

        'salad_toasted' => [
            'name' => 'Toasted?',
            'min' => 1, 'max' => 1,
            'show_if' => ['group' => 'salad_format', 'option' => 'sandwich'],
            'options' => [
                ['id' => 'not_toasted', 'name' => 'Not Toasted',       'price' => 0],
                ['id' => 'toasted',     'name' => 'Toasted',           'price' => 0],
                ['id' => 'well_done',   'name' => 'Toasted Well Done', 'price' => 0],
            ],
        ],

        // Deli meats: cheese is an optional add, not a required question.
        'cheese_optional' => [
            'name' => 'Add Cheese?',
            'min' => 0, 'max' => 1,
            'options' => [
                ['id' => 'american',       'name' => 'American',       'price' => 75],
                ['id' => 'white_american', 'name' => 'White American', 'price' => 75],
                ['id' => 'swiss',          'name' => 'Swiss',          'price' => 75],
                ['id' => 'provolone',      'name' => 'Provolone',      'price' => 75],
                ['id' => 'cheddar',        'name' => 'Cheddar',        'price' => 75],
                ['id' => 'muenster',       'name' => 'Muenster',       'price' => 75],
                ['id' => 'pepper_jack',    'name' => 'Pepper Jack',    'price' => 75],
                ['id' => 'mozzarella',     'name' => 'Mozzarella',     'price' => 75],
            ],
        ],

        /* ---- Variant groups: the option REPLACES the base price ------- */
        'deli_format' => [
            'name' => 'Order As',
            'min' => 1, 'max' => 1,
            'mode' => 'variant',
            'options' => [
                ['id' => 'sandwich', 'name' => 'Sandwich', 'price' => 0], // per-item override
                ['id' => 'per_lb',   'name' => 'Per Pound (sliced, no bread)', 'price' => 0],
            ],
        ],

        'salad_format' => [
            'name' => 'Order As',
            'min' => 1, 'max' => 1,
            'mode' => 'variant',
            'options' => [
                ['id' => 'sandwich', 'name' => 'Sandwich',  'price' => 0],
                ['id' => 'half_lb',  'name' => '½ lb Container', 'price' => 0],
            ],
        ],

        'hot_bev_size' => [
            'name' => 'Size',
            'min' => 1, 'max' => 1,
            'mode' => 'variant',
            'options' => [
                ['id' => 'small',  'name' => 'Small (12 oz)',  'price' => 300],
                ['id' => 'medium', 'name' => 'Medium (16 oz)', 'price' => 325],
                ['id' => 'large',  'name' => 'Large (20 oz)',  'price' => 350],
            ],
        ],

        'coffee_prep' => [
            'name' => 'How Do You Take It?',
            'min' => 0, 'max' => 0,
            'options' => [
                ['id' => 'black',        'name' => 'Black',            'price' => 0],
                ['id' => 'milk',         'name' => 'Milk',             'price' => 0],
                ['id' => 'cream',        'name' => 'Cream',            'price' => 0],
                ['id' => 'oat_milk',     'name' => 'Oat Milk',         'price' => 0],
                ['id' => 'almond_milk',  'name' => 'Almond Milk',      'price' => 0],
                ['id' => 'sugar',        'name' => 'Sugar',            'price' => 0],
                ['id' => 'sweet_n_low',  'name' => 'Sweet\'N Low',     'price' => 0],
                ['id' => 'splenda',      'name' => 'Splenda',          'price' => 0],
                ['id' => 'extra_sweet',  'name' => 'Extra Sweet',      'price' => 0],
            ],
        ],

        'hot_bev_kind' => [
            'name' => 'Which Drink?',
            'min' => 1, 'max' => 1,
            'options' => [
                ['id' => 'coffee',      'name' => 'Coffee',        'price' => 0],
                ['id' => 'decaf',       'name' => 'Decaf Coffee',  'price' => 0],
                ['id' => 'tea',         'name' => 'Tea',           'price' => 0],
                ['id' => 'hot_choc',    'name' => 'Hot Chocolate', 'price' => 0],
                ['id' => 'cappuccino',  'name' => 'Cappuccino',    'price' => 0],
            ],
        ],
    ],

    /* =====================================================================
       CATEGORIES & ITEMS
       ===================================================================== */
    'categories' => [

        /* ---------------- BAGELS ---------------- */
        [
            'id' => 'bagels', 'name' => 'Bagels', 'icon' => '🥯',
            'desc' => 'Boiled & baked fresh daily, the NJ way.',
            // NJ: unheated bakery goods sold as-is are generally sales-tax exempt.
            // CONFIRM WITH YOUR ACCOUNTANT before going live. See includes/order-config.php.
            'tax_exempt' => true,
            'items' => [
                ['id' => 'bagel_single', 'name' => 'Bagel', 'price' => 150, 'popular' => true,
                 'groups' => ['bagel_type_only', 'toasted', 'scooped']],
                ['id' => 'bagel_half_dozen', 'name' => '½ Dozen Bagels', 'price' => 865,
                 'desc' => 'Six bagels, your pick.',
                 'groups' => ['dozen_mix']],
                ['id' => 'bagel_dozen', 'name' => '1 Dozen Bagels', 'price' => 1695, 'popular' => true,
                 'desc' => 'Thirteen, honestly. We do not count great.',
                 'groups' => ['dozen_mix']],
                ['id' => 'bagel_specialty', 'name' => 'Specialty Bagel', 'price' => 229,
                 'groups' => ['toasted', 'scooped']],
                ['id' => 'bagel_gf', 'name' => 'Gluten Free Bagel', 'price' => 350,
                 'groups' => ['toasted']],
            ],
        ],

        /* ---------------- SPREADS ---------------- */
        [
            'id' => 'spreads', 'name' => 'Bagel Spreads', 'icon' => '🧈',
            'items' => [
                ['id' => 'sp_butter', 'name' => 'Bagel w/ Butter', 'price' => 280,
                 'groups' => ['bagel_type_only', 'toasted', 'scooped']],
                ['id' => 'sp_cream_cheese', 'name' => 'Bagel w/ Cream Cheese', 'price' => 360, 'popular' => true,
                 'groups' => ['bagel_type_only', 'cream_cheese_flavor', 'spread_amount', 'toasted', 'scooped']],
                ['id' => 'sp_gourmet_cc', 'name' => 'Bagel w/ Gourmet Cream Cheese', 'price' => 460,
                 'groups' => ['bagel_type_only', 'spread_amount', 'toasted', 'scooped']],
                ['id' => 'sp_spec_butter', 'name' => 'Specialty Bagel w/ Butter', 'price' => 370,
                 'groups' => ['toasted', 'scooped']],
                ['id' => 'sp_spec_cc', 'name' => 'Specialty Bagel w/ Cream Cheese', 'price' => 420,
                 'groups' => ['cream_cheese_flavor', 'spread_amount', 'toasted', 'scooped']],
                ['id' => 'sp_spec_gourmet_cc', 'name' => 'Specialty Bagel w/ Gourmet Cream Cheese', 'price' => 520,
                 'groups' => ['spread_amount', 'toasted', 'scooped']],
                ['id' => 'sp_spec_lox_spread', 'name' => 'Specialty Bagel w/ Lox Spread', 'price' => 730,
                 'groups' => ['spread_amount', 'toasted', 'scooped']],
                ['id' => 'sp_jelly', 'name' => 'Bagel w/ Jelly', 'price' => 310,
                 'groups' => ['bagel_type_only', 'toasted', 'scooped']],
                ['id' => 'sp_butter_jelly', 'name' => 'Bagel w/ Butter & Jelly', 'price' => 345,
                 'groups' => ['bagel_type_only', 'toasted', 'scooped']],
                ['id' => 'sp_pb', 'name' => 'Bagel w/ Peanut Butter', 'price' => 360,
                 'groups' => ['bagel_type_only', 'toasted', 'scooped']],
                ['id' => 'sp_cc_jelly', 'name' => 'Bagel w/ Cream Cheese & Jelly', 'price' => 380,
                 'groups' => ['bagel_type_only', 'cream_cheese_flavor', 'toasted', 'scooped']],
                ['id' => 'sp_pbj', 'name' => 'Bagel w/ Peanut Butter & Jelly', 'price' => 380,
                 'groups' => ['bagel_type_only', 'toasted', 'scooped']],
                ['id' => 'sp_lox_spread', 'name' => 'Bagel w/ Lox Spread', 'price' => 629,
                 'groups' => ['bagel_type_only', 'spread_amount', 'toasted', 'scooped']],
                ['id' => 'sp_sliced_lox', 'name' => 'Bagel w/ Sliced Lox, Cream Cheese, L/T/O', 'price' => 1099,
                 'desc' => 'Sliced nova, cream cheese, lettuce, tomato & onion.',
                 'groups' => ['bagel_type_only', 'toasted', 'scooped', 'add_ons']],
                ['id' => 'sp_sliced_lox_gourmet', 'name' => 'Sliced Lox w/ Gourmet Specialty Cream Cheese', 'price' => 1199,
                 'groups' => ['bagel_type_only', 'toasted', 'scooped', 'add_ons']],
            ],
        ],

        /* ---------------- BREAKFAST ---------------- */
        [
            'id' => 'breakfast', 'name' => 'Breakfast Sandwiches', 'icon' => '🍳',
            'desc' => 'On your choice of bagel. Roll or wrap add $0.75. Egg whites or turkey bacon add $0.99.',
            'items' => [
                ['id' => 'bf_eggs', 'name' => 'Eggs', 'price' => 499,
                 'groups' => ['bread_choice', 'bagel_type', 'egg_style', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_eggs_cheese', 'name' => 'Eggs & Cheese', 'price' => 579, 'popular' => true,
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'egg_style', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_eggs_meat', 'name' => 'Eggs w/ Choice of Meat', 'price' => 745,
                 'groups' => ['bread_choice', 'bagel_type', 'meat_choice', 'egg_style', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_eggs_meat_cheese', 'name' => 'Eggs w/ Choice of Meat & Cheese', 'price' => 795, 'popular' => true,
                 'desc' => 'The BEC / THEC. Ask for it SPK.',
                 'groups' => ['bread_choice', 'bagel_type', 'meat_choice', 'cheese_choice', 'egg_style', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_obo', 'name' => 'OBO', 'price' => 695,
                 'desc' => 'Egg, cheese & hash brown',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'egg_style', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_pobo', 'name' => 'POBO', 'price' => 935,
                 'desc' => 'Pork roll, egg, cheese & hash brown',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'egg_style', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_sobo', 'name' => 'SOBO', 'price' => 935,
                 'desc' => 'Sausage, egg, cheese & hash brown',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'egg_style', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_bobo', 'name' => 'BOBO', 'price' => 935,
                 'desc' => 'Bacon, egg, cheese & hash brown',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'egg_style', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_blt', 'name' => 'BLT', 'price' => 699,
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_grilled_cheese', 'name' => 'Grilled Cheese', 'price' => 599,
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'add_ons', 'condiments']],
                ['id' => 'bf_healthy_turkey', 'name' => 'Healthy Turkey', 'price' => 860,
                 'desc' => 'Egg whites, Oven Gold Turkey, spinach & choice of cheese',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_healthy_chicken', 'name' => 'Healthy Chicken', 'price' => 899,
                 'desc' => 'Egg whites, EverRoast Chicken, spinach & avocado',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_healthy_healthy', 'name' => 'Healthy Healthy', 'price' => 899,
                 'desc' => 'Egg whites, turkey bacon, cheese & avocado',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_rj_special', 'name' => 'RJ Special', 'price' => 899,
                 'desc' => 'Egg whites, turkey, pepper jack, bacon & spinach with chipotle mayo and hot sauce',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'bf_belly_buster', 'name' => 'Big Robby "G" Belly Buster', 'price' => 1299, 'popular' => true,
                 'desc' => 'Eggs with pork roll, bacon, sausage, hash brown & cheese',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'egg_style', 'toasted', 'add_ons', 'condiments']],
            ],
        ],

        /* ---------------- OMELETS ---------------- */
        [
            'id' => 'omelets', 'name' => 'Omelets', 'icon' => '🥘',
            'desc' => 'Made with 3 large eggs. Served with home fries and choice of bagel or toast. Egg whites or turkey bacon add $0.99.',
            'items' => [
                ['id' => 'om_eggs', 'name' => 'Eggs Omelet', 'price' => 729,
                 'groups' => ['egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_cheese', 'name' => 'Eggs w/ Cheese Omelet', 'price' => 799,
                 'groups' => ['cheese_choice', 'egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_meat', 'name' => 'Eggs w/ Choice of Meat Omelet', 'price' => 999,
                 'groups' => ['meat_choice', 'egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_meat_cheese', 'name' => '3 Eggs w/ Choice of Meat & Cheese Omelet', 'price' => 1029,
                 'groups' => ['meat_choice', 'cheese_choice', 'egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_veggie', 'name' => 'Veggie Omelet', 'price' => 1020,
                 'desc' => 'Onions, peppers, tomatoes & spinach',
                 'groups' => ['egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_greek', 'name' => 'Greek Omelet', 'price' => 1020,
                 'desc' => 'Spinach, feta, onion & tomatoes',
                 'groups' => ['egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_western', 'name' => 'Western Omelet', 'price' => 1020,
                 'desc' => 'Ham, cheese, peppers & onions',
                 'groups' => ['egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_cheesesteak', 'name' => 'Cheesesteak Omelet', 'price' => 1099,
                 'desc' => 'Steak, cheese, peppers & onions',
                 'groups' => ['egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_meat_lovers', 'name' => 'Meat Omelet', 'price' => 1299,
                 'desc' => 'Sausage, bacon, diced ham & choice of cheese',
                 'groups' => ['cheese_choice', 'egg_style', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'om_healthy', 'name' => 'Healthy Omelet', 'price' => 1299,
                 'desc' => 'Egg whites, grilled chicken, sauteed spinach, melted cheese & avocado',
                 'groups' => ['cheese_choice', 'bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
            ],
        ],

        /* ---------------- DELI ---------------- */
        [
            'id' => 'deli', 'name' => "Boar's Head Deli", 'icon' => '🥪',
            'desc' => 'Served on a bagel. Roll or wrap add $0.75. Or order sliced by the pound.',
            'items' => [
                ['id' => 'deli_roast_beef', 'name' => "Boar's Head Roast Beef", 'price' => 899,
                 'variant_prices' => ['sandwich' => 899, 'per_lb' => 1899],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_pastrami', 'name' => "Boar's Head Pastrami", 'price' => 899,
                 'variant_prices' => ['sandwich' => 899, 'per_lb' => 1899],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_pepperoni', 'name' => "Boar's Head Pepperoni", 'price' => 799,
                 'variant_prices' => ['sandwich' => 799, 'per_lb' => 1499],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_everroast', 'name' => "Boar's Head EverRoast Chicken", 'price' => 750, 'popular' => true,
                 'variant_prices' => ['sandwich' => 750, 'per_lb' => 1499],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_buffalo_chicken', 'name' => "Boar's Head Blazing Buffalo Chicken", 'price' => 750,
                 'variant_prices' => ['sandwich' => 750, 'per_lb' => 1499],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_oven_gold', 'name' => "Boar's Head Oven Gold Turkey", 'price' => 750, 'popular' => true,
                 'variant_prices' => ['sandwich' => 750, 'per_lb' => 1499],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_maple_turkey', 'name' => "Boar's Head Maple Honey Turkey", 'price' => 750,
                 'variant_prices' => ['sandwich' => 750, 'per_lb' => 1599],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_genoa', 'name' => "Boar's Head Genoa Salami", 'price' => 750,
                 'variant_prices' => ['sandwich' => 750, 'per_lb' => 1499],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_deluxe_ham', 'name' => "Boar's Head Deluxe Ham", 'price' => 750,
                 'variant_prices' => ['sandwich' => 750, 'per_lb' => 1599],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'cheese_optional', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_cheese_american', 'name' => "Boar's Head American Cheese", 'price' => 650,
                 'variant_prices' => ['sandwich' => 650, 'per_lb' => 1199],
                 'groups' => ['deli_format', 'deli_bread', 'deli_bagel_type', 'deli_toasted', 'add_ons', 'condiments']],
                ['id' => 'deli_cheese_other', 'name' => "Boar's Head Cheese (Swiss, Provolone, Muenster, Pepper Jack)", 'price' => 750,
                 'variant_prices' => ['sandwich' => 750, 'per_lb' => 1299],
                 'groups' => ['deli_format', 'cheese_choice', 'deli_bread', 'deli_bagel_type', 'deli_toasted', 'add_ons', 'condiments']],
            ],
        ],

        /* ---------------- SALAD SANDWICHES ---------------- */
        [
            'id' => 'salads', 'name' => 'Salad Sandwiches', 'icon' => '🥗',
            'desc' => 'Served on a bagel. Roll or wrap add $0.75. Or by the ½ pound.',
            'items' => [
                ['id' => 'sal_egg', 'name' => 'Egg Salad', 'price' => 699,
                 'variant_prices' => ['sandwich' => 699, 'half_lb' => 499],
                 'groups' => ['salad_format', 'salad_bread', 'salad_bagel_type', 'salad_toasted', 'add_ons', 'condiments']],
                ['id' => 'sal_tuna', 'name' => 'Tuna Salad', 'price' => 849, 'popular' => true,
                 'variant_prices' => ['sandwich' => 849, 'half_lb' => 699],
                 'groups' => ['salad_format', 'salad_bread', 'salad_bagel_type', 'salad_toasted', 'add_ons', 'condiments']],
                ['id' => 'sal_chicken', 'name' => 'Chicken Salad', 'price' => 849,
                 'variant_prices' => ['sandwich' => 849, 'half_lb' => 699],
                 'groups' => ['salad_format', 'salad_bread', 'salad_bagel_type', 'salad_toasted', 'add_ons', 'condiments']],
            ],
        ],

        /* ---------------- WRAPS ---------------- */
        [
            'id' => 'wraps', 'name' => 'Wraps', 'icon' => '🌯',
            'desc' => 'All wraps $9.99.',
            'items' => [
                ['id' => 'wr_1', 'name' => 'Wrap 1: Ham & Swiss', 'price' => 999,
                 'desc' => 'Domestic ham & Swiss topped with tomato, lettuce, bacon & mayo',
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_2', 'name' => 'Wrap 2: Chicken, Tuna, or Egg Salad', 'price' => 999,
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_3', 'name' => 'Wrap 3: Italian', 'price' => 999,
                 'desc' => 'Genoa salami, capicola ham, pepperoni, provolone, tomato, lettuce, onion, oil & vinegar',
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_4', 'name' => 'Wrap 4: Turkey or Chicken', 'price' => 999,
                 'desc' => 'Ovengold Turkey or EverRoast Chicken w/ bacon, avocado, spinach & mayo',
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_5', 'name' => 'Wrap 5: Buffalo Chicken', 'price' => 999, 'popular' => true,
                 'desc' => 'Buffalo EverRoast Chicken, pepper jack w/ ranch dressing',
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_6', 'name' => 'Wrap 6: Grilled Chicken', 'price' => 999,
                 'desc' => 'Grilled chicken, spinach, provolone & onions',
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_7', 'name' => 'Wrap 7: Turkey Club', 'price' => 999,
                 'desc' => 'Ovengold Turkey, American cheese, bacon, lettuce, tomato & mayo',
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_8', 'name' => 'Wrap 8: Chicken Fajita', 'price' => 999,
                 'desc' => 'Onions, peppers, grilled chicken & mozzarella',
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_9', 'name' => 'Wrap 9: Greek', 'price' => 999,
                 'desc' => 'Grilled marinated chicken, lettuce, tomato, onion & feta with drizzled tzatziki',
                 'groups' => ['add_ons', 'condiments']],
                ['id' => 'wr_10', 'name' => 'Wrap 10: Hot Roast Beef', 'price' => 999,
                 'desc' => 'Hot roast beef with American cheese, grilled onions & grilled peppers',
                 'groups' => ['add_ons', 'condiments']],
            ],
        ],

        /* ---------------- SPECIALTY ---------------- */
        [
            'id' => 'specialty', 'name' => 'Specialty Sandwiches', 'icon' => '⭐',
            'desc' => 'All $9.99. Served on your choice of bagel. Roll or wrap add $0.75.',
            'items' => [
                ['id' => 'spc_sloppy', 'name' => 'Bagel Boyz Sloppy', 'price' => 999, 'popular' => true,
                 'desc' => 'Grilled Ovengold Turkey & roast beef w/ choice of cheese, cole slaw & Russian dressing',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_tommy_pastrami', 'name' => 'Tommy Pastrami', 'price' => 999,
                 'desc' => 'Pastrami w/ choice of cheese (hot or cold), cole slaw & Russian dressing',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_kevin', 'name' => 'Kevin Special', 'price' => 999,
                 'desc' => 'Grilled chicken cutlet, bacon, pepper jack, a fried egg, jalapenos with chipotle mayo',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_alex_delight', 'name' => "Alex's Delight", 'price' => 999,
                 'desc' => 'Turkey, bacon, melted muenster & Russian dressing',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_veggie', 'name' => 'Veggie', 'price' => 999,
                 'desc' => 'Grilled onions, peppers, spinach, lettuce, tomato & avocado',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_peter_style', 'name' => 'Peter Style', 'price' => 999,
                 'desc' => 'Roast beef, muenster & onions with horseradish sauce',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_cheesesteak', 'name' => 'Bagel Boyz Cheesesteak', 'price' => 999, 'popular' => true,
                 'desc' => 'Cheesesteak w/ peppers & onions',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_noahs_italian', 'name' => "Noah's Italian", 'price' => 999,
                 'desc' => 'Genoa salami, cappy ham, pepperoni, provolone, lettuce, tomato & onions, oil & vinegar',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_turkey_chicken', 'name' => 'Turkey or Chicken Special', 'price' => 999,
                 'desc' => 'Ovengold Turkey or EverRoast Chicken w/ choice of cheese, bacon, lettuce, tomato & onions',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_hot_spicy', 'name' => 'Hot & Spicy', 'price' => 999,
                 'desc' => 'Blazing Buffalo Chicken, pepper jack, hot sliced cherry peppers & jalapenos',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_classic', 'name' => 'The Classic', 'price' => 999,
                 'desc' => 'Turkey, choice of cheese w/ onions, lettuce & tomato topped with oil & vinegar',
                 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_alexander', 'name' => 'Alexander the Great', 'price' => 999,
                 'desc' => 'Grilled turkey, melted Swiss, honey mustard sauce with lettuce & tomatoes',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
                ['id' => 'spc_grand_supreme', 'name' => 'Grand Supreme', 'price' => 999,
                 'desc' => 'EverRoast Chicken, Ovengold Turkey, Genoa salami, pepperoni, ham, bacon, lettuce, tomato, onions & balsamic',
                 'groups' => ['bread_choice', 'bagel_type', 'toasted', 'add_ons', 'condiments']],
            ],
        ],

        /* ---------------- SIDES ---------------- */
        [
            'id' => 'sides', 'name' => 'Sides', 'icon' => '🍟',
            'items' => [
                ['id' => 'side_butter', 'name' => 'Side of Butter', 'price' => 50],
                ['id' => 'side_cream_cheese', 'name' => 'Side of Cream Cheese', 'price' => 75,
                 'groups' => ['cream_cheese_flavor']],
                ['id' => 'side_coleslaw', 'name' => 'Coleslaw (½ lb)', 'price' => 250],
                ['id' => 'side_meat', 'name' => 'Side of Meat', 'price' => 250,
                 'desc' => 'Sausage, Turkey Bacon, Bacon, or Pork Roll',
                 'groups' => ['meat_choice']],
                ['id' => 'side_home_fries', 'name' => 'Home Fries', 'price' => 350, 'popular' => true],
                ['id' => 'side_french_fries', 'name' => 'French Fries', 'price' => 350],
                ['id' => 'side_hash_brown', 'name' => 'Hash Brown', 'price' => 175],
            ],
        ],

        /* ---------------- BEVERAGES ---------------- */
        [
            'id' => 'beverages', 'name' => 'Beverages', 'icon' => '☕',
            'items' => [
                ['id' => 'bev_hot', 'name' => 'Hot Beverage', 'price' => 300, 'popular' => true,
                 'desc' => 'Coffee, Decaf, Tea, Hot Chocolate & Cappuccino',
                 'groups' => ['hot_bev_kind', 'hot_bev_size', 'coffee_prep']],
                ['id' => 'bev_cold_brew', 'name' => 'Cold Brewed Iced Coffee (24 oz)', 'price' => 450, 'popular' => true,
                 'groups' => ['coffee_prep']],
                ['id' => 'bev_bottled_water', 'name' => 'Bottled Water', 'price' => 200],
                ['id' => 'bev_soda', 'name' => 'Soda', 'price' => 250,
                 'desc' => 'Ask at pickup for what we have cold today.'],
                ['id' => 'bev_juice', 'name' => 'Juice', 'price' => 300,
                 'desc' => 'Orange, apple, cranberry.'],
            ],
        ],
    ],
];
