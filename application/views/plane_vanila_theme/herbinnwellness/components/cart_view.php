<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
/**
 * Reusable Premium Cart Component
 */
$cart_items = isset($cart_items) && is_array($cart_items)
    ? $cart_items
    : (isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array());
$Settings = isset($Settings) ? $Settings : (object) array('symbol' => '$');

$cv_products = (isset($cart_data['products']) && is_array($cart_data['products'])) ? $cart_data['products'] : array();
$cv_variants_map = (isset($cart_data['variants']) && is_array($cart_data['variants'])) ? $cart_data['variants'] : array();

$cv_clean_label = function ($raw) {
    $clean = trim(strip_tags((string) $raw));
    $clean = preg_replace('/\*+/', '', $clean);
    return trim($clean);
};

$cv_resolve_name = function ($p_id, $item) use ($cv_products, $cv_clean_label) {
    if (!empty($item['product_name'])) {
        $from_session = $cv_clean_label($item['product_name']);
        if ($from_session !== '' && strcasecmp($from_session, 'product') !== 0) {
            return $from_session;
        }
    }
    $candidates = array();
    if ($p_id > 0 && isset($cv_products[$p_id]) && is_array($cv_products[$p_id])) {
        $p = $cv_products[$p_id];
        foreach (array('name', 'product_name', 'title') as $k) {
            if (!empty($p[$k])) {
                $candidates[] = (string) $p[$k];
            }
        }
    }
    foreach (array('product_name', 'name') as $k) {
        if (!empty($item[$k])) {
            $candidates[] = (string) $item[$k];
        }
    }
    foreach ($candidates as $raw) {
        $clean = $cv_clean_label($raw);
        if ($clean !== '' && strcasecmp($clean, 'product') !== 0) {
            return $clean;
        }
    }
    return $p_id > 0 ? ('Product #' . $p_id) : 'Product';
};

/**
 * Resolve unit price for a cart row (variant-aware; session + catalogue).
 */
$cv_unit_price = function ($item) use ($cv_products) {
    $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    $vid = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;
    $p = ($pid > 0 && isset($cv_products[$pid]) && is_array($cv_products[$pid])) ? $cv_products[$pid] : array();
    $fallback_delta = ($vid > 0 && isset($item['variant_price'])) ? (float) $item['variant_price'] : null;
    $cart_unit = 0.0;
    foreach (array('product_price', 'price', 'promotion_price', 'promo_price') as $k) {
        if (isset($item[$k]) && (float) $item[$k] > 0) {
            $cart_unit = (float) $item[$k];
            break;
        }
    }
    if (function_exists('webshop_resolve_variant_line_price')) {
        $resolved = webshop_resolve_variant_line_price($p, $vid, $fallback_delta, $cart_unit, $cart_unit);
        if (!empty($resolved['unit_price']) && (float) $resolved['unit_price'] > 0) {
            return (float) $resolved['unit_price'];
        }
    }
    return $cart_unit;
};

$subtotal = 0;
foreach ($cart_items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $subtotal += $cv_unit_price($item) * (isset($item['quantity']) ? (float) $item['quantity'] : 0);
}
?>
<?php $cv_assets = isset($assets) ? $assets : base_url('assets/webshop/'); ?>
<?php
$cv_cart_flash = function_exists('webshop_checkout_flash_error_message')
    ? webshop_checkout_flash_error_message()
    : '';
?>
<div class="cart-container">
    <h2 class="cart-title">Your Shopping Cart</h2>

    <?php if ($cv_cart_flash !== ''): ?>
        <div class="checkout-flash checkout-flash--error" role="alert"><?= htmlspecialchars($cv_cart_flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

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
                    if (!is_array($item)) {
                        continue;
                    }
                    $p_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                    $vid = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;
                    $name = $cv_resolve_name($p_id, $item);
                    $variant_label = '';
                    if (function_exists('webshop_cart_line_variant_label')) {
                        $p_row = ($p_id > 0 && isset($cv_products[$p_id]) && is_array($cv_products[$p_id])) ? $cv_products[$p_id] : array();
                        $variant_label = webshop_cart_line_variant_label($item, $p_row, $cv_variants_map);
                    } elseif (!empty($item['variant_name'])) {
                        $variant_label = trim((string) $item['variant_name']);
                    }
                    $price = $cv_unit_price($item);
                    $qty = isset($item['quantity']) ? (float) $item['quantity'] : 0;
                    $line_total = $price * $qty;
                ?>
                    <div class="cart-item-card" data-cart-line="<?= html_escape($hash) ?>">
                        <div class="item-details">
                            <h4 class="item-name"><?= html_escape($name) ?></h4>
                            <?php if ($variant_label !== ''): ?>
                            <p class="item-variant"><?= html_escape($variant_label) ?></p>
                            <?php endif; ?>
                            <div class="item-meta">
                                <div class="item-price-wrap">
                                    <?php if ($price > 0): ?>
                                    <span class="item-price"><?= html_escape($Settings->symbol) ?> <?= number_format($price, 2) ?></span>
                                    <?php if ($qty > 1): ?>
                                    <span class="item-price-each-hint">each</span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="item-price item-price-na">Price on request</span>
                                    <?php endif; ?>
                                </div>
                                <div class="quantity-control" role="group" aria-label="Quantity">
                                    <button type="button" class="qty-btn minus" data-qty-change="minus" data-hash="<?= html_escape($hash) ?>" aria-label="Decrease quantity">−</button>
                                    <input type="number" class="qty-input" value="<?= (int) $qty ?>" data-hash="<?= html_escape($hash) ?>" min="1" step="1" readonly aria-label="Quantity">
                                    <button type="button" class="qty-btn plus" data-qty-change="plus" data-hash="<?= html_escape($hash) ?>" aria-label="Increase quantity">+</button>
                                </div>
                            </div>
                        </div>
                        <div class="item-total" data-line-total>
                            <?php if ($price > 0): ?>
                            <?= html_escape($Settings->symbol) ?> <?= number_format($line_total, 2) ?>
                            <?php else: ?>
                            <span class="item-total-na">&mdash;</span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="remove-item-btn" data-cart-remove="<?= html_escape($hash) ?>" aria-label="Remove item">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary-sidebar">
                <div class="summary-card">
                    <h3>Cart Totals</h3>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span data-cart-subtotal><?= html_escape($Settings->symbol) ?> <?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Grand Total</span>
                        <span data-cart-grand-total><?= html_escape($Settings->symbol) ?> <?= number_format($subtotal, 2) ?></span>
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
<script>window.GP_CART_CTX=<?= json_encode(array(
    'request_url' => base_url('webshop/webshop_request'),
    'currency'    => isset($Settings->symbol) ? (string) $Settings->symbol : '$',
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= webshop_theme_assets_url('js/cart.js') ?>"></script>
