<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Gulf Pharmacy wishlist full page (single component file).
 */
$_wlSettings = isset($Settings) && is_object($Settings) ? $Settings : null;
$_shop_name = ($_wlSettings !== null && isset($_wlSettings->site_name)) ? (string) $_wlSettings->site_name : 'Webshop';
$_pv_page_title = 'My Wishlist | ' . $_shop_name;
$_pv_page_robots = 'noindex';
$_pv_page_body_class = 'wl-page';
$_wl_js_ver = '20260526g';

$wishlist_items = isset($wishlist['items']) && is_array($wishlist['items']) ? $wishlist['items'] : array();
$_wl_item_count = count($wishlist_items);
if ($_wl_item_count > 0) {
    $wishlist_count = $_wl_item_count;
}

$wl_display = isset($wishlist_display) && is_array($wishlist_display) ? $wishlist_display : array();
if ($wl_display === array() && isset($wishlist['items']) && is_array($wishlist['items'])) {
    foreach ($wishlist['items'] as $product) {
        $wl_display[] = array(
            'product'   => is_array($product) ? $product : (array) $product,
            'option_id' => 0,
        );
    }
}
$symbol = ($_wlSettings !== null && isset($_wlSettings->symbol)) ? (string) $_wlSettings->symbol : '$';
$uploadsBase = isset($uploads) ? (string) $uploads : '';
$thumbsBase = isset($thumbs) ? (string) $thumbs : '';
$wlPlaceholder = webshop_no_image_src($uploadsBase, $thumbsBase);
$wl_count = isset($wishlist_count) ? (int) $wishlist_count : count($wl_display);

