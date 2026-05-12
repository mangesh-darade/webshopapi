(function () {
    'use strict';
    var ctx = window.GP_ORDER_ACTIONS_CTX || {};
    var endpoint = typeof ctx.action_url === 'string' ? ctx.action_url : '';
    var form = document.getElementById('orderActionForm');
    var msg = document.getElementById('orderActionMessage');
    if (!form || !msg || !endpoint) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        fetch(endpoint, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            msg.hidden = false;
            msg.style.display = 'block';
            if (data && data.status === 'success') {
                msg.className = 'oa-msg ok';
                msg.textContent = 'Your request was submitted successfully.';
            } else {
                msg.className = 'oa-msg err';
                msg.textContent = (data && data.messages) ? data.messages : 'Failed to submit request.';
            }
        }).catch(function () {
            msg.hidden = false;
            msg.style.display = 'block';
            msg.className = 'oa-msg err';
            msg.textContent = 'Network error. Please try again.';
        });
    });
})();
