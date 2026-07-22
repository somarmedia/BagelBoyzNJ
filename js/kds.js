/* ============================================================
   BAGEL BOYZ NJ — Kitchen Display
   ============================================================
   Shared hosting can't hold a socket open, so this short-polls
   /kds/api/poll.php. The server hands back `new_order_ids`; any
   id in there that we haven't seen fires the alarm, and the alarm
   keeps repeating until a human taps GOT IT.
   ============================================================ */
(function () {
  'use strict';

  var CFG = window.BB_KDS || {};
  var $  = function (id) { return document.getElementById(id); };

  var state = {
    cursor: 0,
    orders: [],
    seen: {},            // order ids already announced
    primed: false,       // has the first poll of this session completed?
    pending: 0,          // unacknowledged new orders
    alerting: false,
    soundReady: false,
    location: CFG.location,
    printerIp: localStorage.getItem('bb_printer_ip') || '',
    autoPrint: localStorage.getItem('bb_autoprint') !== '0',
    lastOkPoll: Date.now(),
    openOrderId: null,
    menu: null
  };

  /* ==========================================================
     SOUND  —  a rising two-tone chime plus a spoken sentence.
     iOS blocks both until the page has had a real user gesture,
     which is what the Start Shift button is for.
     ========================================================== */
  var audioCtx = null;

  function initAudio() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (Ctx && !audioCtx) audioCtx = new Ctx();
      if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();

      // Speaking an empty utterance here is what actually unlocks
      // speechSynthesis on iOS — without it the first real announcement
      // is silently swallowed.
      if ('speechSynthesis' in window) {
        var warm = new SpeechSynthesisUtterance(' ');
        warm.volume = 0;
        window.speechSynthesis.speak(warm);
      }
      state.soundReady = true;
    } catch (e) {
      console.warn('Audio unlock failed', e);
    }
  }

  function chime() {
    if (!audioCtx) return;
    if (audioCtx.state === 'suspended') audioCtx.resume();

    [0, 0.18].forEach(function (offset, i) {
      var osc  = audioCtx.createOscillator();
      var gain = audioCtx.createGain();
      osc.type = 'sine';
      osc.frequency.value = i === 0 ? 784 : 1046;   // G5 → C6
      var t = audioCtx.currentTime + offset;
      gain.gain.setValueAtTime(0.0001, t);
      gain.gain.exponentialRampToValueAtTime(0.35, t + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.42);
      osc.connect(gain); gain.connect(audioCtx.destination);
      osc.start(t); osc.stop(t + 0.45);
    });
  }

  function speak(text) {
    if (!('speechSynthesis' in window)) return;
    try {
      window.speechSynthesis.cancel();
      var u = new SpeechSynthesisUtterance(text);
      u.rate = 1.0; u.pitch = 1.0; u.volume = 1.0;
      window.speechSynthesis.speak(u);
    } catch (e) { /* speech is a bonus, never a requirement */ }
  }

  /* ==========================================================
     THE ALARM
     ========================================================== */
  var alertTimer = null;

  function startAlert(count) {
    state.pending = count;
    $('alert-count').textContent = count;
    $('alert-bar').hidden = false;

    if (state.alerting) return;      // already going; just updated the count
    state.alerting = true;

    fireAlert();
    alertTimer = setInterval(fireAlert, Math.max(3, CFG.alertRepeat || 6) * 1000);
  }

  function fireAlert() {
    chime();
    var n = state.pending;
    var text = n > 1
      ? 'You have ' + n + ' new orders'
      : (CFG.alertText || 'You have a new order');
    // Say it twice per cycle — one pass is easy to miss over a slicer.
    speak(text + '. ' + text);

    if (navigator.vibrate) navigator.vibrate([220, 90, 220]);
  }

  function stopAlert() {
    state.alerting = false;
    state.pending  = 0;
    if (alertTimer) { clearInterval(alertTimer); alertTimer = null; }
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    $('alert-bar').hidden = true;
  }

  /* ==========================================================
     NETWORK
     ========================================================== */
  function api(url, body) {
    return fetch(url, {
      method: body ? 'POST' : 'GET',
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (r) {
      return r.json().then(function (data) {
        if (r.status === 401 && data.signed_out) {
          location.reload();
          throw new Error('signed out');
        }
        return data;
      });
    });
  }

  function toast(message, kind) {
    var el = $('toast');
    el.textContent = message;
    el.className = 'toast ' + (kind || '');
    el.hidden = false;
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.hidden = true; }, 2800);
  }

  /* ==========================================================
     POLL LOOP
     ========================================================== */
  function poll() {
    api('api/poll.php?since=' + state.cursor)
      .then(function (data) {
        if (!data.success) return;

        state.lastOkPoll = Date.now();
        state.orders = data.orders || [];

        applyCounts(data.counts);
        $('topbar-loc').textContent   = data.location_name || '';
        $('topbar-time').textContent  = data.server_time || '';

        renderStoreState(data.store);

        // The first poll of a session primes `seen` without shouting about
        // orders that were already on the board before this iPad woke up.
        //
        // This deliberately does NOT use the server's `primed` flag. The
        // server derives it from `since === 0`, but the cursor only advances
        // off zero once an order exists — so on an empty board (i.e. every
        // morning at open) `primed` stays true forever and the first real
        // order of the day would never set off the alarm.
        var isFirstPoll = !state.primed;
        var announce = [];

        (data.new_order_ids || []).forEach(function (id) {
          if (!state.seen[id]) {
            state.seen[id] = true;
            if (!isFirstPoll) announce.push(id);
          }
        });

        state.primed = true;
        state.cursor = data.cursor || state.cursor;

        render();

        if (announce.length) {
          startAlert(state.pending + announce.length);
          // One drain per new order — each call claims the next queued job.
          if (state.autoPrint) {
            announce.forEach(function () { drainPrintQueue(); });
          }
        }
      })
      .catch(function (e) {
        if (e && e.message === 'signed out') return;
        console.warn('poll failed', e);
      })
      .finally(function () {
        updateConnDot();
        setTimeout(poll, Math.max(2, CFG.pollSeconds || 5) * 1000);
      });
  }

  function updateConnDot() {
    var age = Date.now() - state.lastOkPoll;
    var dot = $('conn-dot');
    dot.className = 'conn-dot' + (age > 45000 ? ' dead' : age > 20000 ? ' stale' : '');
    dot.title = age > 20000 ? 'Connection problem — check the wifi' : 'Connected';
  }

  /* ==========================================================
     RENDER
     ========================================================== */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function renderStoreState(store) {
    if (!store) return;
    var banner = $('pause-banner');

    if (!store.accepting) {
      $('pause-text').textContent = 'Online orders are turned OFF.';
      banner.hidden = false;
    } else if (store.pause_until) {
      var until = new Date(store.pause_until.replace(' ', 'T'));
      if (until > new Date()) {
        $('pause-text').textContent =
          (store.pause_reason || 'Paused') + ' until ' +
          until.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        banner.hidden = false;
      } else { banner.hidden = true; }
    } else {
      banner.hidden = true;
    }

    $('set-accepting').checked = !!store.accepting;
    Array.prototype.forEach.call(document.querySelectorAll('#prep-chips .chip'), function (c) {
      c.classList.toggle('active', parseInt(c.dataset.prep, 10) === store.prep_minutes);
    });
  }

  function render() {
    var wrap = $('cards');
    var orders = state.orders;

    $('empty-state').hidden = orders.length > 0;
    wrap.innerHTML = orders.map(cardHtml).join('');

    // Re-open the detail modal against fresh data if one is showing.
    if (state.openOrderId) {
      var still = orders.filter(function (o) { return o.id === state.openOrderId; })[0];
      if (still) renderDetail(still); else closeModal('order-modal');
    }
  }

  function cardHtml(o) {
    var MAX_LINES = 4;
    var items = o.items.slice(0, MAX_LINES).map(function (it) {
      var opts = it.options.map(function (x) { return esc(x.name); }).join(' · ');
      return '<div class="card-item">' +
               '<span class="card-item-qty">' + it.qty + '</span>' +
               '<span class="card-item-name">' + esc(it.name) + '</span>' +
               (opts ? '<div class="card-item-opts">' + opts + '</div>' : '') +
               (it.notes ? '<div class="card-item-note">! ' + esc(it.notes) + '</div>' : '') +
             '</div>';
    }).join('');

    if (o.items.length > MAX_LINES) {
      items += '<div class="card-more">+ ' + (o.items.length - MAX_LINES) + ' more — tap to see all</div>';
    }

    var ageClass = o.is_late ? 'late' : (o.is_due ? 'due' : '');
    var ageText  = o.pickup_type === 'scheduled'
      ? o.pickup_label
      : (o.age_minutes < 1 ? 'just now' : o.age_minutes + 'm ago');

    var flags = '';
    if (o.is_test) flags += '<span class="flag flag-test">Test Order</span>';
    flags += o.payment_status === 'paid'
      ? '<span class="flag flag-paid">Paid</span>'
      : '<span class="flag flag-unpaid">Collect ' + esc(o.total) + '</span>';
    if (o.order_notes) flags += '<span class="flag flag-note">Note</span>';
    if (o.print_status === 'failed') flags += '<span class="flag flag-print">Print failed</span>';

    var actions = '';
    if (o.status === 'new') {
      actions = '<button class="card-btn primary-start" data-act="in_progress" data-id="' + o.id + '">' +
                '<i class="fas fa-play"></i> Start</button>';
    } else if (o.status === 'in_progress') {
      actions = '<button class="card-btn primary-ready" data-act="ready" data-id="' + o.id + '">' +
                '<i class="fas fa-check"></i> Ready</button>';
    } else if (o.status === 'ready') {
      actions = '<button class="card-btn primary-done" data-act="completed" data-id="' + o.id + '">' +
                '<i class="fas fa-bag-shopping"></i> Picked Up</button>';
    }
    actions += '<button class="card-btn ghost" data-act="open" data-id="' + o.id + '"><i class="fas fa-ellipsis"></i></button>';

    return '<div class="card s-' + o.status + (o.is_late ? ' is-late' : '') + '">' +
      '<div class="card-head" data-act="open" data-id="' + o.id + '">' +
        '<div><div class="card-code">' + esc(o.order_code) + '</div>' +
        '<div class="card-when">' + (o.pickup_type === 'scheduled' ? 'Scheduled' : 'ASAP') + '</div></div>' +
        '<span class="card-age ' + ageClass + '">' + esc(ageText) + '</span>' +
      '</div>' +
      '<div class="card-customer" data-act="open" data-id="' + o.id + '">' +
        '<strong>' + esc(o.customer_name) + '</strong><span>' + esc(o.customer_phone) + '</span>' +
      '</div>' +
      '<div class="card-items" data-act="open" data-id="' + o.id + '">' + items + '</div>' +
      (flags ? '<div class="card-flags">' + flags + '</div>' : '') +
      '<div class="card-actions">' + actions + '</div>' +
    '</div>';
  }

  /* ==========================================================
     ORDER DETAIL
     ========================================================== */
  function openOrder(id) {
    var o = state.orders.filter(function (x) { return x.id === id; })[0];
    if (!o) return;
    state.openOrderId = id;
    renderDetail(o);
    $('order-modal').hidden = false;
  }

  function renderDetail(o) {
    $('m-code').textContent = o.order_code;
    $('m-sub').textContent  = o.customer_name + ' · ' + o.customer_phone;

    var html = '';
    html += '<div class="detail-row"><span class="k">Status</span><span>' + esc(o.status_label) + '</span></div>';
    html += '<div class="detail-row"><span class="k">Pickup</span><span>' +
            (o.pickup_type === 'scheduled' ? esc(o.pickup_label || '') : 'ASAP') + '</span></div>';
    html += '<div class="detail-row"><span class="k">Payment</span><span>' +
            (o.payment_status === 'paid' ? 'Paid online' : 'COLLECT ' + esc(o.total)) + '</span></div>';

    if (o.order_notes) {
      html += '<div class="d-note">Order note: ' + esc(o.order_notes) + '</div>';
    }

    html += '<div class="detail-sep"></div>';

    html += o.items.map(function (it) {
      var opts = it.options.map(function (x) {
        return '<span class="d-opt">' + esc(x.name) + '</span>';
      }).join('');
      return '<div class="d-item">' +
        '<div class="d-item-top"><span>' + it.qty + '&times; ' + esc(it.name) + '</span>' +
        '<span>' + esc(it.price) + '</span></div>' +
        (opts ? '<div class="d-opts">' + opts + '</div>' : '') +
        (it.notes ? '<div class="d-note">' + esc(it.notes) + '</div>' : '') +
      '</div>';
    }).join('');

    html += '<div class="detail-sep"></div>';
    html += '<div class="detail-row"><span class="k">Subtotal</span><span>' + esc(o.subtotal) + '</span></div>';
    html += '<div class="detail-row"><span class="k">Tax</span><span>' + esc(o.tax) + '</span></div>';
    if (o.tip !== '$0.00') html += '<div class="detail-row"><span class="k">Tip</span><span>' + esc(o.tip) + '</span></div>';
    html += '<div class="detail-row"><span class="k"><strong>Total</strong></span><span><strong>' + esc(o.total) + '</strong></span></div>';

    $('m-body').innerHTML = html;

    var foot = '';
    if (o.status === 'new')         foot += '<button class="btn-fill-amber" data-act="in_progress" data-id="' + o.id + '">Start Making</button>';
    if (o.status === 'in_progress') foot += '<button class="btn-fill-green" data-act="ready" data-id="' + o.id + '">Mark Ready</button>';
    if (o.status === 'ready')       foot += '<button class="btn-fill-blue"  data-act="completed" data-id="' + o.id + '">Picked Up</button>';
    foot += '<button class="btn-outline" data-act="print" data-id="' + o.id + '"><i class="fas fa-print"></i></button>';
    foot += '<button class="btn-danger"  data-act="cancel" data-id="' + o.id + '"><i class="fas fa-trash"></i></button>';
    $('m-foot').innerHTML = foot;
  }

  /* ==========================================================
     ACTIONS
     ========================================================== */
  function setStatus(id, status) {
    var note = null;
    if (status === 'cancelled') {
      note = prompt('Cancelling this order. Why? (the customer sees nothing, this is for your records)');
      if (note === null) return;
    }

    api('api/update.php', { action: 'status', order_id: id, status: status, note: note })
      .then(function (r) {
        if (!r.success) { toast(r.message || 'Could not update', 'err'); return; }
        toast(r.message, 'ok');
        poll_now();
      })
      .catch(function () { toast('Network problem — try again', 'err'); });
  }

  function poll_now() {
    setTimeout(function () {
      api('api/poll.php?since=' + state.cursor).then(function (data) {
        if (data.success) {
          state.orders = data.orders || [];
          applyCounts(data.counts);
          render();
        }
      }).catch(function () {});
    }, 150);
  }

  /**
   * Counts come straight from the server, which is the only authority on
   * what's still waiting. Deciding when to silence the alarm from the
   * client's stale `state.orders` copy raced badly: tapping two new orders
   * in quick succession left it blaring until someone hit GOT IT.
   */
  function applyCounts(counts) {
    $('count-new').textContent    = counts.new;
    $('count-making').textContent = counts.in_progress;
    $('count-ready').textContent  = counts.ready;

    if (counts.new === 0 && state.alerting) stopAlert();
  }

  /* ==========================================================
     PRINTING
     ----------------------------------------------------------
     Star TSP100III speaks WebPRNT: the iPad POSTs the ticket XML
     straight to the printer's IP on the shop network. If that
     can't work (no IP set, or Safari blocking HTTP from an HTTPS
     page) we fall back to AirPrint, which always works but needs
     someone to tap through the print sheet.
     ========================================================== */
  /**
   * Pull the next QUEUED job and print it.
   *
   * Called with no order id on purpose — that's what makes print/webprnt.php
   * claim from bb_print_jobs and hand back a job_token, so the job can be
   * retired afterwards. Passing an order id takes the reprint path instead,
   * which never touches the queue and would leave rows stuck at 'queued'.
   */
  function drainPrintQueue() {
    if (!state.printerIp) return Promise.resolve();
    return sendTicket('../print/webprnt.php');
  }

  /** Explicit (re)print of one known order. Does not consume the queue. */
  function printViaWebPRNT(orderId) {
    if (!state.printerIp) return Promise.resolve();
    return sendTicket('../print/webprnt.php?order_id=' + orderId);
  }

  function sendTicket(url) {
    return api(url).then(function (data) {
      if (!data.success || data.empty) return;

      return pushToPrinter(data.xml).then(function () {
        if (data.job_token) {
          api('api/update.php', { action: 'print_done', job_token: data.job_token, ok: true });
        }
      }).catch(function (err) {
        console.warn('WebPRNT push failed', err);
        if (data.job_token) {
          api('api/update.php', { action: 'print_done', job_token: data.job_token, ok: false });
        }
        toast('Printer did not respond — use the print button to AirPrint', 'err');
      });
    }).catch(function () {});
  }

  function pushToPrinter(xml) {
    var endpoint = 'http://' + state.printerIp + '/StarWebPRNT/SendMessage';
    var body = new URLSearchParams();
    body.set('requestType', 'application/json');
    body.set('request', xml);

    return fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      // The printer doesn't send CORS headers, so we can't read the reply.
      // 'no-cors' still delivers the POST, which is all we need.
      mode: 'no-cors'
    });
  }

  /** AirPrint fallback — render the ticket into a hidden div and print it. */
  function printViaAirPrint(orderId) {
    api('api/update.php', { action: 'ticket', order_id: orderId })
      .then(function (r) {
        if (!r.success) { toast('Could not build the ticket', 'err'); return; }
        $('print-area').innerHTML = r.html;
        setTimeout(function () { window.print(); }, 60);
      })
      .catch(function () { toast('Could not build the ticket', 'err'); });
  }

  function handlePrint(orderId) {
    if (state.printerIp) {
      printViaWebPRNT(orderId);
      toast('Sent to printer', 'ok');
    } else {
      printViaAirPrint(orderId);
    }
  }

  /* ==========================================================
     86 BOARD
     ========================================================== */
  function open86() {
    $('eightysix-modal').hidden = false;
    if (state.menu) { render86(); return; }

    fetch('../api/menu.php?location=' + encodeURIComponent(state.location), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) return;
        state.menu = data.categories;
        render86();
      })
      .catch(function () { toast('Could not load the menu', 'err'); });
  }

  function render86() {
    var term = ($('86-search').value || '').toLowerCase().trim();
    var html = '';

    (state.menu || []).forEach(function (cat) {
      var items = cat.items.filter(function (it) {
        return !term || it.name.toLowerCase().indexOf(term) !== -1;
      });
      if (!items.length) return;

      html += '<div class="eightysix-cat">' + esc(cat.name) + '</div>';
      items.forEach(function (it) {
        html += '<div class="eightysix-item' + (it.available ? '' : ' is-off') + '">' +
                  '<span class="e86-name">' + esc(it.name) + '</span>' +
                  '<button class="e86-toggle" data-item="' + esc(it.id) + '" data-avail="' + (it.available ? '0' : '1') + '">' +
                    (it.available ? '86 IT' : 'BACK ON') +
                  '</button>' +
                '</div>';
      });
    });

    $('86-list').innerHTML = html || '<p style="color:var(--k-text-dim);padding:20px 0;">Nothing matches that.</p>';
  }

  function toggle86(itemId, makeAvailable) {
    api('api/update.php', { action: 'availability', item_id: itemId, available: makeAvailable })
      .then(function (r) {
        if (!r.success) { toast(r.message || 'Could not update', 'err'); return; }
        toast(r.message, 'ok');
        (state.menu || []).forEach(function (cat) {
          cat.items.forEach(function (it) {
            if (it.id === itemId) it.available = r.available;
          });
        });
        render86();
      })
      .catch(function () { toast('Network problem', 'err'); });
  }

  /* ==========================================================
     LOGIN
     ========================================================== */
  var pin = '', loginLoc = null;

  function renderPin() {
    Array.prototype.forEach.call(document.querySelectorAll('.pin-dot'), function (d, i) {
      d.classList.toggle('filled', i < pin.length);
    });
  }

  function submitPin() {
    api('api/auth.php', {
      action: 'login', pin: pin, location: loginLoc,
      device: (navigator.platform || 'iPad')
    }).then(function (r) {
      if (!r.success) {
        $('login-error').textContent = r.message || 'Wrong PIN.';
        $('login-error').hidden = false;
        pin = ''; renderPin();
        if (navigator.vibrate) navigator.vibrate(200);
        return;
      }
      state.location = r.location;
      $('login-screen').hidden = true;
      $('app').hidden = false;
      $('sound-gate').hidden = false;
    }).catch(function () {
      $('login-error').textContent = 'Could not sign in. Check the wifi.';
      $('login-error').hidden = false;
      pin = ''; renderPin();
    });
  }

  /* ==========================================================
     WIRING
     ========================================================== */
  function closeModal(id) {
    $(id).hidden = true;
    if (id === 'order-modal') state.openOrderId = null;
  }

  function boot() {
    /* ---- login keypad ---- */
    var picker = $('loc-picker');
    if (picker) {
      var firstBtn = picker.querySelector('.loc-btn');
      loginLoc = firstBtn ? firstBtn.dataset.loc : null;

      picker.addEventListener('click', function (e) {
        var btn = e.target.closest('.loc-btn');
        if (!btn) return;
        Array.prototype.forEach.call(picker.children, function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        loginLoc = btn.dataset.loc;
      });
    }

    var keypad = $('keypad');
    if (keypad) {
      keypad.addEventListener('click', function (e) {
        var key = e.target.closest('.key');
        if (!key) return;
        var k = key.dataset.key;

        $('login-error').hidden = true;
        if (k === 'clear')      pin = '';
        else if (k === 'back')  pin = pin.slice(0, -1);
        else if (pin.length < 8) pin += k;

        renderPin();
        if (pin.length === 4) setTimeout(submitPin, 120);
      });
    }

    /* ---- sound gate ---- */
    $('enable-sound').addEventListener('click', function () {
      initAudio();
      $('sound-gate').hidden = true;
      chime();
      start();
    });

    /* ---- alarm ack ---- */
    $('alert-ack').addEventListener('click', stopAlert);

    /* ---- delegated card + modal actions ---- */
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-act]');
      if (el) {
        var act = el.dataset.act;
        var id  = parseInt(el.dataset.id, 10);

        if (act === 'open')   { openOrder(id); return; }
        if (act === 'print')  { handlePrint(id); return; }
        if (act === 'cancel') { setStatus(id, 'cancelled'); closeModal('order-modal'); return; }
        if (['in_progress', 'ready', 'completed'].indexOf(act) !== -1) {
          setStatus(id, act);
          if (!$('order-modal').hidden) closeModal('order-modal');
          return;
        }
      }

      if (e.target.closest('[data-close]')) {
        var modal = e.target.closest('.modal');
        if (modal) closeModal(modal.id);
      }

      var toggle = e.target.closest('.e86-toggle');
      if (toggle) toggle86(toggle.dataset.item, toggle.dataset.avail === '1');
    });

    /* ---- header buttons ---- */
    $('btn-86').addEventListener('click', open86);
    $('btn-settings').addEventListener('click', function () { $('settings-modal').hidden = false; });
    $('86-search').addEventListener('input', render86);

    $('btn-resume').addEventListener('click', function () {
      api('api/update.php', { action: 'store', accepting: 1, pause_minutes: 0 })
        .then(function (r) {
          if (!r.success) { toast(r.message || 'Could not resume', 'err'); return; }
          toast('Taking orders again', 'ok');
          renderStoreState(r.store);
          poll_now();
        })
        .catch(function () { toast('Network problem — try again', 'err'); });
    });

    /* ---- settings ---- */
    $('set-accepting').addEventListener('change', function () {
      api('api/update.php', { action: 'store', accepting: this.checked ? 1 : 0 })
        .then(function (r) { if (r.success) toast(r.store.accepting ? 'Taking orders' : 'Orders turned off', 'ok'); });
    });

    $('prep-chips').addEventListener('click', function (e) {
      var chip = e.target.closest('.chip');
      if (!chip) return;
      api('api/update.php', { action: 'store', prep_minutes: parseInt(chip.dataset.prep, 10) })
        .then(function (r) { if (r.success) { toast('Wait time set to ' + r.store.prep_minutes + ' min', 'ok'); renderStoreState(r.store); } });
    });

    $('pause-chips').addEventListener('click', function (e) {
      var chip = e.target.closest('.chip');
      if (!chip) return;
      var mins = parseInt(chip.dataset.pause, 10);
      api('api/update.php', { action: 'store', pause_minutes: mins, pause_reason: 'We\'re slammed right now' })
        .then(function (r) {
          if (r.success) { toast(mins ? 'Paused for ' + mins + ' min' : 'Taking orders again', 'ok'); renderStoreState(r.store); }
        });
    });

    var ipInput = $('set-printer-ip');
    ipInput.value = state.printerIp;
    ipInput.addEventListener('change', function () {
      state.printerIp = this.value.trim();
      localStorage.setItem('bb_printer_ip', state.printerIp);
      $('print-note').textContent = state.printerIp
        ? 'Saved. Tickets will print automatically.'
        : 'No printer set — the print button will use AirPrint instead.';
      $('print-note').className = 'setting-note ok';
    });

    $('set-autoprint').checked = state.autoPrint;
    $('set-autoprint').addEventListener('change', function () {
      state.autoPrint = this.checked;
      localStorage.setItem('bb_autoprint', this.checked ? '1' : '0');
    });

    $('btn-test-print').addEventListener('click', function () {
      var newest = state.orders[0];
      if (!newest) { toast('No order on the board to test with', 'err'); return; }
      handlePrint(newest.id);
    });

    $('btn-test-sound').addEventListener('click', function () {
      if (!state.soundReady) initAudio();
      chime();
      speak(CFG.alertText || 'You have a new order');
    });

    $('btn-signout').addEventListener('click', function () {
      if (!confirm('Sign this iPad out of the kitchen display?')) return;
      api('api/auth.php', { action: 'logout' }).then(function () { location.reload(); });
    });

    /* ---- keep the screen awake if the browser allows it ---- */
    if ('wakeLock' in navigator) {
      var lock = null;
      var acquire = function () {
        navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {});
      };
      acquire();
      document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && !lock) acquire();
      });
    }

    /* ---- already signed in? just ask for the sound tap ---- */
    if (CFG.signedIn) {
      $('sound-gate').hidden = false;
    }
  }

  function start() {
    poll();
    setInterval(updateConnDot, 5000);
  }

  document.addEventListener('DOMContentLoaded', boot);
})();
