<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Gulf Pharmacy — Wishlist items (used by pages/wishlist.php). */
$wishlist_items = isset($wishlist['items']) ? $wishlist['items'] : array();
$symbol = isset($Settings->symbol) ? $Settings->symbol : '$';
$uploadsBase = isset($uploads) ? (string) $uploads : '';
$thumbsBase = isset($thumbs) ? (string) $thumbs : '';
$wlPlaceholder = webshop_no_image_src($uploadsBase, $thumbsBase);
$wl_assets = isset($assets) ? $assets : base_url('assets/webshop/');
$wl_assets = rtrim((string) $wl_assets, '/') . '/';
$wl_count = is_array($wishlist_items) ? count($wishlist_items) : 0;
$_wl_js_ver = isset($_wl_js_ver) ? (string) $_wl_js_ver : '20260520c';
?>
<div class="wl-shell">
    <div class="wl-inner">
        <nav class="wl-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= base_url('webshop') ?>">Home</a>
            <span class="wl-breadcrumb-sep" aria-hidden="true">/</span>
            <span class="wl-breadcrumb-current">Wishlist</span>
        </nav>
        <header class="wl-header">
            <h1 class="wl-title">My Wishlist</h1>
            <p class="wl-subtitle">Save your favorite products and quickly add them to your cart.</p>
            <?php if ($wl_count > 0): ?>
            <p class="wl-count-pill" id="wlCountPill"><span aria-hidden="true">♥</span> <?= (int) $wl_count ?> <?= $wl_count === 1 ? 'item' : 'items' ?> saved</p>
            <?php endif; ?>
        </header>

        <?php if (empty($wishlist_items)): ?>
        <div class="wl-empty" role="status">
            <div class="wl-empty-icon" aria-hidden="true">♡</div>
            <h2>Your wishlist is empty</h2>
            <p>Browse products and save your favorite items — they will appear here for fast checkout.</p>
            <a href="<?= base_url('webshop') ?>" class="wl-empty-cta">Continue shopping</a>
        </div>
        <?php else: ?>
        <div class="wl-grid<?= $wl_count === 1 ? ' wl-grid--single' : '' ?>" id="wlGrid">
            <?php foreach ($wishlist_items as $product):
                $p_id = isset($product['id']) ? (int) $product['id'] : 0;
                $p_name = isset($product['name']) ? $product['name'] : 'Product';
                $p_price = isset($product['price']) ? (float) $product['price'] : 0;
                $p_hash = md5((string) $p_id);
                $imgSrc = webshop_product_image_src($uploadsBase, $thumbsBase, $product);
                $pd_url = base_url('webshop/product_details/' . $p_hash);
            ?>
            <article class="wl-card" id="wishlist-item-<?= (int) $p_id ?>">
                <div class="wl-card-toolbar">
                    <span class="wl-card-badge" title="Saved" aria-hidden="true">♥</span>
                    <button type="button" class="wl-remove" data-wishlist-remove="<?= (int) $p_id ?>" aria-label="Remove from wishlist"><span aria-hidden="true">&times;</span></button>
                </div>
                <a class="wl-card-media" href="<?= htmlspecialchars($pd_url, ENT_QUOTES, 'UTF-8') ?>">
                    <img class="wl-card-img"
                         src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($p_name, ENT_QUOTES, 'UTF-8') ?>"
                         loading="lazy"
                         width="400"
                         height="400"
                         onerror="this.onerror=null;this.src='<?= htmlspecialchars($wlPlaceholder, ENT_QUOTES, 'UTF-8') ?>'">
                </a>
                <div class="wl-card-body">
                    <h2 class="wl-card-title">
                        <a href="<?= htmlspecialchars($pd_url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p_name, ENT_QUOTES, 'UTF-8') ?></a>
                    </h2>
                    <?php if ($p_price > 0): ?>
                    <p class="wl-card-price"><?= htmlspecialchars((string) $symbol, ENT_QUOTES, 'UTF-8') ?><?= number_format($p_price, 2) ?></p>
                    <?php else: ?>
                    <p class="wl-card-price"><?= htmlspecialchars((string) $symbol, ENT_QUOTES, 'UTF-8') ?> —</p>
                    <?php endif; ?>
                    <div class="wl-card-actions">
                        <button type="button" class="wl-btn wl-btn--primary" data-wishlist-add="<?= (int) $p_id ?>">Add to cart</button>
                        <a class="wl-btn wl-btn--outline" href="<?= htmlspecialchars($pd_url, ENT_QUOTES, 'UTF-8') ?>">View product</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<div class="wl-toast" id="wlToast" role="status" aria-live="polite"></div>
<script>window.GP_WISHLIST_CTX=<?= json_encode(array(
    'request_url' => base_url('webshop/webshop_request'),
    'login_url'   => base_url('webshop/login'),
    'cart_url'    => base_url('webshop/cart'),
    'item_count'  => (int) $wl_count,
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php if ($wl_count > 0): ?>
<script>
(function () {
    if (typeof window.webshopUpdateWishlistBadge === 'function') {
        window.webshopUpdateWishlistBadge(<?= (int) $wl_count ?>);
    }
})();
</script>
<?php endif; ?>
<script defer src="<?= htmlspecialchars($wl_assets, ENT_QUOTES, 'UTF-8') ?>gulfpharmacy_theme/js/header-drawers.js?ver=<?= htmlspecialchars($_wl_js_ver, ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars($wl_assets, ENT_QUOTES, 'UTF-8') ?>gulfpharmacy_theme/js/wishlist.js?ver=<?= htmlspecialchars($_wl_js_ver, ENT_QUOTES, 'UTF-8') ?>"></script>
