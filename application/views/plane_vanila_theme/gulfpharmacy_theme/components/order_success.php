<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';

$order     = isset($order) && is_array($order) ? $order : array();
$items     = isset($items) && is_array($items)  ? $items  : array();
$symbol    = isset($Settings->symbol) ? $Settings->symbol : '';
$shopName  = isset($Settings->site_name) ? $Settings->site_name : 'Shop';
$refNo     = isset($order['reference_no']) ? $order['reference_no'] : (isset($order['id']) ? $order['id'] : '—');
$grandTotal = isset($order['grand_total']) ? (float) $order['grand_total'] : 0;

$os_uploads_base = isset($uploads) ? (string) $uploads : '';
$os_preload_logo = function_exists('webshop_resolve_header_logo_url')
    ? webshop_resolve_header_logo_url(
        $os_uploads_base,
        isset($Settings) ? $Settings : null,
        isset($webshop_settings) ? $webshop_settings : null,
        ''
    )
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Placed | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($os_preload_logo !== ''): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($os_preload_logo, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/order-success.css">
</head>
<body>
<div class="os-shell">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        $gp_header_logo_fetchpriority = ($os_preload_logo !== '');
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
        unset($gp_header_logo_fetchpriority);
    }
    ?>

    <main class="os-main">
        <div class="os-card">
            <div class="os-icon">✅</div>
            <h1 class="os-title">Order Placed Successfully!</h1>
            <p class="os-sub">Thank you for your purchase. We've received your order.</p>

            <?php if ($refNo !== '—'): ?>
                <div class="os-ref">Order #<?= htmlspecialchars((string) $refNo, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (!empty($items)): ?>
                <div class="os-items">
                    <div class="os-items-title">Items Ordered</div>
                    <?php foreach ($items as $item):
                        $itemArr = is_array($item) ? $item : (array) $item;
                        $iName  = isset($itemArr['name']) ? $itemArr['name'] : (isset($itemArr['product_name']) ? $itemArr['product_name'] : 'Item');
                        $iQty   = isset($itemArr['quantity']) ? (int) $itemArr['quantity'] : 1;
                        $iPrice = isset($itemArr['unit_price']) ? (float) $itemArr['unit_price'] : (isset($itemArr['price']) ? (float) $itemArr['price'] : 0);
                    ?>
                        <div class="os-item">
                            <span class="os-item-name"><?= htmlspecialchars($iName, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="os-item-qty">× <?= $iQty ?></span>
                            <span class="os-item-price"><?= $symbol ?> <?= number_format($iPrice * $iQty, 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($grandTotal > 0): ?>
                        <div class="os-total">
                            <span>Total</span>
                            <span><?= $symbol ?> <?= number_format($grandTotal, 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="os-actions">
                <a href="<?= base_url('webshop') ?>" class="os-btn os-btn-primary">Continue Shopping</a>
                <?php if (!empty($order['id'])): ?>
                    <a href="<?= base_url('webshop/your_orders') ?>" class="os-btn os-btn-secondary">My Orders</a>
                <?php endif; ?>
            </div>
            <?php $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/order_actions', array('order_id' => isset($order['id']) ? $order['id'] : null, 'order' => $order)); ?>
        </div>
    </main>

    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php');
    }
    ?>
</div>
</body>
</html>
