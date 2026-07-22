/* ============================================================
   BAGEL BOYZ NJ — customer order tracking
   Polls /api/order-status.php and re-renders. Backs off once the
   order reaches a terminal state so a tab left open overnight
   isn't hammering the server.
   ============================================================ */
(function () {
  'use strict';

  var CFG = window.BB_TRACK || {};
  var $ = function (id) { return document.getElementById(id); };

  var POLL_MS = 8000;
  var timer = null;
  var lastStatus = null;
  var started = false;    // has the first poll been kicked off?
  var finished = false;   // terminal state or hard error — never restart

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  var STATUS_ICON = {
    pending_payment: 'fa-hourglass-half',
    new:             'fa-receipt',
    in_progress:     'fa-fire-burner',
    ready:           'fa-bag-shopping',
    completed:       'fa-circle-check',
    cancelled:       'fa-circle-xmark'
  };

  function fetchStatus() {
    $('track-refresh').classList.add('updating');

    fetch('api/order-status.php?code=' + encodeURIComponent(CFG.code) +
          '&t=' + encodeURIComponent(CFG.token), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          // Only tear the page down if we never had good data. A DB hiccup
          // mid-session shouldn't replace a live order with an error card —
          // just skip this tick and try again on the next one.
          if (!lastStatus) showError(data.message);
          return;
        }
        render(data.order);
      })
      .catch(function () {
        // A blip shouldn't wipe a page that's already showing good data.
        if (!lastStatus) showError('We couldn\'t reach the shop\'s system. Please call us.');
      })
      .finally(function () {
        $('track-refresh').classList.remove('updating');
      });
  }

  function showError(msg) {
    $('track-loading').hidden = true;
    $('track-content').hidden = true;
    $('track-error').hidden = false;
    if (msg) $('track-error-msg').textContent = msg;
    finished = true;
    stopPolling();
  }

  function render(o) {
    $('track-loading').hidden = true;
    $('track-error').hidden = true;
    $('track-content').hidden = false;

    /* ---- hero ---- */
    var hero = $('track-hero');
    hero.className = 'track-hero s-' + o.status;
    $('hero-icon').innerHTML = '<i class="fas ' + (STATUS_ICON[o.status] || 'fa-receipt') + '"></i>';
    $('hero-status').textContent = o.status_label;
    $('hero-blurb').textContent  = o.blurb || '';

    var eta = $('hero-eta');
    if (o.status === 'ready') {
      eta.hidden = false;
      eta.textContent = 'Waiting at the counter';
    } else if (o.eta_minutes !== null && o.eta_minutes !== undefined && !o.is_terminal) {
      eta.hidden = false;
      eta.textContent = o.eta_minutes <= 0
        ? 'Should be up any minute'
        : 'Ready in about ' + o.eta_minutes + ' min' + (o.pickup_label ? ' · ' + o.pickup_label : '');
    } else {
      eta.hidden = true;
    }

    /* ---- progress ---- */
    $('progress-card').hidden = !!o.is_cancelled;
    if (!o.is_cancelled) {
      $('progress-rail').innerHTML = (o.progress || []).map(function (step) {
        var cls = 'prog-step' + (step.done ? ' done' : '') + (step.current ? ' current' : '');
        var icon = step.done ? 'fa-check' : 'fa-circle';
        return '<div class="' + cls + '">' +
                 '<div class="prog-dot"><i class="fas ' + icon + '"></i></div>' +
                 '<div class="prog-label">' + esc(step.label) + '</div>' +
               '</div>';
      }).join('');
    }

    /* ---- summary ---- */
    $('t-code').textContent = o.order_code;
    $('t-pickup').textContent = o.pickup_type === 'scheduled'
      ? (o.pickup_label || 'Scheduled')
      : 'ASAP';

    $('t-items').innerHTML = (o.items || []).map(function (it) {
      var opts = (it.options || []).map(function (x) { return esc(x.name); }).join(' · ');
      return '<div class="titem">' +
               '<div class="titem-top">' +
                 '<span class="titem-name">' + it.qty + '&times; ' + esc(it.name) + '</span>' +
                 '<span>' + esc(it.price) + '</span>' +
               '</div>' +
               (opts ? '<div class="titem-opts">' + opts + '</div>' : '') +
               (it.notes ? '<div class="titem-note">Note: ' + esc(it.notes) + '</div>' : '') +
             '</div>';
    }).join('');

    var totals =
      '<div class="ctotal-row"><span>Subtotal</span><span>' + esc(o.subtotal) + '</span></div>' +
      '<div class="ctotal-row"><span>Tax</span><span>' + esc(o.tax) + '</span></div>';
    if (o.tip !== '$0.00') totals += '<div class="ctotal-row"><span>Tip</span><span>' + esc(o.tip) + '</span></div>';
    totals += '<div class="ctotal-row grand"><span>Total</span><span>' + esc(o.total) + '</span></div>';
    $('t-totals').innerHTML = totals;

    var pay = $('t-pay');
    if (o.payment_status === 'paid') {
      pay.className = 'track-pay paid';
      pay.innerHTML = '<i class="fas fa-check"></i> Paid online — nothing to pay at pickup';
    } else {
      pay.className = 'track-pay unpaid';
      pay.textContent = 'Pay at pickup: ' + o.total;
    }

    /* ---- location ---- */
    if (o.location) {
      $('t-loc-name').textContent = o.location.name || '';
      $('t-loc-addr').textContent = o.location.address || '';
      $('t-loc-map').href = 'https://maps.google.com/?q=' + encodeURIComponent(o.location.address || '');
      $('t-loc-call').href = 'tel:' + (o.location.phone || '').replace(/\D/g, '');
    }

    /* ---- celebrate the moment it flips to ready ---- */
    if (lastStatus && lastStatus !== 'ready' && o.status === 'ready') {
      if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    }
    lastStatus = o.status;

    if (o.is_terminal) {
      finished = true;
      $('track-refresh').innerHTML = o.status === 'completed'
        ? 'Thanks for coming to Bagel Boyz!'
        : 'Questions? Give us a call.';
      stopPolling();
    }
  }

  function startPolling() {
    started = true;
    fetchStatus();
    if (!timer) timer = setInterval(fetchStatus, POLL_MS);
  }

  function stopPolling() {
    if (timer) { clearInterval(timer); timer = null; }
  }

  // Don't poll a backgrounded tab; catch up the moment it's visible again.
  // Gated on `started`/`finished` rather than lastStatus — backgrounding the
  // tab before the first response landed would otherwise strand the spinner.
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      stopPolling();
    } else if (started && !finished && !timer) {
      startPolling();
    }
  });

  document.addEventListener('DOMContentLoaded', startPolling);
})();
