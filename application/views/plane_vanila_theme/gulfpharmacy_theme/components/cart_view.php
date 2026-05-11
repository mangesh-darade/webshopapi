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
                        <button class="remove-item-btn" onclick="remove_cart_item('<?= $hash ?>')">×</button>
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

<style>
    .cart-container {
        font-family: 'Outfit', sans-serif;
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .cart-title {
        font-size: 2rem;
        margin-bottom: 30px;
        color: #1a1a1a;
    }

    .cart-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 30px;
    }

    @media (max-width: 992px) {
        .cart-grid {
            grid-template-columns: 1fr;
        }
    }

    .cart-item-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid #e2e8f0;
        position: relative;
    }

    .item-details { flex: 1; }
    .item-name { margin: 0 0 5px 0; font-size: 1.1rem; }
    .item-meta { color: #718096; font-size: 0.9rem; }
    .item-total { font-weight: 700; font-size: 1.1rem; color: #fa8507; }

    .remove-item-btn {
        background: none;
        border: none;
        color: #cbd5e1;
        font-size: 1.5rem;
        cursor: pointer;
        margin-left: 20px;
        transition: color 0.2s;
    }

    .remove-item-btn:hover { color: #ef4444; }

    .cart-summary-sidebar .summary-card {
        background: #f8fafc;
        border-radius: 16px;
        padding: 25px;
        border: 1px solid #e2e8f0;
        position: sticky;
        top: 20px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .summary-row.total {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #e2e8f0;
        font-weight: 700;
        font-size: 1.25rem;
        color: #fa8507;
    }

    .checkout-btn {
        display: block;
        width: 100%;
        background: #fa8507;
        color: #fff;
        text-align: center;
        padding: 15px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 700;
        margin-top: 25px;
        transition: background 0.2s;
    }

    .checkout-btn:hover { background: #e67700; }

    .empty-cart-message {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
    }

    .empty-icon { font-size: 4rem; margin-bottom: 20px; }
    .continue-shopping-btn {
        display: inline-block;
        background: #1a1a1a;
        color: #fff;
        padding: 12px 30px;
        border-radius: 50px;
        text-decoration: none;
        margin-top: 20px;
    }
</style>
