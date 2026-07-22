/* ============================================================
   BAGEL BOYZ NJ — Online Ordering storefront
   ============================================================
   Cart lives in localStorage so a refresh doesn't lose an order.
   Prices shown here are for DISPLAY ONLY — api/order-create.php
   re-prices everything from data/menu.php before it writes the
   order, so nothing here is load-bearing for money.
   ============================================================ */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };

  var S = {
    location: null,
    menu: null,           // full /api/menu.php payload
    itemsById: {},
    groups: {},
    cart: [],
    ordering: { open: false, message: '', prep_minutes: 15, slots: [] },
    payment: { stripe_enabled: false, publishable_key: null, allow_in_store: true },
    tips: { enabled: true, presets: [0, 10, 15, 20], default: 0 },
    taxRate: 0.0625,
    honorExempt: true,
    categoryByItem: {},   // item_id -> its category (for the tax preview)
    // item sheet working state
    draft: null,
    // checkout
    pickupType: 'asap',
    tipPercent: 0,
    payMethod: 'in_store',
    stripe: null,
    elements: null,
    placing: false
  };

  var CART_KEY = 'bb_cart_v1';

  /* ==========================================================
     UTIL
     ========================================================== */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function money(cents) {
    return '$' + (Math.round(cents) / 100).toFixed(2);
  }

  function toast(msg, kind) {
    var el = $('toast');
    el.textContent = msg;
    el.className = 'toast ' + (kind || '');
    el.hidden = false;
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.hidden = true; }, 3000);
  }

  function openSheet(id)  { $(id).hidden = false; document.body.style.overflow = 'hidden'; }
  function closeSheet(id) { $(id).hidden = true;  document.body.style.overflow = ''; }

  /* ==========================================================
     CART PERSISTENCE
     ========================================================== */
  function saveCart() {
    try {
      localStorage.setItem(CART_KEY, JSON.stringify({ location: S.location, cart: S.cart }));
    } catch (e) { /* private browsing — cart just won't survive a refresh */ }
  }

  function loadCart() {
    try {
      var raw = JSON.parse(localStorage.getItem(CART_KEY) || 'null');
      if (raw && raw.cart && raw.location === S.location) S.cart = raw.cart;
    } catch (e) { S.cart = []; }
  }

  /* ==========================================================
     PRICING (display only — server is authoritative)
     ========================================================== */
  function lineUnitCents(line) {
    var item = S.itemsById[line.item_id];
    if (!item) return 0;

    var base = item.price;
    var mods = 0;

    (item.groups || []).forEach(function (gid) {
      var group = S.groups[gid];
      if (!group) return;
      // Skip groups whose show_if condition no longer holds, or a stale
      // selection from a since-hidden group would still be charged for.
      if (!groupIsActiveFor(line, gid)) return;

      var picks = line.options[gid] || [];
      var isVariant = (group.mode || 'add') === 'variant';

      picks.forEach(function (oid) {
        var opt = (group.options || []).filter(function (o) { return o.id === oid; })[0];
        if (!opt) return;
        if (isVariant) {
          if (item.variant_prices && item.variant_prices[oid] != null) base = item.variant_prices[oid];
          else if (opt.price > 0) base = opt.price;
        } else {
          mods += opt.price;
        }
      });
    });

    return base + mods;
  }

  function cartTotals() {
    var subtotal = 0, taxable = 0, count = 0;

    S.cart.forEach(function (line) {
      var unit = lineUnitCents(line);
      var lineTotal = unit * line.qty;
      subtotal += lineTotal;
      count += line.qty;

      if (!S.honorExempt || !categoryOf(line.item_id).tax_exempt) taxable += lineTotal;
    });

    var tax = Math.round(taxable * S.taxRate);
    var tip = S.tips.enabled ? Math.round(subtotal * (S.tipPercent / 100)) : 0;

    return { subtotal: subtotal, tax: tax, tip: tip, total: subtotal + tax + tip, count: count };
  }

  /**
   * Which category an item belongs to, for the tax preview.
   * `tax_exempt` now comes straight from api/menu.php, so this display
   * estimate uses the same rule bb_price_cart() applies server-side.
   * Built once per menu load rather than scanned per line.
   */
  function categoryOf(itemId) {
    return S.categoryByItem[itemId] || { tax_exempt: false };
  }

  /* ==========================================================
     LOAD MENU
     ========================================================== */
  function loadMenu(location) {
    $('menu-loading').hidden = false;
    $('menu-error').hidden = true;
    $('menu-root').innerHTML = '';

    fetch('api/menu.php?location=' + encodeURIComponent(location), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) throw new Error(data.message || 'menu failed');

        S.menu     = data;
        S.location = data.location;
        S.ordering = data.ordering;
        S.payment  = data.payment;
        S.tips     = data.tips;
        S.taxRate  = data.tax_rate;
        S.honorExempt = data.tax_honor_exempt !== false;
        S.groups   = data.modifier_groups;
        S.tipPercent = S.tips.default || 0;

        S.itemsById = {};
        S.categoryByItem = {};
        data.categories.forEach(function (c) {
          c.items.forEach(function (i) {
            S.itemsById[i.id] = i;
            S.categoryByItem[i.id] = c;
          });
        });

        loadCart();
        pruneCart();

        $('menu-loading').hidden = true;
        renderStatus();
        renderCategories();
        renderMenu();
        renderCart();
      })
      .catch(function (e) {
        console.error(e);
        $('menu-loading').hidden = true;
        $('menu-error').hidden = false;
      });
  }

  /** Drop anything that got 86'd or removed while the cart sat in storage. */
  function pruneCart() {
    var before = S.cart.length;
    S.cart = S.cart.filter(function (l) {
      var item = S.itemsById[l.item_id];
      return item && item.available;
    });
    if (S.cart.length !== before) {
      toast('Some items are no longer available and were removed.', 'err');
      saveCart();
    }
  }

  /* ==========================================================
     RENDER — status bar
     ========================================================== */
  function renderStatus() {
    var box = $('ob-status');
    var txt = box.querySelector('.ob-status-text');

    if (S.ordering.open) {
      box.className = 'ob-group ob-status is-open';
      txt.textContent = 'Open · ready in about ' + S.ordering.prep_minutes + ' min';
      $('closed-notice').hidden = true;
    } else {
      box.className = 'ob-group ob-status is-closed';
      txt.textContent = S.ordering.allow_scheduled ? 'Closed · schedule ahead' : 'Closed';
      $('closed-text').textContent = S.ordering.message;
      $('closed-notice').hidden = !S.ordering.message;
    }
  }

  function renderCategories() {
    $('cat-nav').innerHTML = S.menu.categories.map(function (c, i) {
      return '<button type="button" class="cat-btn' + (i === 0 ? ' active' : '') +
             '" data-cat="' + esc(c.id) + '">' + esc(c.name) + '</button>';
    }).join('');
  }

  function renderMenu() {
    $('menu-root').innerHTML = S.menu.categories.map(function (c) {
      var items = c.items.map(function (it) {
        var badges = '';
        if (it.popular)    badges += ' <span class="badge-pop">Popular</span>';
        if (!it.available) badges += ' <span class="badge-out">Sold Out</span>';

        return '<button type="button" class="oitem' + (it.available ? '' : ' sold-out') + '"' +
               (it.available ? ' data-item="' + esc(it.id) + '"' : ' disabled') + '>' +
                 '<span class="oitem-info">' +
                   '<span class="oitem-name">' + esc(it.name) + badges + '</span>' +
                   (it.desc ? '<span class="oitem-desc">' + esc(it.desc) + '</span>' : '') +
                 '</span>' +
                 '<span class="oitem-right">' +
                   '<span class="oitem-price">' + esc(it.price_fmt) + '</span>' +
                   (it.available ? '<span class="oitem-add"><i class="fas fa-plus"></i></span>' : '') +
                 '</span>' +
               '</button>';
      }).join('');

      return '<section class="ocat" id="cat-' + esc(c.id) + '">' +
               '<div class="ocat-head">' +
                 '<h2>' + (c.icon ? '<span>' + c.icon + '</span>' : '') + esc(c.name) + '</h2>' +
                 (c.desc ? '<p>' + esc(c.desc) + '</p>' : '') +
               '</div>' +
               '<div class="oitems">' + items + '</div>' +
             '</section>';
    }).join('');
  }

  /* ==========================================================
     ITEM SHEET
     ========================================================== */
  function openItem(itemId) {
    var item = S.itemsById[itemId];
    if (!item || !item.available) return;

    S.draft = { item_id: itemId, qty: 1, options: {}, notes: '' };

    // Preselect the first option of every required single-choice group, so a
    // customer who just wants "a bagel" can tap straight through.
    (item.groups || []).forEach(function (gid) {
      var g = S.groups[gid];
      if (g && g.min >= 1 && g.max === 1 && g.options.length) {
        S.draft.options[gid] = [g.options[0].id];
      }
    });

    $('is-name').textContent = item.name;
    $('is-desc').textContent = item.desc || '';
    $('is-desc').hidden = !item.desc;
    $('is-qty').textContent = '1';

    renderItemGroups();
    openSheet('item-sheet');
  }

  /**
   * Is this group applicable given what's selected on `line`?
   *
   * Resolves the whole show_if chain, not just one hop. The menu has a real
   * two-level chain — deli_format → deli_bread → deli_bagel_type — and a
   * single-hop check would treat a bagel type as still live on a per-pound
   * order, pricing and printing a modifier the customer can't even see.
   */
  function groupIsActiveFor(line, gid, guard) {
    var g = S.groups[gid];
    if (!g || !g.show_if) return true;

    // Cycle guard — a malformed menu must not hang the page.
    guard = guard || {};
    if (guard[gid]) return false;
    guard[gid] = true;

    var parentGid = g.show_if.group;
    if (!groupIsActiveFor(line, parentGid, guard)) return false;

    var picks = line.options[parentGid] || [];
    return picks.indexOf(g.show_if.option) !== -1;
  }

  function groupIsActive(gid) {
    return groupIsActiveFor(S.draft, gid);
  }

  /** A line's selections with inactive groups filtered out. */
  function activeOptionsFor(line) {
    var item = S.itemsById[line.item_id];
    var out = {};
    if (!item) return out;

    (item.groups || []).forEach(function (gid) {
      if (!groupIsActiveFor(line, gid)) return;
      var picks = line.options[gid];
      if (picks && picks.length) out[gid] = picks;
    });
    return out;
  }

  function renderItemGroups() {
    var item = S.itemsById[S.draft.item_id];
    var html = '';

    (item.groups || []).forEach(function (gid) {
      var g = S.groups[gid];
      if (!g || !groupIsActive(gid)) return;

      var single   = g.max === 1;
      var required = g.min >= 1;
      var picks    = S.draft.options[gid] || [];

      var rule = required
        ? '<span class="mgroup-rule required">Required</span>'
        : (g.max > 1 ? '<span class="mgroup-rule">Pick up to ' + g.max + '</span>'
                     : '<span class="mgroup-rule">Optional</span>');

      var opts = g.options.map(function (o) {
        var on = picks.indexOf(o.id) !== -1;
        var priceLabel = '';
        if ((g.mode || 'add') === 'variant') {
          var vp = (item.variant_prices && item.variant_prices[o.id] != null)
                    ? item.variant_prices[o.id] : o.price;
          if (vp) priceLabel = money(vp);
        } else if (o.price > 0) {
          priceLabel = '+' + money(o.price);
        }

        return '<label class="mopt' + (on ? ' selected' : '') + '">' +
                 '<input type="' + (single ? 'radio' : 'checkbox') + '"' +
                   ' name="g_' + esc(gid) + '"' +
                   ' data-group="' + esc(gid) + '" data-option="' + esc(o.id) + '"' +
                   (on ? ' checked' : '') + '>' +
                 '<span class="mopt-name">' + esc(o.name) + '</span>' +
                 (priceLabel ? '<span class="mopt-price">' + priceLabel + '</span>' : '') +
               '</label>';
      }).join('');

      html += '<div class="mgroup">' +
                '<div class="mgroup-head"><span class="mgroup-name">' + esc(g.name) + '</span>' + rule + '</div>' +
                '<div class="mopts">' + opts + '</div>' +
              '</div>';
    });

    html += '<div class="field item-note-field">' +
              '<span class="field-label">Special instructions</span>' +
              '<textarea id="is-notes" rows="2" placeholder="Extra crispy, cut in half, light spread…">' +
                esc(S.draft.notes) + '</textarea>' +
            '</div>';

    $('is-body').innerHTML = html;
    updateItemPrice();
  }

  function updateItemPrice() {
    var unit = lineUnitCents(S.draft);
    $('is-price').textContent = money(unit * S.draft.qty);
  }

  function toggleOption(gid, oid, checked) {
    var g = S.groups[gid];
    if (!g) return;

    // renderItemGroups rebuilds the whole panel including the notes textarea,
    // so capture what's typed first or it's lost on every option tap.
    captureNotes();

    var picks = S.draft.options[gid] || [];

    if (g.max === 1) {
      S.draft.options[gid] = checked ? [oid] : [];
    } else {
      if (checked) {
        if (g.max > 0 && picks.length >= g.max) {
          toast('You can pick up to ' + g.max + ' here.', 'err');
          renderItemGroups();
          return;
        }
        picks.push(oid);
      } else {
        picks = picks.filter(function (x) { return x !== oid; });
      }
      S.draft.options[gid] = picks;
    }

    // Clear selections in any group that just became inapplicable. Run to a
    // fixpoint: dropping deli_bread also has to drop deli_bagel_type, which
    // hangs off it — a single pass would leave the grandchild behind.
    var item = S.itemsById[S.draft.item_id];
    var changed = true;
    while (changed) {
      changed = false;
      (item.groups || []).forEach(function (otherGid) {
        if (S.draft.options[otherGid] && !groupIsActive(otherGid)) {
          delete S.draft.options[otherGid];
          changed = true;
        }
      });
    }

    renderItemGroups();
  }

  function validateDraft() {
    var item = S.itemsById[S.draft.item_id];
    var missing = [];

    (item.groups || []).forEach(function (gid) {
      var g = S.groups[gid];
      if (!g || !groupIsActive(gid)) return;
      if (g.min >= 1 && (S.draft.options[gid] || []).length < g.min) missing.push(g.name);
    });

    return missing;
  }

  /** Persist whatever is in the notes textarea into the draft. */
  function captureNotes() {
    if (!S.draft) return;
    var el = $('is-notes');
    if (el) S.draft.notes = el.value.trim().slice(0, 200);
  }

  function addDraftToCart() {
    captureNotes();

    var missing = validateDraft();
    if (missing.length) {
      toast('Please choose: ' + missing.join(', '), 'err');
      return;
    }

    // Merge with an identical existing line rather than stacking duplicates.
    var sig = signature(S.draft);
    var existing = S.cart.filter(function (l) { return signature(l) === sig; })[0];

    if (existing) {
      existing.qty = Math.min(25, existing.qty + S.draft.qty);
    } else {
      S.cart.push(JSON.parse(JSON.stringify(S.draft)));
    }

    saveCart();
    renderCart();
    closeSheet('item-sheet');
    toast(S.itemsById[S.draft.item_id].name + ' added', 'ok');
    S.draft = null;
  }

  function signature(line) {
    var keys = Object.keys(line.options).sort();
    var parts = keys.map(function (k) { return k + ':' + (line.options[k] || []).slice().sort().join('|'); });
    return line.item_id + '::' + parts.join(';') + '::' + (line.notes || '');
  }

  /* ==========================================================
     CART RENDER
     ========================================================== */
  function renderCart() {
    var t = cartTotals();
    var linesHtml, totalsHtml;

    if (!S.cart.length) {
      linesHtml = '<div class="cart-empty"><i class="fas fa-basket-shopping"></i>Nothing here yet.<br>Tap something tasty above.</div>';
      totalsHtml = '';
    } else {
      linesHtml = S.cart.map(function (line, idx) {
        var item = S.itemsById[line.item_id];
        if (!item) return '';

        // activeOptionsFor, not line.options — so a modifier from a group
        // that's no longer applicable never shows up in the cart.
        var active = activeOptionsFor(line);
        var optNames = [];
        Object.keys(active).forEach(function (gid) {
          var g = S.groups[gid];
          if (!g) return;
          active[gid].forEach(function (oid) {
            var o = g.options.filter(function (x) { return x.id === oid; })[0];
            if (o) optNames.push(o.name);
          });
        });

        return '<div class="cline">' +
                 '<span class="cline-qty">' + line.qty + '</span>' +
                 '<div class="cline-main">' +
                   '<div class="cline-name">' + esc(item.name) + '</div>' +
                   (optNames.length ? '<div class="cline-opts">' + esc(optNames.join(' · ')) + '</div>' : '') +
                   (line.notes ? '<div class="cline-note">' + esc(line.notes) + '</div>' : '') +
                 '</div>' +
                 '<div class="cline-right">' +
                   '<div class="cline-price">' + money(lineUnitCents(line) * line.qty) + '</div>' +
                   '<button type="button" class="cline-remove" data-remove="' + idx + '">Remove</button>' +
                 '</div>' +
               '</div>';
      }).join('');

      totalsHtml =
        '<div class="ctotal-row"><span>Subtotal</span><span>' + money(t.subtotal) + '</span></div>' +
        '<div class="ctotal-row"><span>Tax</span><span>' + money(t.tax) + '</span></div>' +
        (t.tip ? '<div class="ctotal-row"><span>Tip</span><span>' + money(t.tip) + '</span></div>' : '') +
        '<div class="ctotal-row grand"><span>Total</span><span>' + money(t.total) + '</span></div>';
    }

    $('cart-lines-desktop').innerHTML  = linesHtml;
    $('cart-lines-mobile').innerHTML   = linesHtml;
    $('cart-totals-desktop').innerHTML = totalsHtml;
    $('cart-totals-mobile').innerHTML  = totalsHtml;

    var canCheckout = S.cart.length > 0;
    $('btn-checkout-desktop').disabled = !canCheckout;

    var fab = $('cart-fab');
    fab.hidden = !canCheckout;
    $('fab-count').textContent = t.count;
    $('fab-total').textContent = money(t.total);
  }

  /* ==========================================================
     CHECKOUT
     ========================================================== */
  function openCheckout() {
    if (!S.cart.length) return;

    if (!S.ordering.open && !S.ordering.allow_scheduled) {
      toast(S.ordering.message || 'We\'re closed right now.', 'err');
      return;
    }

    // Closed but schedulable → force the scheduled tab.
    if (!S.ordering.open) {
      S.pickupType = 'scheduled';
    }

    var loc = (S.menu.locations || []).filter(function (l) { return l.id === S.location; })[0];
    $('co-location').textContent = loc ? ('Pickup at ' + loc.name + ' · ' + loc.address) : '';
    $('pt-asap-sub').textContent = 'Ready in ~' + S.ordering.prep_minutes + ' min';

    renderPickupToggle();
    renderSlots();
    renderTips();
    renderPayOptions();
    renderCheckoutTotals();

    $('co-error').hidden = true;
    closeSheet('cart-sheet');
    openSheet('checkout-sheet');
  }

  function renderPickupToggle() {
    Array.prototype.forEach.call(document.querySelectorAll('.pt-btn'), function (b) {
      b.classList.toggle('active', b.dataset.type === S.pickupType);
      // No ASAP when the shop is shut.
      b.disabled = (b.dataset.type === 'asap' && !S.ordering.open);
      b.style.opacity = b.disabled ? '.45' : '';
    });
    $('slot-picker').hidden = S.pickupType !== 'scheduled';
  }

  function renderSlots() {
    var days = S.ordering.slots || [];
    var daySel = $('slot-day'), timeSel = $('slot-time');

    daySel.innerHTML = days.map(function (d, i) {
      return '<option value="' + i + '">' + esc(d.label) + '</option>';
    }).join('');

    var fillTimes = function () {
      var day = days[parseInt(daySel.value || '0', 10)];
      if (!day) { timeSel.innerHTML = ''; return; }
      timeSel.innerHTML = day.slots.map(function (s) {
        return '<option value="' + esc(s.value) + '">' + esc(s.label) + '</option>';
      }).join('');
    };

    daySel.onchange = fillTimes;
    fillTimes();
  }

  function renderTips() {
    if (!S.tips.enabled) { $('tip-section').hidden = true; return; }

    var t = cartTotals();
    $('tip-row').innerHTML = (S.tips.presets || []).map(function (p) {
      var amount = Math.round(t.subtotal * (p / 100));
      return '<button type="button" class="tip-btn' + (p === S.tipPercent ? ' active' : '') + '" data-tip="' + p + '">' +
               (p === 0 ? 'No tip' : p + '%') +
               (p > 0 ? '<span class="tip-amt">' + money(amount) + '</span>' : '') +
             '</button>';
    }).join('');
  }

  function renderPayOptions() {
    var html = '';

    if (S.payment.stripe_enabled) {
      html += payOptHtml('stripe', 'fa-credit-card', 'Pay now by card', 'Fastest — just show your order number at pickup.');
    }
    if (S.payment.allow_in_store) {
      html += payOptHtml('in_store', 'fa-store', 'Pay at pickup', 'Card or cash at the counter when you grab it.');
    }

    $('pay-options').innerHTML = html;

    // Default to whatever is actually available.
    if (S.payMethod === 'stripe' && !S.payment.stripe_enabled) S.payMethod = 'in_store';
    if (S.payMethod === 'in_store' && !S.payment.allow_in_store) S.payMethod = 'stripe';

    Array.prototype.forEach.call(document.querySelectorAll('.pay-opt'), function (el) {
      var on = el.dataset.pay === S.payMethod;
      el.classList.toggle('selected', on);
      el.querySelector('input').checked = on;
    });

    if (!S.payment.stripe_enabled) {
      $('pay-note').textContent = 'Online card payment is coming soon — for now you\'ll pay when you pick up.';
    } else {
      $('pay-note').textContent = '';
    }

    syncStripeMount();
  }

  function payOptHtml(id, icon, title, sub) {
    return '<label class="pay-opt" data-pay="' + id + '">' +
             '<input type="radio" name="paymethod" value="' + id + '">' +
             '<span class="pay-opt-icon"><i class="fas ' + icon + '"></i></span>' +
             '<span class="pay-opt-main">' +
               '<span class="pay-opt-title">' + title + '</span>' +
               '<span class="pay-opt-sub">' + sub + '</span>' +
             '</span>' +
           '</label>';
  }

  function renderCheckoutTotals() {
    var t = cartTotals();
    $('co-totals').innerHTML =
      '<div class="ctotal-row"><span>Subtotal</span><span>' + money(t.subtotal) + '</span></div>' +
      '<div class="ctotal-row"><span>Tax</span><span>' + money(t.tax) + '</span></div>' +
      (t.tip ? '<div class="ctotal-row"><span>Tip</span><span>' + money(t.tip) + '</span></div>' : '') +
      '<div class="ctotal-row grand"><span>Total</span><span>' + money(t.total) + '</span></div>';
    $('co-total-btn').textContent = money(t.total);
  }

  /* ==========================================================
     STRIPE
     ----------------------------------------------------------
     The Payment Element only mounts once we have a client secret,
     which we don't get until the order row exists. So: create the
     order first (status pending_payment, invisible to the kitchen),
     then confirm the card. The webhook is what releases it.
     ========================================================== */
  /** Restore the Place Order button to its normal, usable state. */
  function resetPlaceOrderButton() {
    var btn = $('btn-place-order');
    btn.disabled = false;
    $('co-btn-label').textContent = 'Place Order';
    renderCheckoutTotals();
  }

  function syncStripeMount() {
    var mount = $('stripe-element');

    if (S.payMethod !== 'stripe') {
      mount.hidden = true;
      // Switching back to pay-at-pickup after the card form appeared must
      // re-enable the button — otherwise the customer is stuck with a dead
      // Place Order and an order row already sitting at pending_payment.
      mount.innerHTML = '';
      resetPlaceOrderButton();
      return;
    }

    mount.hidden = false;
    if (!mount.innerHTML) {
      mount.innerHTML = '<p style="color:var(--bb-gray);font-size:.88rem;">' +
        'Card details appear after you tap Place Order.</p>';
    }
  }

  function confirmStripePayment(clientSecret, publishableKey, trackUrl) {
    if (!S.stripe) S.stripe = Stripe(publishableKey);

    S.elements = S.stripe.elements({
      clientSecret: clientSecret,
      appearance: {
        theme: 'flat',
        variables: {
          colorPrimary: '#D4901E',
          colorBackground: '#FFFFFF',
          colorText: '#1A1A1A',
          borderRadius: '8px',
          fontFamily: 'Inter, -apple-system, sans-serif'
        }
      }
    });

    var mount = $('stripe-element');
    mount.innerHTML = '<div id="stripe-payment-element"></div>' +
      '<button type="button" class="btn-add" id="stripe-pay-btn" style="margin-top:1rem;">Pay & Place Order</button>';
    mount.hidden = false;

    var paymentElement = S.elements.create('payment');
    paymentElement.mount('#stripe-payment-element');

    $('btn-place-order').disabled = true;
    $('co-btn-label').textContent = 'Enter your card below ↓';

    $('stripe-pay-btn').addEventListener('click', function () {
      var btn = this;
      btn.disabled = true;
      btn.textContent = 'Processing…';

      S.stripe.confirmPayment({
        elements: S.elements,
        confirmParams: { return_url: trackUrl },
        redirect: 'if_required'
      }).then(function (result) {
        if (result.error) {
          showCheckoutError(result.error.message || 'Your card was declined.');
          btn.disabled = false;
          btn.textContent = 'Pay & Place Order';
          return;
        }
        // Paid. The webhook releases it to the kitchen; we just celebrate.
        clearCartAndConfirm(window._bbPendingOrder);
      }).catch(function () {
        showCheckoutError('Payment could not be completed. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Pay & Place Order';
      });
    });

    mount.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  /* ==========================================================
     PLACE ORDER
     ========================================================== */
  function showCheckoutError(msg) {
    var el = $('co-error');
    el.textContent = msg;
    el.hidden = false;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function placeOrder() {
    if (S.placing) return;

    var name  = $('co-name').value.trim();
    var phone = $('co-phone').value.trim();
    var email = $('co-email').value.trim();

    $('co-name').classList.toggle('invalid', name.length < 2);
    $('co-phone').classList.toggle('invalid', phone.replace(/\D/g, '').length < 10);

    if (name.length < 2)  { showCheckoutError('Please enter your name.'); return; }
    if (phone.replace(/\D/g, '').length < 10) { showCheckoutError('Please enter a valid 10-digit phone number.'); return; }
    if (email && email.indexOf('@') === -1)   { showCheckoutError('That email address doesn\'t look right.'); return; }

    var pickupAt = null;
    if (S.pickupType === 'scheduled') {
      pickupAt = $('slot-time').value;
      if (!pickupAt) { showCheckoutError('Please pick a pickup time.'); return; }
    }

    var t = cartTotals();

    var payload = {
      location: S.location,
      name: name,
      phone: phone,
      email: email,
      pickup_type: S.pickupType,
      pickup_at: pickupAt,
      notes: $('co-notes').value.trim(),
      tip_cents: t.tip,
      payment_method: S.payMethod,
      website: document.querySelector('[name="website"]').value,
      items: S.cart.map(function (l) {
        // Send only applicable groups. The server filters again anyway, but
        // there's no reason to ship a modifier the customer can't see.
        return { item_id: l.item_id, qty: l.qty, notes: l.notes, options: activeOptionsFor(l) };
      })
    };

    S.placing = true;
    var btn = $('btn-place-order');
    btn.disabled = true;
    // Only ever touch the label span — writing textContent on the button
    // itself would delete #co-total-btn, and the next tip tap would throw.
    $('co-btn-label').textContent = 'Placing your order…';
    $('co-error').hidden = true;

    withRecaptcha('place_order', function (token) {
      payload['g-recaptcha-response'] = token;

      fetch('api/order-create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          S.placing = false;

          if (!data.success) {
            showCheckoutError(data.message || 'We couldn\'t place your order.');
            resetPlaceOrderButton();
            if (data.unavailable) { loadMenu(S.location); }
            return;
          }

          window._bbPendingOrder = data;

          if (data.requires_payment) {
            confirmStripePayment(data.client_secret, data.publishable_key, data.track_url);
          } else {
            clearCartAndConfirm(data);
          }
        })
        .catch(function () {
          S.placing = false;
          showCheckoutError('Network problem. Please check your connection and try again.');
          resetPlaceOrderButton();
        });
    });
  }

  /** reCAPTCHA Enterprise is loaded site-wide; degrade gracefully if not. */
  function withRecaptcha(action, cb) {
    if (!window.grecaptcha || !window.grecaptcha.enterprise || !window.BB_RECAPTCHA_SITE_KEY) {
      cb(''); return;
    }
    try {
      grecaptcha.enterprise.ready(function () {
        grecaptcha.enterprise.execute(window.BB_RECAPTCHA_SITE_KEY, { action: action })
          .then(cb)
          .catch(function () { cb(''); });
      });
    } catch (e) { cb(''); }
  }

  function clearCartAndConfirm(data) {
    S.cart = [];
    saveCart();
    renderCart();

    closeSheet('checkout-sheet');

    $('confirm-code').textContent = data.order_code;
    $('confirm-track').href = data.track_url;
    $('confirm-when').textContent = S.pickupType === 'scheduled'
      ? 'Scheduled for pickup — see you then!'
      : 'Ready in about ' + S.ordering.prep_minutes + ' minutes.';

    openSheet('confirm-sheet');
  }

  /* ==========================================================
     WIRING
     ========================================================== */
  function boot() {
    var CFG = window.BB_ORDER || {};
    S.location = CFG.defaultLocation || null;

    // Honour a remembered location, but only if it still takes online orders —
    // otherwise a location we've since disabled would strand the customer.
    var saved = localStorage.getItem('bb_location');
    var orderable = CFG.orderableLocations || [];
    if (saved && (!orderable.length || orderable.indexOf(saved) !== -1)) {
      S.location = saved;
    }

    Array.prototype.forEach.call(document.querySelectorAll('.ob-loc'), function (b) {
      b.classList.toggle('active', b.dataset.loc === S.location);
    });

    loadMenu(S.location);

    /* ---- location switch ---- */
    $('ob-locations').addEventListener('click', function (e) {
      var btn = e.target.closest('.ob-loc');
      if (!btn || btn.dataset.loc === S.location) return;

      if (S.cart.length && !confirm('Switching locations will empty your cart. Continue?')) return;

      S.cart = [];
      S.location = btn.dataset.loc;
      localStorage.setItem('bb_location', S.location);
      saveCart();

      Array.prototype.forEach.call(document.querySelectorAll('.ob-loc'), function (b) {
        b.classList.toggle('active', b === btn);
      });
      loadMenu(S.location);
    });

    /* ---- category jump ---- */
    $('cat-nav').addEventListener('click', function (e) {
      var btn = e.target.closest('.cat-btn');
      if (!btn) return;
      Array.prototype.forEach.call(this.children, function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var target = $('cat-' + btn.dataset.cat);
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    /* ---- open an item ---- */
    $('menu-root').addEventListener('click', function (e) {
      var btn = e.target.closest('.oitem[data-item]');
      if (btn) openItem(btn.dataset.item);
    });

    /* ---- item sheet ---- */
    $('is-body').addEventListener('change', function (e) {
      var input = e.target.closest('input[data-group]');
      if (!input) return;
      toggleOption(input.dataset.group, input.dataset.option, input.checked);
    });

    $('is-minus').addEventListener('click', function () {
      if (S.draft.qty > 1) { S.draft.qty--; $('is-qty').textContent = S.draft.qty; updateItemPrice(); }
    });
    $('is-plus').addEventListener('click', function () {
      if (S.draft.qty < 25) { S.draft.qty++; $('is-qty').textContent = S.draft.qty; updateItemPrice(); }
    });
    $('is-add').addEventListener('click', addDraftToCart);

    /* ---- cart ---- */
    document.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-remove]');
      if (rm) {
        S.cart.splice(parseInt(rm.dataset.remove, 10), 1);
        saveCart(); renderCart();
        if (!S.cart.length) closeSheet('cart-sheet');
        return;
      }

      if (e.target.closest('[data-close]')) {
        var sheet = e.target.closest('.sheet');
        if (sheet) closeSheet(sheet.id);
      }
    });

    $('cart-fab').addEventListener('click', function () { renderCart(); openSheet('cart-sheet'); });
    $('btn-checkout-desktop').addEventListener('click', openCheckout);
    $('btn-checkout-mobile').addEventListener('click', openCheckout);

    /* ---- checkout controls ---- */
    $('pickup-toggle').addEventListener('click', function (e) {
      var btn = e.target.closest('.pt-btn');
      if (!btn || btn.disabled) return;
      S.pickupType = btn.dataset.type;
      renderPickupToggle();
    });

    $('tip-row').addEventListener('click', function (e) {
      var btn = e.target.closest('.tip-btn');
      if (!btn) return;
      S.tipPercent = parseInt(btn.dataset.tip, 10);
      renderTips();
      renderCheckoutTotals();
    });

    $('pay-options').addEventListener('click', function (e) {
      var opt = e.target.closest('.pay-opt');
      if (!opt) return;
      S.payMethod = opt.dataset.pay;
      renderPayOptions();
    });

    $('btn-place-order').addEventListener('click', placeOrder);

    /* ---- highlight the category you're actually looking at ---- */
    if ('IntersectionObserver' in window) {
      var obs = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var id = entry.target.id.replace('cat-', '');
          Array.prototype.forEach.call(document.querySelectorAll('.cat-btn'), function (b) {
            b.classList.toggle('active', b.dataset.cat === id);
          });
        });
      }, { rootMargin: '-160px 0px -70% 0px' });

      // Categories render async, so observe once the DOM settles.
      var wire = setInterval(function () {
        var cats = document.querySelectorAll('.ocat');
        if (!cats.length) return;
        clearInterval(wire);
        Array.prototype.forEach.call(cats, function (c) { obs.observe(c); });
      }, 300);
    }
  }

  document.addEventListener('DOMContentLoaded', boot);
})();
