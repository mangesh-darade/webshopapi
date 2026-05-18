(function () {
  var ctx = window.GP_TRACK_CTX;
  if (!ctx || !ctx.endpoint || !ctx.order_id) {
    return;
  }

  var pollMs = 45000;
  var stepOrder = ['received', 'in_progress', 'ready', 'dispatched', 'delivered'];

  function normalizeKey(raw) {
    return String(raw || '').toLowerCase().replace(/[\s_-]+/g, '');
  }

  function keyToStep(key) {
    if (['inprogress', 'accepted', 'preparing', 'processing'].indexOf(key) >= 0) {
      return 'in_progress';
    }
    if (['ready', 'orderready', 'foodready'].indexOf(key) >= 0) {
      return 'ready';
    }
    if (['dispatched', 'shipped', 'enroute', 'outfordelivery'].indexOf(key) >= 0) {
      return 'dispatched';
    }
    if (['delivered', 'completed', 'complete'].indexOf(key) >= 0) {
      return 'delivered';
    }
    return 'received';
  }

  function applyStep(step) {
    var idx = stepOrder.indexOf(step);
    if (idx < 0) {
      idx = 0;
    }
    var steps = document.querySelectorAll('.to-step');
    steps.forEach(function (el, i) {
      el.classList.remove('to-step-done', 'to-step-active', 'to-step-pending');
      if (i < idx) {
        el.classList.add('to-step-done');
      } else if (i === idx) {
        el.classList.add('to-step-active');
      } else {
        el.classList.add('to-step-pending');
      }
    });
    var statusEl = document.querySelector('.to-summary-status');
    if (statusEl) {
      var labels = { received: 'Received', in_progress: 'In progress', ready: 'Ready', dispatched: 'Dispatched', delivered: 'Delivered' };
      statusEl.textContent = labels[step] || step;
    }
  }

  function poll() {
    var body = new URLSearchParams();
    body.append('identifier', ctx.identifier || String(ctx.order_id));
    if (ctx.csrf_name && ctx.csrf_hash) {
      body.append(ctx.csrf_name, ctx.csrf_hash);
    }

    fetch(ctx.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.status === 'OK' && data.sale_status) {
          var step = keyToStep(normalizeKey(data.sale_status));
          if (step !== ctx.current_step) {
            ctx.current_step = step;
            applyStep(step);
          }
        }
      })
      .catch(function () {});
  }

  setInterval(poll, pollMs);
})();
