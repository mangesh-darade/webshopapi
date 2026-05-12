<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
/**
 * Reusable Premium Cart Component
 */
$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
$Settings = isset($Settings) ? $Settings : (object) array('symbol' => '$');

$subtotal = 0;
foreach ($cart_items as $item) {
    $price = isset($item['product_price']) ? $item['product_price'] : (isset($item['price']) ? $item['price'] : 0);
    $subtotal += ($price * $item['quantity']);
}
?>
<?php $cv_assets = isset($assets) ? $assets : base_url('assets/webshop/'); ?>
<link rel="stylesheet" href="<?= $cv_assets ?>gulfpharmacy_theme/css/cart.css">
<div class="cart-container">
    <h2 class="cart-title">Your Shopping Cart</h2>
    
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart-message">
            <div class="empty-icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Looks like you haven't added anything to your cart yet.</p>
            <a href="<?= base_url('webshop') ?>" class="continue-shopping-btn">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-grid">
            <div class="cart-items-list">
                <?php foreach ($cart_items as $hash => $item): 
                    $p_id = $item['product_id'];
                    $name = isset($cart_data['products'][$p_id]['name']) ? $cart_data['products'][$p_id]['name'] : 'Product';
                    $price = isset($item['product_price']) ? $item['product_price'] : (isset($item['price']) ? $item['price'] : 0);
                ?>
                    <div class="cart-item-card">
                        <div class="item-details">
                            <h4 class="item-name"><?= html_escape($name) ?></h4>
                            <div class="item-meta">
                                <span class="item-price"><?= $Settings->symbol ?> <?= number_format($price, 2) ?></span>
                                <span class="item-quantity">Qty: <?= $item['quantity'] ?></span>
                            </div>
                        </div>
                        <div class="item-total">
                            <?= $Settings->symbol ?> <?= number_format($price * $item['quantity'], 2) ?>
                        </div>
                        <button type="button" class="remove-item-btn" data-cart-remove="<?= html_escape($hash) ?>" aria-label="Remove item">×</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary-sidebar">
                <div class="summary-card">
                    <h3>Cart Totals</h3>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?= $Settings->symbol ?> <?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Grand Total</span>
                        <span><?= $Settings->symbol ?> <?= number_format($subtotal, 2) ?></span>
                    </div>
                    <a href="<?= base_url('webshop/checkout') ?>" class="checkout-btn">Proceed to Checkout</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>window.GP_CART_CTX=<?= json_encode(array('request_url' => base_url('webshop/webshop_request')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $cv_assets ?>gulfpharmacy_theme/js/cart.js"></script>
