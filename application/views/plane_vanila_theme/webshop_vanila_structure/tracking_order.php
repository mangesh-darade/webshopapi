<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Gulf Pharmacy order tracking — /webshop/track_order/{token}
 *
 * Guest: MD5(orders.id) or reference_no from WhatsApp/email (no login).
 * Logged-in: numeric id, reference, or MD5 (must own the order).
 */
$identifier      = isset($identifier) ? (string) $identifier : (isset($order_id) ? (string) $order_id : '');
$is_logged_in    = !empty($is_logged_in);
$tracking_is_guest = !empty($tracking_is_guest);
$tracking_order  = isset($tracking_order) && is_array($tracking_order) ? $tracking_order : array();
$tracking_items  = isset($tracking_items) && is_array($tracking_items) ? $tracking_items : array();
$tracking_error  = isset($tracking_error) ? (string) $tracking_error : '';

$currency = (isset($Settings) && is_object($Settings) && !empty($Settings->symbol)) ? (string) $Settings->symbol : '$';
$shop_name = (isset($Settings) && is_object($Settings) && !empty($Settings->site_name))
    ? (string) $Settings->site_name : 'Gulf Pharmacy';

$status_raw    = isset($tracking_order['sale_status']) ? (string) $tracking_order['sale_status'] : '';
$status_key    = strtolower(preg_replace('/[\s_-]+/', '', $status_raw));
$status_steps  = array('received', 'in_progress', 'ready', 'dispatched', 'delivered');
$status_labels = array(
    'received'    => 'Received',
    'in_progress' => 'In progress',
    'ready'       => 'Ready',
    'dispatched'  => 'Dispatched',
    'delivered'   => 'Delivered',
);
$current_step = 'received';
if (in_array($status_key, array('inprogress', 'accepted', 'preparing', 'processing'), true)) {
    $current_step = 'in_progress';
} elseif (in_array($status_key, array('ready', 'orderready', 'foodready'), true)) {
    $current_step = 'ready';
} elseif (in_array($status_key, array('dispatched', 'shipped', 'enroute', 'outfordelivery'), true)) {
    $current_step = 'dispatched';
} elseif (in_array($status_key, array('delivered', 'completed', 'complete'), true)) {
    $current_step = 'delivered';
}

$current_index = array_search($current_step, $status_steps, true);
if ($current_index === false) {
    $current_index = 0;
}

$order_ref     = isset($tracking_order['reference_no']) ? trim((string) $tracking_order['reference_no']) : '';
$order_id_num  = isset($tracking_order['id']) ? (int) $tracking_order['id'] : 0;
$order_date    = isset($tracking_order['date']) ? (string) $tracking_order['date'] : '';
$payment_meth  = isset($tracking_order['payment_method']) ? (string) $tracking_order['payment_method'] : '';
$payment_stat  = isset($tracking_order['payment_status']) ? (string) $tracking_order['payment_status'] : '';

if (function_exists('webshop_order_grand_total_amount')) {
    $order_total = webshop_order_grand_total_amount($tracking_order, $tracking_items);
    $order_sub   = isset($tracking_order['total']) && (float) $tracking_order['total'] > 0
        ? (float) $tracking_order['total'] : $order_total;
} else {
    $order_total = isset($tracking_order['grand_total']) ? (float) $tracking_order['grand_total'] : 0.0;
    $order_sub   = isset($tracking_order['total']) ? (float) $tracking_order['total'] : 0.0;
}
$order_ship = isset($tracking_order['shipping']) ? (float) $tracking_order['shipping'] : 0.0;
$order_tax  = isset($tracking_order['total_tax']) ? (float) $tracking_order['total_tax'] : 0.0;

$display_ref = $order_ref !== '' ? $order_ref : ($order_id_num > 0 ? ('#' . $order_id_num) : '');
$show_hash_in_sub = ($display_ref === '' && preg_match('/^[a-f0-9]{32}$/i', $identifier));

