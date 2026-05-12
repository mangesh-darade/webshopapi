<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
/**
 * Wishlist Component
 * Displays a grid of user's favorite products.
 */
$wishlist_items = isset($wishlist['items']) ? $wishlist['items'] : array();
$symbol = isset($Settings->symbol) ? $Settings->symbol : '$';
?>

<?php $wl_assets = isset($assets) ? $assets : base_url('assets/webshop/'); ?>
<link rel="stylesheet" href="<?= $wl_assets ?>gulfpharmacy_theme/css/wishlist.css">
<div class="wishlist-container">
    <div class="wishlist-header">
        <h2 class="section-title">My Wishlist</h2>
        <p class="section-subtitle">Manage your favorite products and add them to your cart.</p>
    </div>

    <?php if (empty($wishlist_items)): ?>
        <div class="empty-wishlist">
            <div class="empty-icon">❤️</div>
            <h3>Your wishlist is empty</h3>
            <p>Go explore our products and save your favorites here!</p>
            <a href="<?= base_url('webshop') ?>" class="btn-primary">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="wishlist-grid">
            <?php foreach ($wishlist_items as $product): 
                $p_id = isset($product['id']) ? $product['id'] : 0;
                $p_name = isset($product['name']) ? $product['name'] : 'Product';
                $p_price = isset($product['price']) ? (float)$product['price'] : 0;
                $p_image = isset($product['image']) ? $product['image'] : 'no_image.png';
                $p_hash = md5((string)$p_id);
            ?>
                <div class="wishlist-card" id="wishlist-item-<?= $p_id ?>">
                    <div class="wishlist-item-image">
                        <img src="<?= $api_media_uploads_base . $p_image ?>" alt="<?= html_escape($p_name) ?>" loading="lazy" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" fill="none"><rect width="80" height="80" rx="8" fill="#F1F5F9"/><path d="M20 56l14-16 10 12 6-8 12 12H20z" fill="#CBD5E1"/><circle cx="28" cy="28" r="5" fill="#CBD5E1"/></svg>') ?>'">
                        <button class="remove-btn" onclick="removeFromWishlist('<?= $p_id ?>')" title="Remove from Wishlist">&times;</button>
                    </div>
                    <div class="wishlist-item-info">
                        <h4 class="product-name">
                            <a href="<?= base_url('webshop/product_details/' . $p_hash) ?>"><?= html_escape($p_name) ?></a>
                        </h4>
                        <div class="product-price">
                            <span class="price-val"><?= $symbol ?> <?= number_format($p_price, 2) ?></span>
                        </div>
                        <div class="wishlist-actions">
                            <button class="add-to-cart-btn" onclick="addToCart('<?= $p_id ?>')">
                                <i class="fa fa-shopping-cart"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>window.GP_WISHLIST_CTX=<?= json_encode(array('remove_url' => base_url('webshop/remove_wishlist/'), 'add_to_cart_url' => base_url('webshop/add_to_cart/')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $wl_assets ?>gulfpharmacy_theme/js/wishlist.js"></script>
