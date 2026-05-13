<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
/**
 * Reusable Premium Cart Component
 */
$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
$Settings = isset($Settings) ? $Settings : (object) array('symbol' => '$');

$cv_products = (isset($cart_data['products']) && is_array($cart_data['products'])) ? $cart_data['products'] : array();

/**
 * Resolve unit price for a cart row.
 * Session-stored prices are preferred (set by Webshop_action_engine::add_to_cart),
 * but fall back to the API-resolved product list so legacy 0-price rows still
 * display a meaningful number on screen.
 */
$cv_unit_price = function($item) use ($cv_products) {
    foreach (array('product_price', 'price', 'promotion_price') as $k) {
        if (isset($item[$k]) && (float) $item[$k] > 0) {
            return (float) $item[$k];
        }
    }
    $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    if ($pid > 0 && isset($cv_products[$pid]) && is_array($cv_products[$pid])) {
        foreach (array('price', 'eshop_price', 'sale_price', 'mrp') as $k) {
            if (isset($cv_products[$pid][$k]) && (float) $cv_products[$pid][$k] > 0) {
                return (float) $cv_products[$pid][$k];
            }
        }
    }
    return 0.0;
};

$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += ($cv_unit_price($item) * (isset($item['quantity']) ? (float) $item['quantity'] : 0));
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
                    $p_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                    $name = isset($cv_products[$p_id]['name']) ? $cv_products[$p_id]['name'] : 'Product';
                    $price = $cv_unit_price($item);
                    $qty = isset($item['quantity']) ? (float) $item['quantity'] : 0;
                ?>
                    <div class="cart-item-card">
                        <div class="item-details">
                            <h4 class="item-name"><?= html_escape($name) ?></h4>
                            <div class="item-meta">
                                <?php if ($price > 0): ?>
                                <span class="item-price"><?= $Settings->symbol ?> <?= number_format($price, 2) ?></span>
                                <?php else: ?>
                                <span class="item-price item-price-na">Price on request</span>
                                <?php endif; ?>
                                <div class="quantity-control">
                                    <button type="button" class="qty-btn minus" data-qty-change="minus" data-hash="<?= html_escape($hash) ?>">-</button>
                                    <input type="number" class="qty-input" value="<?= (int) $qty ?>" data-hash="<?= html_escape($hash) ?>" min="1" readonly>
                                    <button type="button" class="qty-btn plus" data-qty-change="plus" data-hash="<?= html_escape($hash) ?>">+</button>
                                </div>
                            </div>
                        </div>
                        <div class="item-total">
                            <?php if ($price > 0): ?>
                            <?= $Settings->symbol ?> <?= number_format($price * $qty, 2) ?>
                            <?php else: ?>
                            &mdash;
                            <?php endif; ?>
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
                    <?php
                    $cv_ws = isset($this->session->webshop) ? $this->session->webshop : null;
                    $cv_is_login = false;
                    if (is_object($cv_ws)) {
                        $cv_is_login = !empty($cv_ws->is_login) && !empty($cv_ws->user_id);
                    } elseif (is_array($cv_ws)) {
                        $cv_is_login = !empty($cv_ws['is_login']) && !empty($cv_ws['user_id']);
                    }
                    if (!$cv_is_login):
                    ?>
                    <p class="cart-guest-hint">
                        No account needed — checkout as a guest, or
                        <a href="<?= base_url('webshop/login?return_page=webshop/checkout') ?>">sign in</a>
                        for faster checkout.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>window.GP_CART_CTX=<?= json_encode(array('request_url' => base_url('webshop/webshop_request')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $cv_assets ?>gulfpharmacy_theme/js/cart.js"></script>
