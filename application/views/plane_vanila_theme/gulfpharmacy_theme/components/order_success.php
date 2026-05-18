<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$order     = isset($order) && is_array($order) ? $order : array();
$items     = isset($items) && is_array($items)  ? $items  : array();
$symbol    = isset($Settings->symbol) ? $Settings->symbol : '';
$shopName  = isset($Settings->site_name) ? $Settings->site_name : 'Shop';
$refNo     = isset($order['reference_no']) ? $order['reference_no'] : (isset($order['id']) ? $order['id'] : '—');
$grandTotal = isset($order['grand_total']) ? (float) $order['grand_total'] : 0;
$order_notify_hint = isset($order_notify_hint) && (string) $order_notify_hint !== ''
    ? (string) $order_notify_hint
    : (string) $this->session->flashdata('order_notify_hint');

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
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/common.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/header.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/order-success.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="os-shell">
    <?php
    if (function_exists('webshop_plane_vanila_view_file') && is_file(webshop_plane_vanila_view_file('header'))) {
        $gp_header_logo_fetchpriority = ($os_preload_logo !== '');
        require_once webshop_plane_vanila_view_file('header');
        unset($gp_header_logo_fetchpriority);
    }
    ?>

    <main class="os-main">
        <div class="os-card">
            <div class="os-icon" aria-hidden="true">✅</div>
            <h1 class="os-title">Order Placed Successfully!</h1>
            <p class="os-sub">Thank you for your purchase. We've received your order.</p>

            <?php if ($order_notify_hint !== ''): ?>
            <p class="os-notify" role="status"><?= htmlspecialchars($order_notify_hint, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
            <p class="os-notify" role="status">Order confirmation and updates will be sent via WhatsApp, SMS, and/or email when available on your account.</p>
            <?php endif; ?>

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
        </div>
    </main>

    <?php
    if (function_exists('webshop_plane_vanila_view_file') && is_file(webshop_plane_vanila_view_file('footer'))) {
        require_once webshop_plane_vanila_view_file('footer');
    }
    ?>
</div>
</body>
</html>
