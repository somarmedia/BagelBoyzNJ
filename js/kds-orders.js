/* ============================================================
   BAGEL BOYZ NJ — Order history
   Everything the live board drops once an order is handed over.
   ============================================================ */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };

  var state = { from: null, to: null, status: 'all', q: '', page: 1, lastPayload: null };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function toast(msg, kind) {
    var el = $('toast');
    el.textContent = msg;
    el.className = 'toast ' + (kind || '');
    el.hidden = false;
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.hidden = true; }, 2800);
  }

  function api(url, body) {
    return fetch(url, {
      method: body ? 'POST' : 'GET',
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (r) {
      return r.json().then(function (d) {
        if (r.status === 401 && d.signed_out) { location.href = 'index.php'; throw new Error('signed out'); }
        return d;
      });
    });
  }

  /* ---- local YYYY-MM-DD, not UTC (toISOString would shift the day) ---- */
  function ymd(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth() + 1).padStart(2, '0') + '-' +
           String(d.getDate()).padStart(2, '0');
  }

  function setRange(kind) {
    var now = new Date();
    if (kind === 'today') {
      state.from = state.to = ymd(now);
    } else if (kind === 'yesterday') {
      var y = new Date(now); y.setDate(y.getDate() - 1);
      state.from = state.to = ymd(y);
    } else {
      var days = parseInt(kind, 10);
      var back = new Date(now); back.setDate(back.getDate() - (days - 1));
      state.from = ymd(back);
      state.to   = ymd(now);
    }
    $('f-from').value = state.from;
    $('f-to').value   = state.to;
  }

  /* ==========================================================
     LOAD
     ========================================================== */
  function load() {
    var qs = 'view=list' +
      '&from=' + encodeURIComponent(state.from) +
      '&to=' + encodeURIComponent(state.to) +
      '&status=' + encodeURIComponent(state.status) +
      '&q=' + encodeURIComponent(state.q) +
      '&page=' + state.page;

    api('api/orders.php?' + qs)
      .then(function (data) {
        if (!data.success) { toast(data.message || 'Could not load', 'err'); return; }
        state.lastPayload = data;
        $('topbar-loc').textContent = data.location_name || '';
        renderTotals(data.totals, data.paging);
        renderList(data.orders);
        renderPaging(data.paging);
      })
      .catch(function (e) {
        if (e && e.message === 'signed out') return;
        toast('Network problem', 'err');
      });
  }

  function renderTotals(t, paging) {
    $('ord-totals').innerHTML =
      '<div class="ord-tot"><span class="ord-tot-n">' + t.count + '</span><span class="ord-tot-l">Orders</span></div>' +
      '<div class="ord-tot"><span class="ord-tot-n">' + esc(t.gross) + '</span><span class="ord-tot-l">Gross</span></div>' +
      '<div class="ord-tot"><span class="ord-tot-n">' + esc(t.subtotal) + '</span><span class="ord-tot-l">Subtotal</span></div>' +
      '<div class="ord-tot"><span class="ord-tot-n">' + esc(t.tax) + '</span><span class="ord-tot-l">Tax</span></div>' +
      '<div class="ord-tot"><span class="ord-tot-n">' + esc(t.tips) + '</span><span class="ord-tot-l">Tips</span></div>' +
      '<div class="ord-tot-note">Totals exclude cancelled and test orders' +
        (paging.matched !== t.count ? ' (' + paging.matched + ' rows shown)' : '') + '</div>';
  }

  function renderList(orders) {
    $('ord-empty').hidden = orders.length > 0;

    var lastDate = null;
    var html = '';

    orders.forEach(function (o) {
      if (o.date !== lastDate) {
        html += '<div class="ord-daybreak">' + esc(o.date) + '</div>';
        lastDate = o.date;
      }

      var badges = '';
      if (o.is_test) badges += '<span class="flag flag-test">Test</span>';
      badges += o.payment_status === 'paid'
        ? '<span class="flag flag-paid">Paid</span>'
        : '<span class="flag flag-unpaid">' + (o.status === 'cancelled' ? 'Unpaid' : 'Collect') + '</span>';

      html += '<button type="button" class="ord-row s-' + o.status + '" data-id="' + o.id + '">' +
                '<span class="ord-time">' + esc(o.time) + '</span>' +
                '<span class="ord-main">' +
                  '<span class="ord-code">' + esc(o.order_code) + '</span>' +
                  '<span class="ord-cust">' + esc(o.customer_name) + ' &middot; ' + o.item_count + ' item' + (o.item_count === 1 ? '' : 's') + '</span>' +
                  '<span class="ord-badges">' + badges + '</span>' +
                '</span>' +
                '<span class="ord-right">' +
                  '<span class="ord-total">' + esc(o.total) + '</span>' +
                  '<span class="ord-status">' + esc(o.status_label) + '</span>' +
                '</span>' +
              '</button>';
    });

    $('ord-list').innerHTML = html;
  }

  function renderPaging(p) {
    if (p.pages <= 1) { $('ord-paging').innerHTML = ''; return; }
    $('ord-paging').innerHTML =
      '<button type="button" class="chip" data-page="' + (p.page - 1) + '"' + (p.page <= 1 ? ' disabled' : '') + '>Previous</button>' +
      '<span class="ord-page-label">Page ' + p.page + ' of ' + p.pages + '</span>' +
      '<button type="button" class="chip" data-page="' + (p.page + 1) + '"' + (p.page >= p.pages ? ' disabled' : '') + '>Next</button>';
  }

  /* ==========================================================
     DETAIL
     ========================================================== */
  function openDetail(id) {
    api('api/orders.php?view=detail&id=' + id)
      .then(function (data) {
        if (!data.success) { toast(data.message || 'Not found', 'err'); return; }
        var o = data.order;

        $('d-code').textContent = o.order_code + (o.is_test ? '  (TEST)' : '');
        $('d-sub').textContent  = o.placed_at + ' · ' + o.customer_name + ' · ' + o.customer_phone;
        $('d-reprint').dataset.id = o.id;

        var h = '';
        h += '<div class="detail-row"><span class="k">Status</span><span>' + esc(o.status_label) + '</span></div>';
        h += '<div class="detail-row"><span class="k">Pickup</span><span>' +
             (o.pickup_type === 'scheduled' ? 'Scheduled' : 'ASAP') + '</span></div>';
        h += '<div class="detail-row"><span class="k">Payment</span><span>' +
             (o.payment_status === 'paid' ? 'Paid online' : 'At pickup') + ' (' + esc(o.payment_method) + ')</span></div>';
        if (o.customer_email) {
          h += '<div class="detail-row"><span class="k">Email</span><span>' + esc(o.customer_email) + '</span></div>';
        }
        if (o.fulfilment_minutes != null) {
          h += '<div class="detail-row"><span class="k">Took</span><span>' + o.fulfilment_minutes + ' min</span></div>';
        }
        if (o.cancel_reason) {
          h += '<div class="d-note">Cancelled: ' + esc(o.cancel_reason) + '</div>';
        }
        if (o.order_notes) {
          h += '<div class="d-note">Order note: ' + esc(o.order_notes) + '</div>';
        }

        h += '<div class="detail-sep"></div>';
        h += o.items.map(function (it) {
          var opts = (it.options || []).map(function (x) {
            return '<span class="d-opt">' + esc(x.name) + '</span>';
          }).join('');
          return '<div class="d-item">' +
                   '<div class="d-item-top"><span>' + it.qty + '&times; ' + esc(it.name) + '</span>' +
                   '<span>' + esc(it.price) + '</span></div>' +
                   (opts ? '<div class="d-opts">' + opts + '</div>' : '') +
                   (it.notes ? '<div class="d-note">' + esc(it.notes) + '</div>' : '') +
                 '</div>';
        }).join('');

        h += '<div class="detail-sep"></div>';
        h += '<div class="detail-row"><span class="k">Subtotal</span><span>' + esc(o.subtotal) + '</span></div>';
        h += '<div class="detail-row"><span class="k">Tax</span><span>' + esc(o.tax) + '</span></div>';
        if (o.tip !== '$0.00') h += '<div class="detail-row"><span class="k">Tip</span><span>' + esc(o.tip) + '</span></div>';
        h += '<div class="detail-row"><span class="k"><strong>Total</strong></span><span><strong>' + esc(o.total) + '</strong></span></div>';

        if (o.timeline && o.timeline.length) {
          h += '<div class="detail-sep"></div><div class="ord-tl-title">History</div>';
          h += '<div class="ord-tl">' + o.timeline.map(function (t) {
            return '<div class="ord-tl-row">' +
                     '<span class="ord-tl-time">' + esc(t.at) + '</span>' +
                     '<span class="ord-tl-ev">' + esc(t.event.replace('status:', '')) +
                       (t.note ? ' — ' + esc(t.note) : '') + '</span>' +
                     '<span class="ord-tl-actor">' + esc(t.actor) + '</span>' +
                   '</div>';
          }).join('') + '</div>';
        }

        $('d-body').innerHTML = h;
        $('detail-modal').hidden = false;
      })
      .catch(function () { toast('Could not load that order', 'err'); });
  }

  /* ==========================================================
     WIRING
     ========================================================== */
  document.addEventListener('DOMContentLoaded', function () {
    setRange('today');
    load();

    $('range-chips').addEventListener('click', function (e) {
      var chip = e.target.closest('.chip');
      if (!chip) return;
      Array.prototype.forEach.call(this.children, function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      setRange(chip.dataset.range);
      state.page = 1;
      load();
    });

    ['f-from', 'f-to'].forEach(function (id) {
      $(id).addEventListener('change', function () {
        state.from = $('f-from').value;
        state.to   = $('f-to').value;
        state.page = 1;
        Array.prototype.forEach.call($('range-chips').children, function (c) { c.classList.remove('active'); });
        load();
      });
    });

    $('f-status').addEventListener('change', function () {
      state.status = this.value; state.page = 1; load();
    });

    var searchTimer = null;
    $('f-q').addEventListener('input', function () {
      var v = this.value;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () { state.q = v; state.page = 1; load(); }, 300);
    });

    $('ord-list').addEventListener('click', function (e) {
      var row = e.target.closest('.ord-row');
      if (row) openDetail(parseInt(row.dataset.id, 10));
    });

    $('ord-paging').addEventListener('click', function (e) {
      var btn = e.target.closest('[data-page]');
      if (!btn || btn.disabled) return;
      state.page = parseInt(btn.dataset.page, 10);
      load();
      // .board is the scroll container here, not the window.
      var board = document.querySelector('.board');
      if (board) board.scrollTop = 0;
    });

    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-close]')) {
        var m = e.target.closest('.modal');
        if (m) m.hidden = true;
      }
    });

    $('d-reprint').addEventListener('click', function () {
      var id = this.dataset.id;
      api('api/update.php', { action: 'reprint', order_id: parseInt(id, 10) })
        .then(function (r) { toast(r.success ? 'Ticket queued' : (r.message || 'Failed'), r.success ? 'ok' : 'err'); })
        .catch(function () { toast('Network problem', 'err'); });
    });

    // Print the visible list — a simple end-of-day summary.
    $('btn-print-day').addEventListener('click', function () {
      var d = state.lastPayload;
      if (!d) return;

      var rows = d.orders.map(function (o) {
        return '<div class="row"><span>' + esc(o.time) + '  ' + esc(o.order_code) + '  ' +
               esc(o.customer_name) + '</span><span>' + esc(o.total) + '</span></div>';
      }).join('');

      $('print-area').innerHTML =
        '<div class="bb-ticket">' +
          '<div class="c">BAGEL BOYZ NJ</div>' +
          '<div class="c">' + esc(d.location_name) + '</div>' +
          '<div class="sp"></div>' +
          '<div class="big">ORDERS</div>' +
          '<div class="c">' + esc(d.range.from) + (d.range.from !== d.range.to ? ' to ' + esc(d.range.to) : '') + '</div>' +
          '<hr>' + rows + '<hr>' +
          '<div class="row"><span>Orders</span><span>' + d.totals.count + '</span></div>' +
          '<div class="row"><span>Subtotal</span><span>' + esc(d.totals.subtotal) + '</span></div>' +
          '<div class="row"><span>Tax</span><span>' + esc(d.totals.tax) + '</span></div>' +
          '<div class="row"><span>Tips</span><span>' + esc(d.totals.tips) + '</span></div>' +
          '<div class="row item"><span>GROSS</span><span>' + esc(d.totals.gross) + '</span></div>' +
        '</div>';

      setTimeout(function () { window.print(); }, 60);
    });
  });
})();
