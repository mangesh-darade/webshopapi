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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Declined | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/payment-declined.css">
</head>
<body>
<div class="pd-shell">
    <?php if ($theme === 'nw' || $theme === 'gulfpharmacy'): ?>
        <?php require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php'); ?>
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
        <?php require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php'); ?>
    <?php endif; ?>
</div>
</body>
</html>
