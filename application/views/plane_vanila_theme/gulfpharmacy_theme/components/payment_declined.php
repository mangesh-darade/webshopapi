<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';

$shopName     = isset($Settings->site_name) ? $Settings->site_name : 'Shop';
$errorMessage = isset($error_message) ? $error_message
    : (isset($status_message) ? $status_message : 'Your payment could not be processed. Please try again.');
$orderId      = isset($order_id) ? $order_id : (isset($order['id']) ? $order['id'] : null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Declined | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <style>
        .pd-shell{min-height:100vh;background:#fef2f2;display:flex;flex-direction:column}
        .pd-main{flex:1;max-width:600px;margin:0 auto;padding:48px 20px;width:100%}
        .pd-card{background:#fff;border-radius:20px;padding:40px 36px;box-shadow:0 10px 32px rgba(0,0,0,.07);text-align:center}
        .pd-icon{font-size:64px;margin-bottom:16px}
        .pd-title{font-size:26px;font-weight:800;color:#991b1b;margin:0 0 10px}
        .pd-msg{color:#6b7280;font-size:15px;margin:0 0 28px;line-height:1.6}
        .pd-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
        .pd-btn{padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px;cursor:pointer;border:none;display:inline-block}
        .pd-btn-primary{background:#0F4C81;color:#fff}
        .pd-btn-secondary{background:#f3f4f6;color:#374151}
        @media(max-width:500px){.pd-card{padding:24px 16px}.pd-actions{flex-direction:column}}
    </style>
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
                <?php if ($orderId): ?>
                    <a href="<?= base_url('webshop/payments?order=' . urlencode($orderId)) ?>" class="pd-btn pd-btn-primary">Try Again</a>
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