ob_start();
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
            <p class="wl-count-pill" id="wlCountPill"><span class="wl-count-heart" aria-hidden="true">&#9829;</span> <?= (int) $wl_count ?> <?= $wl_count === 1 ? 'item' : 'items' ?> saved</p>
            <?php endif; ?>
        </header>

        <?php if (empty($wl_display)): ?>
        <div class="wl-empty" role="status">
            <div class="wl-empty-icon" aria-hidden="true">♡</div>
            <h2>Your wishlist is empty</h2>
            <p>Browse products and save your favorite items — they will appear here for fast checkout.</p>
            <a href="<?= base_url('webshop') ?>" class="wl-empty-cta">Continue shopping</a>
        </div>
        <?php else: ?>
        <div class="wl-grid<?= $wl_count === 1 ? ' wl-grid--single' : '' ?>" id="wlGrid">
            <?php foreach ($wl_display as $wl_row):
                $product = isset($wl_row['product']) && is_array($wl_row['product']) ? $wl_row['product'] : array();
                $saved_vid = isset($wl_row['option_id']) ? (int) $wl_row['option_id'] : 0;
                $p_id = isset($product['id']) ? (int) $product['id'] : 0;
                $p_name = function_exists('webshop_product_display_name') ? webshop_product_display_name($product) : (isset($product['name']) ? $product['name'] : (isset($product['product_name']) ? $product['product_name'] : ''));
                $p_hash = md5((string) $p_id);
                $card_key = $saved_vid > 0 ? ($p_id . '_' . $saved_vid) : (string) $p_id;
                $display = function_exists('webshop_wishlist_item_display')
                    ? webshop_wishlist_item_display($product, $saved_vid, $_wlSettings)
                    : array('price' => isset($product['price']) ? (float) $product['price'] : 0, 'mrp' => 0, 'discount_percent' => 0, 'variant_id' => $saved_vid, 'variant_price' => 0, 'variant_unit_quantity' => 1, 'variant_name' => '', 'price_from' => false);
                $price = (float) $display['price'];
                $mrp = (float) $display['mrp'];
                $discount = (int) $display['discount_percent'];
                $list_vid = (int) $display['variant_id'];
                $list_vprice = (float) $display['variant_price'];
                $list_vuq = (float) $display['variant_unit_quantity'];
                $variant_label = trim((string) $display['variant_name']);
                $priceFromLabel = !empty($display['price_from']);
                $priceMin = isset($display['price_min']) ? (float) $display['price_min'] : $price;
                $hasVariants = $list_vid > 0 || $variant_label !== '' || !empty(webshop_product_variants_from_row($product));
                $imgSrc = webshop_product_image_src($uploadsBase, $thumbsBase, $product);
                $pd_url = base_url('webshop/product_details/' . $p_hash);
                $rAvg = isset($product['ratings_avarage']) ? (float) $product['ratings_avarage'] : 0;
                $rCount = isset($product['ratings_count']) ? (int) $product['ratings_count'] : 0;
                $starFill = (int) round(max(0, min(5, $rAvg)));
                $reviewPhrase = $rCount === 0 ? 'No reviews yet' : ($rCount === 1 ? '1 review' : $rCount . ' reviews');
                $purchaseState = function_exists('webshop_product_list_purchase_state')
                    ? webshop_product_list_purchase_state($product, true)
                    : array('can_purchase' => true, 'label' => '', 'unavailable' => false);
                $canPurchase = !empty($purchaseState['can_purchase']);
                $statusLabel = isset($purchaseState['label']) ? (string) $purchaseState['label'] : '';
                $unavailable = !empty($purchaseState['unavailable']);
            ?>
            <article class="wl-card<?= $unavailable ? ' wl-card--unavailable' : '' ?>" id="wishlist-item-<?= html_escape($card_key) ?>" data-product-id="<?= (int) $p_id ?>" data-variant-id="<?= (int) $list_vid ?>">
                <div class="wl-card-toolbar">
                    <span class="wl-card-badge" title="Saved" aria-hidden="true">♥</span>
                    <button type="button" class="wl-remove" data-wishlist-remove="<?= (int) $p_id ?>" data-variant-id="<?= (int) $list_vid ?>" aria-label="Remove from wishlist"><span aria-hidden="true">&times;</span></button>
                </div>
                <a class="wl-card-media" href="<?= htmlspecialchars($pd_url, ENT_QUOTES, 'UTF-8') ?>">
                    <img class="wl-card-img" src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($p_name, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" width="400" height="400" data-fallback-src="<?= htmlspecialchars($wlPlaceholder, ENT_QUOTES, 'UTF-8') ?>">
                </a>
                <div class="wl-card-body">
                    <h2 class="wl-card-title"><a href="<?= htmlspecialchars($pd_url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p_name, ENT_QUOTES, 'UTF-8') ?></a></h2>
                    <?php if ($variant_label !== ''): ?><p class="wl-card-variant"><?= htmlspecialchars($variant_label, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    <?php if ($rCount > 0 || $rAvg > 0): ?>
                    <div class="wl-card-rating" aria-label="Rating <?= number_format($rAvg, 1) ?> out of 5">
                        <span class="wl-stars" aria-hidden="true"><?= str_repeat('★', $starFill) ?><?= str_repeat('☆', 5 - $starFill) ?></span>
                        <span class="wl-rating-meta">(<?= htmlspecialchars($reviewPhrase, ENT_QUOTES, 'UTF-8') ?>)</span>
                    </div>
                    <?php endif; ?>
                    <div class="wl-card-price-row">
                        <?php if ($price > 0): ?>
                        <span class="wl-card-price"><?php if ($priceFromLabel && $priceMin > 0) { ?>From <?php } ?><?= htmlspecialchars((string) $symbol, ENT_QUOTES, 'UTF-8') ?> <?= number_format($priceFromLabel && $priceMin > 0 ? $priceMin : $price, 2) ?></span>
                        <?php if ($mrp > $price && !$priceFromLabel): ?><span class="wl-card-mrp"><?= htmlspecialchars((string) $symbol, ENT_QUOTES, 'UTF-8') ?> <?= number_format($mrp, 2) ?></span><?php endif; ?>
                        <?php if ($discount >= 5 && !$priceFromLabel): ?><span class="wl-card-off"><?= (int) $discount ?>% OFF</span><?php endif; ?>
                        <?php elseif ($hasVariants): ?>
                        <span class="wl-card-price wl-card-price--na">See options for price</span>
                        <?php else: ?>
                        <span class="wl-card-price wl-card-price--na">Price on request</span>
                        <?php endif; ?>
                    </div>
                    <div class="wl-card-actions">
                        <?php if ($canPurchase): ?>
                        <button type="button" class="wl-btn wl-btn--primary" data-wishlist-add="<?= (int) $p_id ?>" data-variant-id="<?= (int) $list_vid ?>" data-product-price="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8') ?>" data-variant-price="<?= htmlspecialchars((string) $list_vprice, ENT_QUOTES, 'UTF-8') ?>" data-variant-unit-quantity="<?= htmlspecialchars((string) $list_vuq, ENT_QUOTES, 'UTF-8') ?>">Add to cart</button>
                        <?php else: ?>
                        <button type="button" class="wl-btn wl-btn--primary" disabled>Add to cart</button>
                        <?php endif; ?>
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
<?= $this->load->view(webshop_plane_vanila_view('components/js_bootstrap_var'), array(
    'var_name'  => 'GP_WISHLIST_CTX',
    'var_value' => array(
        'request_url' => base_url('webshop/webshop_request'),
        'login_url'   => base_url('webshop/login'),
        'cart_url'    => base_url('webshop/cart'),
        'item_count'  => (int) $wl_count,
    ),
), true) ?>
<?php if ($wl_count > 0): ?>
<script>(function(){if(typeof window.webshopUpdateWishlistBadge==='function'){window.webshopUpdateWishlistBadge(<?= (int) $wl_count ?>);}})();</script>
<?php endif; ?>
<?php if (function_exists('webshop_csrf_pair')): ?>
<?= $this->load->view(webshop_plane_vanila_view('components/js_bootstrap_var'), array(
    'var_name'  => 'GP_CSRF',
    'var_value' => webshop_csrf_pair(),
), true) ?>
<?php endif; ?>
<script src="<?= htmlspecialchars(webshop_theme_assets_url('js/webshop-csrf.js?ver=' . $_wl_js_ver), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(webshop_theme_assets_url('js/header-drawers.js?ver=' . $_wl_js_ver), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(webshop_theme_assets_url('js/image-fallback.js?ver=20260528a'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(webshop_theme_assets_url('js/wishlist.js?ver=' . $_wl_js_ver), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php
$wishlist_markup = ob_get_clean();

$_wl_tl_open = APPPATH . 'views/plane_vanila_theme/theme_loader/page_open.php';
if (is_file($_wl_tl_open)) {
    require_once $_wl_tl_open;
    echo '<main class="pv-main-content wl-page-main">' . $wishlist_markup . '</main>';
    require_once APPPATH . 'views/plane_vanila_theme/theme_loader/page_close.php';
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (function_exists('webshop_require_storefront_analytics_head')) { webshop_require_storefront_analytics_head(); } ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= htmlspecialchars($_pv_page_robots, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($_pv_page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/common.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/header.css?ver=20260526f'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/header-drawers.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/components.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/wishlist.css?ver=' . $_wl_js_ver), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="<?= htmlspecialchars($_pv_page_body_class, ENT_QUOTES, 'UTF-8') ?>">
<div class="gp-site-wrapper">
<?php require_once webshop_plane_vanila_view_file('header'); ?>
<main class="wl-page-main"><?= $wishlist_markup ?></main>
<?php require_once webshop_plane_vanila_view_file('footer'); ?>
</div>
</body>
</html>
