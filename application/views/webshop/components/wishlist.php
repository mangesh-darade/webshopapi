<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
/**
 * Wishlist Component
 * Displays a grid of user's favorite products.
 */
$wishlist_items = isset($wishlist['items']) ? $wishlist['items'] : array();
$symbol = isset($Settings->symbol) ? $Settings->symbol : '$';
?>

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
                        <img src="<?= $api_media_uploads_base . $p_image ?>" alt="<?= html_escape($p_name) ?>" onerror="this.src='<?= base_url('assets/uploads/no_image.png') ?>'">
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

<style>
    .wishlist-container {
        padding: 40px 0;
        max-width: 1200px;
        margin: 0 auto;
    }
    .wishlist-header {
        margin-bottom: 40px;
        text-align: center;
    }
    .section-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 8px;
    }
    .section-subtitle {
        color: #718096;
        font-size: 1rem;
    }
    .empty-wishlist {
        text-align: center;
        padding: 60px 20px;
        background: #f8fafc;
        border-radius: 16px;
        border: 2px dashed #e2e8f0;
    }
    .empty-icon {
        font-size: 4rem;
        margin-bottom: 20px;
    }
    .btn-primary {
        display: inline-block;
        background: #fa8507;
        color: white;
        padding: 12px 30px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        margin-top: 20px;
        transition: background 0.2s;
    }
    .btn-primary:hover {
        background: #e67700;
    }
    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 30px;
    }
    .wishlist-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    .wishlist-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .wishlist-item-image {
        position: relative;
        padding-top: 100%; /* 1:1 Aspect Ratio */
        background: #f7fafc;
    }
    .wishlist-item-image img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 20px;
    }
    .remove-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: white;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        font-size: 1.2rem;
        color: #e53e3e;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: background 0.2s;
    }
    .remove-btn:hover {
        background: #fff5f5;
    }
    .wishlist-item-info {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .product-name {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: #2d3748;
    }
    .product-name a {
        text-decoration: none;
        color: inherit;
    }
    .product-name a:hover {
        color: #fa8507;
    }
    .product-price {
        margin-top: auto;
        margin-bottom: 15px;
    }
    .price-val {
        font-size: 1.25rem;
        font-weight: 700;
        color: #fa8507;
    }
    .add-to-cart-btn {
        width: 100%;
        background: #2d3748;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .add-to-cart-btn:hover {
        background: #1a202c;
    }
</style>

<script>
function removeFromWishlist(productId) {
    if(!confirm('Are you sure you want to remove this item?')) return;
    
    // Call API/Controller via AJAX
    fetch('<?= base_url("webshop/remove_wishlist/") ?>' + productId, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'SUCCESS') {
            const el = document.getElementById('wishlist-item-' + productId);
            if(el) {
                el.style.opacity = '0';
                setTimeout(() => {
                    el.remove();
                    if(document.querySelectorAll('.wishlist-card').length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        }
    });
}

function addToCart(productId) {
    window.location.href = '<?= base_url("webshop/add_to_cart/") ?>' + productId;
}
</script>