$order_date_display = '';
if ($order_date !== '' && $order_date !== '0000-00-00 00:00:00') {
    $ts = strtotime($order_date);
    if ($ts) {
        $order_date_display = date('M j, Y · g:i A', $ts);
    }
}

$has_tracking = ($tracking_error === '' && !empty($tracking_order));
$assets_fn = function_exists('webshop_theme_assets_url') ? 'webshop_theme_assets_url' : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Track order<?= $display_ref !== '' ? ' · ' . htmlspecialchars($display_ref, ENT_QUOTES, 'UTF-8') : '' ?> | <?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php if ($assets_fn): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/common.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/header.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/tracking-order.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php else: ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assets . 'gulfpharmacy_theme/css/common.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($assets . 'gulfpharmacy_theme/css/tracking-order.css', ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="icon" type="image/x-icon" href="<?= isset($uploads) ? htmlspecialchars($uploads . 'webshop/herbinn_favicon.ico', ENT_QUOTES, 'UTF-8') : '' ?>">
</head>
<body>

<?php include_once('header.php'); ?>

<main class="to-shell">
    <div class="to-card">
        <header class="to-card-head">
            <div>
                <h1 class="to-title">Track your order</h1>
                <?php if ($display_ref !== ''): ?>
                    <p class="to-sub">Order <span class="to-ref"><?= htmlspecialchars($display_ref, ENT_QUOTES, 'UTF-8') ?></span></p>
                <?php elseif ($show_hash_in_sub): ?>
                    <p class="to-sub">Tracking link from your confirmation message</p>
                <?php endif; ?>
            </div>
            <?php if ($is_logged_in): ?>
            <a class="to-back" href="<?= htmlspecialchars(base_url('webshop/your_orders'), ENT_QUOTES, 'UTF-8') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                My orders
            </a>
            <?php else: ?>
            <a class="to-back" href="<?= htmlspecialchars(base_url('webshop'), ENT_QUOTES, 'UTF-8') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                Continue shopping
            </a>
            <?php endif; ?>
        </header>

        <?php if (!$has_tracking): ?>
            <div class="to-empty">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                    <rect x="9" y="3" width="6" height="4" rx="1"/>
                    <path d="M9 12h6"/>
                    <path d="M9 16h6"/>
                </svg>
                <p class="to-empty-msg">
                    <?= htmlspecialchars($tracking_error !== '' ? $tracking_error : 'No tracking data found for this reference.', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <div class="to-actions">
                    <?php if (!$is_logged_in): ?>
                    <a class="to-btn" href="<?= htmlspecialchars(base_url('webshop/login'), ENT_QUOTES, 'UTF-8') ?>">Sign in</a>
                    <?php endif; ?>
                    <a class="to-btn to-btn-ghost" href="<?= htmlspecialchars(base_url('webshop'), ENT_QUOTES, 'UTF-8') ?>">Back to shop</a>
                    <?php if ($is_logged_in): ?>
                    <a class="to-btn to-btn-ghost" href="<?= htmlspecialchars(base_url('webshop/your_tracking'), ENT_QUOTES, 'UTF-8') ?>">Track another order</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="to-summary">
                <div>
                    <p class="to-summary-label">Order</p>
                    <p class="to-summary-val"><?= $order_id_num > 0 ? (int) $order_id_num : htmlspecialchars($display_ref, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="to-summary-label">Placed</p>
                    <p class="to-summary-val"><?= $order_date_display !== '' ? htmlspecialchars($order_date_display, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                </div>
                <div>
                    <p class="to-summary-label">Status</p>
                    <p class="to-summary-val to-summary-status"><?= htmlspecialchars($status_labels[$current_step], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="to-summary-label">Total</p>
                    <p class="to-summary-val"><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_total, 2) ?></p>
                </div>
            </div>

            <ol class="to-steps" aria-label="Order progress">
                <?php foreach ($status_steps as $i => $key): ?>
                    <?php
                        $state = 'to-step-pending';
                        if ($i < $current_index) {
                            $state = 'to-step-done';
                        } elseif ($i === $current_index) {
                            $state = 'to-step-active';
                        }
                    ?>
                    <li class="to-step <?= $state ?>">
                        <span class="to-step-dot" aria-hidden="true">
                            <?php if ($i < $current_index): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-11"/></svg>
                            <?php else: ?>
                                <span class="to-step-num"><?= $i + 1 ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="to-step-label"><?= htmlspecialchars($status_labels[$key], ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if (!empty($tracking_items)): ?>
                <section class="to-items" aria-label="Order items">
                    <h2 class="to-h2">Items</h2>
                    <ul class="to-items-list">
                        <?php foreach ($tracking_items as $item):
                            $row = is_array($item) ? $item : (array) $item;
                            $name = isset($row['product_name']) ? (string) $row['product_name'] : (isset($row['name']) ? (string) $row['name'] : 'Item');
                            if (function_exists('webshop_order_item_line_total')) {
                                $line = webshop_order_item_line_total($row);
                                $qty  = isset($row['quantity']) ? (float) $row['quantity'] : 1;
                                $unit = $qty > 0 ? $line / $qty : $line;
                            } else {
                                $qty  = isset($row['quantity']) ? (float) $row['quantity'] : 0;
                                $unit = isset($row['unit_price']) ? (float) $row['unit_price'] : (isset($row['price']) ? (float) $row['price'] : 0);
                                $line = isset($row['subtotal']) ? (float) $row['subtotal'] : $qty * $unit;
                            }
                        ?>
                            <li class="to-item">
                                <span class="to-item-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="to-item-qty">× <?= $qty == (int) $qty ? (int) $qty : number_format($qty, 2) ?></span>
                                <span class="to-item-price"><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($line, 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="to-totals" aria-label="Order totals">
                <dl>
                    <div><dt>Subtotal</dt><dd><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_sub, 2) ?></dd></div>
                    <?php if ($order_tax > 0): ?><div><dt>Tax</dt><dd><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_tax, 2) ?></dd></div><?php endif; ?>
                    <div><dt>Shipping</dt><dd><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_ship, 2) ?></dd></div>
                    <div class="to-totals-grand"><dt>Total</dt><dd><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_total, 2) ?></dd></div>
                </dl>
                <?php if ($payment_meth !== '' || $payment_stat !== ''): ?>
                    <p class="to-payment">
                        <?php if ($payment_meth !== ''): ?>Payment: <strong><?= htmlspecialchars(strtoupper($payment_meth), ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
                        <?php if ($payment_stat !== ''): ?> · <span class="to-pay-status"><?= htmlspecialchars(ucfirst($payment_stat), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>
            <?php if ($tracking_is_guest): ?>
            <p class="to-payment" style="margin-top:20px;">
                <a href="<?= htmlspecialchars(base_url('webshop/login'), ENT_QUOTES, 'UTF-8') ?>">Sign in</a> to see all your orders in one place.
            </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php include_once('footer.php'); ?>

<?php if ($has_tracking && $order_id_num > 0 && $is_logged_in): ?>
<script>
    window.GP_TRACK_CTX = {
        order_id: <?= json_encode($order_id_num, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        identifier: <?= json_encode($identifier, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        current_step: <?= json_encode($current_step, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        endpoint: <?= json_encode(base_url('webshop/track_order_status'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        csrf_name: <?= json_encode($this->security->get_csrf_token_name(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        csrf_hash: <?= json_encode($this->security->get_csrf_hash(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
</script>
<script defer src="<?= $assets_fn ? htmlspecialchars(webshop_theme_assets_url('js/tracking-order.js'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($assets . 'gulfpharmacy_theme/js/tracking-order.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>

</body>
</html>
