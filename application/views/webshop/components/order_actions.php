<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$orderId = isset($order_id) ? $order_id : (isset($order['id']) ? $order['id'] : '');
if (!$orderId) {
    return;
}
?>
<div class="oa-wrap">
    <h3>Need help with this order?</h3>
    <p class="oa-sub">Request cancellation or return.</p>
    <form id="orderActionForm" class="oa-form">
        <input type="hidden" name="order_id" value="<?= html_escape($orderId) ?>">
        <div class="oa-row">
            <select name="action" required>
                <option value="">Select action</option>
                <option value="cancel">Cancel order</option>
                <option value="return">Return order</option>
            </select>
        </div>
        <div class="oa-row">
            <textarea name="reason" rows="3" placeholder="Write reason (optional)"></textarea>
        </div>
        <button type="submit">Submit Request</button>
    </form>
    <div id="orderActionMessage" class="oa-msg" style="display:none;"></div>
</div>

<style>
.oa-wrap{margin-top:28px;padding:18px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
.oa-wrap h3{margin:0 0 4px;font-size:18px}
.oa-sub{margin:0 0 12px;color:#6b7280;font-size:13px}
.oa-row{margin-bottom:10px}
.oa-row select,.oa-row textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box}
.oa-form button{background:#0F4C81;color:#fff;border:0;border-radius:8px;padding:10px 14px;cursor:pointer}
.oa-msg{margin-top:10px;padding:9px 10px;border-radius:8px;font-size:13px}
.oa-msg.ok{background:#ecfdf3;border:1px solid #bbf7d0;color:#166534}
.oa-msg.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
</style>

<script>
(function () {
    var form = document.getElementById('orderActionForm');
    var msg = document.getElementById('orderActionMessage');
    if (!form || !msg) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        fetch("<?= base_url('webshop/action') ?>", {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            msg.style.display = 'block';
            if (data && data.status === 'success') {
                msg.className = 'oa-msg ok';
                msg.textContent = 'Your request was submitted successfully.';
            } else {
                msg.className = 'oa-msg err';
                msg.textContent = (data && data.messages) ? data.messages : 'Failed to submit request.';
            }
        }).catch(function () {
            msg.style.display = 'block';
            msg.className = 'oa-msg err';
            msg.textContent = 'Network error. Please try again.';
        });
    });
})();
</script>
