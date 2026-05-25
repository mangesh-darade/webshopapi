<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';

$shopName     = isset($Settings->site_name) ? $Settings->site_name : 'Shop';
$errorMessage = isset($error_message) ? $error_message
    : (isset($status_message) ? $status_message : 'Your payment could not be processed. Please try again.');
$orderId = isset($order_id) ? $order_id : (isset($order['id']) ? $order['id'] : null);
if ($orderId === null && isset($payment_gateway_response) && is_array($payment_gateway_response) && isset($payment_gateway_response['order_id'])) {
    $orderId = $payment_gateway_response['order_id'];
}
$retryCustomer = isset($customer_id) ? $customer_id : '';
$retryQs = $orderId !== null && $orderId !== '' ? ('order=' . urlencode((string) $orderId)) : '';
if ($retryQs !== '' && $retryCustomer !== '' && $retryCustomer !== null) {
    $retryQs .= '&customer=' . urlencode((string) $retryCustomer);
}

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
    <title>Payment Declined | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($os_preload_logo !== ''): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($os_preload_logo, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/header.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/payment-declined.css') ?>">
</head>
<body>
<div class="pd-shell">
    <?php if ($theme === 'nw' || $theme === 'gulfpharmacy'): ?>
        <?php
        $gp_header_logo_fetchpriority = ($os_preload_logo !== '');
        require_once webshop_plane_vanila_view_file('header');
        unset($gp_header_logo_fetchpriority);
        ?>
    <?php endif; ?>

    <main class="pd-main">
        <div class="pd-card">
            <div class="pd-icon">❌</div>
            <h1 class="pd-title">Payment Declined</h1>
            <p class="pd-msg"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>

            <div class="pd-actions">
                <?php if ($orderId && $retryQs !== ''): ?>
                    <a href="<?= base_url('webshop/payments?' . $retryQs) ?>" class="pd-btn pd-btn-primary">Try Again</a>
                <?php endif; ?>
                <a href="<?= base_url('webshop') ?>" class="pd-btn pd-btn-secondary">Continue Shopping</a>
            </div>
        </div>
    </main>

    <?php if ($theme === 'nw' || $theme === 'gulfpharmacy'): ?>
        <?php require_once webshop_plane_vanila_view_file('footer'); ?>
    <?php endif; ?>
</div>
</body>
</html>
