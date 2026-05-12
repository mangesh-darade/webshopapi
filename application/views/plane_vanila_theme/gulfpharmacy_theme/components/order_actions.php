<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$orderId = isset($order_id) ? $order_id : (isset($order['id']) ? $order['id'] : '');
if (!$orderId) {
    return;
}
?>
<?php $oa_assets = isset($assets) ? $assets : base_url('assets/webshop/'); ?>
<link rel="stylesheet" href="<?= $oa_assets ?>gulfpharmacy_theme/css/components.css">
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
    <div id="orderActionMessage" class="oa-msg" hidden></div>
</div>
<script>window.GP_ORDER_ACTIONS_CTX=<?= json_encode(array('action_url' => base_url('webshop/action')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $oa_assets ?>gulfpharmacy_theme/js/order-actions.js"></script>
