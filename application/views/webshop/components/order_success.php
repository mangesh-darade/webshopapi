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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Placed | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <style>
        .os-shell{min-height:100vh;background:#f0fdf4;display:flex;flex-direction:column}
        .os-main{flex:1;max-width:720px;margin:0 auto;padding:48px 20px;width:100%}
        .os-card{background:#fff;border-radius:20px;padding:40px 36px;box-shadow:0 10px 32px rgba(0,0,0,.07);text-align:center}
        .os-icon{font-size:64px;margin-bottom:16px}
        .os-title{font-size:28px;font-weight:800;color:#166534;margin:0 0 8px}
        .os-sub{color:#6b7280;font-size:16px;margin:0 0 28px}
        .os-ref{display:inline-block;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 20px;font-weight:700;color:#166534;font-size:15px;margin-bottom:28px}
        .os-items{text-align:left;border-top:1px solid #e5e7eb;padding-top:20px;margin-top:4px}
        .os-items-title{font-weight:700;color:#374151;margin-bottom:12px;font-size:15px}
        .os-item{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px dashed #f1f5f9;font-size:15px;color:#374151}
        .os-item:last-child{border-bottom:0}
        .os-item-name{flex:1}
        .os-item-qty{color:#6b7280;font-size:13px;margin:0 12px}
        .os-item-price{font-weight:600}
        .os-total{display:flex;justify-content:space-between;padding:14px 0 0;border-top:2px solid #e5e7eb;margin-top:8px;font-size:17px;font-weight:800;color:#111827}
        .os-actions{display:flex;gap:12px;justify-content:center;margin-top:28px;flex-wrap:wrap}
        .os-btn{padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px;cursor:pointer;border:none;display:inline-block}
        .os-btn-primary{background:#166534;color:#fff}
        .os-btn-secondary{background:#f3f4f6;color:#374151}
        @media(max-width:600px){.os-card{padding:24px 18px}.os-actions{flex-direction:column}}
    </style>
</head>
<body>
<div class="os-shell">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
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
            <?php $this->load->view('webshop/components/order_actions', array('order_id' => isset($order['id']) ? $order['id'] : null, 'order' => $order)); ?>
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
