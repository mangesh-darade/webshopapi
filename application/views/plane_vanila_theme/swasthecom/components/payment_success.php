<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';

$gw       = isset($payment_gateway_response) && is_array($payment_gateway_response) ? $payment_gateway_response : array();
$shopName = isset($Settings->site_name) ? $Settings->site_name : 'Shop';
$symbol   = isset($Settings->symbol) ? $Settings->symbol : '';

$order    = isset($order) && is_array($order) ? $order : array();
$items    = isset($items) && is_array($items) ? $items : array();

$refNo = isset($order['reference_no']) ? (string) $order['reference_no'] : '';
if ($refNo === '' && isset($gw['order_id'])) {
    $refNo = (string) $gw['order_id'];
}

$trackingId = isset($gw['tracking_id']) ? (string) $gw['tracking_id'] : '';
$bankRef    = isset($gw['bank_ref_no']) ? (string) $gw['bank_ref_no'] : '';
$payAmt     = isset($gw['mer_amount']) && $gw['mer_amount'] !== ''
    ? (string) $gw['mer_amount']
    : (isset($gw['amount']) ? (string) $gw['amount'] : '');
$gwCurrency = isset($gw['currency']) ? (string) $gw['currency'] : '';

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
    <?php if (function_exists('webshop_require_storefront_analytics_head')) { webshop_require_storefront_analytics_head(); } ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Successful | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($os_preload_logo !== ''): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($os_preload_logo, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/header.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/order-success.css') ?>">
</head>
<body>
<div class="os-shell">
    <?php if ($theme === 'nw' || $theme === 'gulfpharmacy'): ?>
        <?php
        $gp_header_logo_fetchpriority = ($os_preload_logo !== '');
        require_once webshop_plane_vanila_view_file('header');
        unset($gp_header_logo_fetchpriority);
        ?>
    <?php endif; ?>

    <main class="os-main">
        <div class="os-card">
            <div class="os-icon">✅</div>
            <h1 class="os-title">Payment Successful</h1>
            <p class="os-sub">Your payment was received. Thank you — your order will be processed shortly.</p>

            <?php if ($refNo !== ''): ?>
                <div class="os-ref">Order #<?= htmlspecialchars($refNo, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($trackingId !== '' || $bankRef !== '' || $payAmt !== ''): ?>
                <div class="os-items os-pay-meta">
                    <div class="os-items-title">Payment details</div>
                    <?php if ($trackingId !== ''): ?>
                        <div class="os-item os-pay-meta-row">
                            <span class="os-item-name">Transaction reference</span>
                            <span class="os-item-price"><?= htmlspecialchars($trackingId, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($bankRef !== ''): ?>
                        <div class="os-item os-pay-meta-row">
                            <span class="os-item-name">Bank reference</span>
                            <span class="os-item-price"><?= htmlspecialchars($bankRef, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($payAmt !== ''): ?>
                        <div class="os-item os-pay-meta-row">
                            <span class="os-item-name">Amount paid<?= $gwCurrency !== '' ? ' (' . htmlspecialchars($gwCurrency, ENT_QUOTES, 'UTF-8') . ')' : '' ?></span>
                            <span class="os-item-price"><?= htmlspecialchars($payAmt, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($items)): ?>
                <div class="os-items">
                    <div class="os-items-title">Items</div>
                    <?php foreach ($items as $item):
                        $itemArr = is_array($item) ? $item : (array) $item;
                        $iName  = isset($itemArr['name']) ? $itemArr['name'] : (isset($itemArr['product_name']) ? $itemArr['product_name'] : 'Item');
                        $iQty   = isset($itemArr['quantity']) ? (int) $itemArr['quantity'] : 1;
                        $iPrice = isset($itemArr['unit_price']) ? (float) $itemArr['unit_price'] : (isset($itemArr['price']) ? (float) $itemArr['price'] : 0);
                    ?>
                        <div class="os-item">
                            <span class="os-item-name"><?= htmlspecialchars((string) $iName, ENT_QUOTES, 'UTF-8') ?></span>
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

    <?php if ($theme === 'nw' || $theme === 'gulfpharmacy'): ?>
        <?php require_once webshop_plane_vanila_view_file('footer'); ?>
    <?php endif; ?>
</div>
</body>
</html>
