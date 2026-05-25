<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* ── Data resolution ─────────────────────────────────────── */
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';

$selectedCatId  = isset($get_category_id) ? (int) $get_category_id : 0;
$categoryName   = 'Products';
if (isset($categories['main'][$selectedCatId])) {
    $c = $categories['main'][$selectedCatId];
    $categoryName = is_object($c) ? (string) $c->name : (isset($c['name']) ? (string) $c['name'] : $categoryName);
}
if (isset($entity_meta_title) && trim((string) $entity_meta_title) !== '') {
    $categoryName = trim((string) $entity_meta_title);
}

$products      = isset($listItems) && is_array($listItems) ? $listItems : array();
$subcategories = isset($subcategories) && is_array($subcategories) ? $subcategories : array();
$totalItems    = isset($items_total) ? (int) $items_total : count($products);
$_cp_ci =& get_instance();
$_cp_page = $_cp_ci->input->get('page');
$currentPage = ($_cp_page !== null && $_cp_page !== '') ? max(1, (int) $_cp_page) : 1;
$perPage       = 12;
$totalPages    = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 1;

$uploadsBase   = isset($uploads) ? (string) $uploads : '';
$thumbsBase    = isset($thumbs)  ? (string) $thumbs  : '';
$symbol        = isset($Settings->symbol) ? $Settings->symbol : '';
$shopName      = isset($Settings->site_name) ? $Settings->site_name : 'Shop';

// Shared fallback so <img> 404s degrade to the same placeholder as products with no image field
// (mirrors the onerror pattern in webshop_normalize_html_media_urls()).
$noImgSrc      = webshop_no_image_src($uploadsBase, $thumbsBase);
$noImgSrcAttr  = htmlspecialchars($noImgSrc, ENT_QUOTES, 'UTF-8');

/* ── Helpers ─────────────────────────────────────────────── */
$getImg = function($item) use ($uploadsBase, $thumbsBase) {
    $row = is_array($item) ? $item : (array) $item;
    return webshop_product_image_src($uploadsBase, $thumbsBase, $row);
};
$_cpSettings = isset($Settings) ? $Settings : null;
$getCardPricing = function($item) use ($_cpSettings) {
    $row = is_array($item) ? $item : (array) $item;
    if (function_exists('webshop_product_list_card_pricing')) {
        $card = webshop_product_list_card_pricing($row, $_cpSettings);
        if ((float) $card['price'] <= 0 && !empty($row['list_display_price']) && (float) $row['list_display_price'] > 0) {
            $card['price'] = (float) $row['list_display_price'];
        }
        if ((float) $card['mrp'] <= 0 && !empty($row['list_display_mrp']) && (float) $row['list_display_mrp'] > 0) {
            $card['mrp'] = (float) $row['list_display_mrp'];
        }
        if (empty($card['has_variants']) && !empty($row['list_default_variant_id'])) {
            $card['has_variants'] = true;
            $card['variant_id'] = (int) $row['list_default_variant_id'];
            $card['variant_price'] = isset($row['list_variant_price']) ? (float) $row['list_variant_price'] : 0.0;
            $card['variant_unit_quantity'] = isset($row['list_variant_unit_quantity']) ? (float) $row['list_variant_unit_quantity'] : 1.0;
            $card['variant_name'] = isset($row['list_variant_name']) ? (string) $row['list_variant_name'] : '';
            $card['price_from'] = !empty($row['list_price_from']);
            $card['price_min'] = isset($row['list_price_min']) ? (float) $row['list_price_min'] : $card['price'];
            $card['price_max'] = isset($row['list_price_max']) ? (float) $row['list_price_max'] : $card['price'];
        }
        if (empty($card['discount_percent']) && !empty($row['list_discount_percent'])) {
            $card['discount_percent'] = (int) $row['list_discount_percent'];
        }
        return $card;
    }
    $price = isset($row['list_display_price']) ? (float) $row['list_display_price'] : (isset($row['price']) ? (float) $row['price'] : 0);
    $mrp = isset($row['list_display_mrp']) ? (float) $row['list_display_mrp'] : (isset($row['mrp']) ? (float) $row['mrp'] : 0);
    return array(
        'price' => $price,
        'mrp' => $mrp,
        'discount_percent' => ($mrp > $price && $price > 0) ? (int) round((($mrp - $price) / $mrp) * 100) : 0,
        'has_variants' => !empty($row['list_default_variant_id']),
        'variant_id' => isset($row['list_default_variant_id']) ? (int) $row['list_default_variant_id'] : 0,
        'variant_price' => isset($row['list_variant_price']) ? (float) $row['list_variant_price'] : 0.0,
        'variant_unit_quantity' => isset($row['list_variant_unit_quantity']) ? (float) $row['list_variant_unit_quantity'] : 1.0,
        'variant_name' => isset($row['list_variant_name']) ? (string) $row['list_variant_name'] : '',
        'price_from' => !empty($row['list_price_from']),
        'price_min' => isset($row['list_price_min']) ? (float) $row['list_price_min'] : $price,
        'price_max' => isset($row['list_price_max']) ? (float) $row['list_price_max'] : $price,
    );
};
$getHash = function($item) {
    $row = is_array($item) ? $item : (array) $item;
    return isset($row['proudctIdHash']) ? $row['proudctIdHash']
         : (isset($row['id']) ? md5($row['id']) : '');
};
$getCategory = function($item) {
    $row = is_array($item) ? $item : (array) $item;
    foreach (array('category_name','category','cat_name') as $k) {
        if (!empty($row[$k])) return htmlspecialchars($row[$k], ENT_QUOTES,'UTF-8');
    }
    return '';
};
$deliveryEta = 'Fast delivery · Same-day dispatch where available';
if (isset($webshop_settings) && is_object($webshop_settings)) {
    foreach (array('delivery_eta', 'delivery_time_text', 'delivery_note') as $_dk) {
        if (!empty($webshop_settings->{$_dk})) {
            $deliveryEta = trim((string) $webshop_settings->{$_dk});
            break;
        }
    }
}
$_cp_wl_lookup = function_exists('webshop_view_wishlist_lookup')
    ? webshop_view_wishlist_lookup(isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : null)
    : (isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : array());
$_cp_logged_in = function_exists('webshop_is_customer_logged_in')
    ? webshop_is_customer_logged_in()
    : !empty($webshop_is_logged_in);
$_cp_lcp_img = '';
if (!empty($products)) {
    $_cp_first = reset($products);
    if ($_cp_first !== false) {
        $_cp_lcp_img = $getImg($_cp_first);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <?php if ($_cp_lcp_img !== '' && function_exists('webshop_external_origin_preconnect_tag')): ?>
    <?= webshop_external_origin_preconnect_tag($_cp_lcp_img) ?>

    <?php endif; ?>
    <?php if ($_cp_lcp_img !== ''): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($_cp_lcp_img, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css?ver=20260525g">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/category-products.css?ver=20260526a">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/wishlist-fav.css?ver=20260526a">
</head>
<body>
<div class="gp-site-wrapper cp-shell">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
    } elseif (is_file(VIEWPATH . 'webshop/header.php')) {
        require_once(VIEWPATH . 'webshop/header.php');
    }
    ?>

    <!-- Breadcrumb -->
    <nav class="cp-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= base_url('webshop') ?>">Home</a>
        <span aria-hidden="true">›</span>
        <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>
    </nav>

    <div class="cp-inner">

        <?php
        $cp_sidebar_subcats = !empty($subcategories);
        $cp_sidebar_brands  = !empty($category_brands) && is_array($category_brands);
        ?>
        <?php if ($cp_sidebar_subcats || $cp_sidebar_brands): ?>
        <aside class="cp-sidebar">
            <?php if ($cp_sidebar_subcats): ?>
            <div class="cp-sidebar-box">
                <h3 class="cp-sidebar-title">Subcategories</h3>
                <?php foreach ($subcategories as $subId => $sub):
                    $subName = is_object($sub) ? $sub->name : (isset($sub['name']) ? $sub['name'] : '');
                    $subLink = base_url('webshop/category_products/' . $selectedCatId . '/' . $subId);
                ?>
                    <a href="<?= $subLink ?>" class="cp-sidebar-link"><?= htmlspecialchars($subName, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($cp_sidebar_brands): ?>
            <div class="cp-sidebar-box">
                <h3 class="cp-sidebar-title">Brands</h3>
                <?php foreach (array_slice($category_brands, 0, 10) as $brand):
                    $bName = is_object($brand) ? (isset($brand->name) ? $brand->name : '') : (isset($brand['name']) ? $brand['name'] : '');
                    if (!$bName) continue;
                ?>
                    <a href="<?= base_url('webshop/category_products/' . $selectedCatId . '?brand=' . urlencode($bName)) ?>" class="cp-sidebar-link">
                        <?= htmlspecialchars($bName, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </aside>
        <?php endif; ?>

        <!-- Main -->
        <main class="cp-main">
            <div class="cp-header-bar">
                <h1 class="cp-header-title"><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></h1>
                <span class="cp-header-count">
                    <?= $totalItems ?> result<?= $totalItems != 1 ? 's' : '' ?>
                    <?php if ($totalPages > 1): ?>
                        &nbsp;· Page <?= $currentPage ?> of <?= $totalPages ?>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (empty($products)): ?>
                <div class="cp-empty">
                    <div class="cp-empty-icon">📦</div>
                    <p class="cp-empty-title">No products found</p>
                    <p class="cp-empty-desc">This category has no products yet.</p>
                    <a href="<?= base_url('webshop') ?>" class="cp-empty-back">← Back to Home</a>
                </div>
            <?php else: ?>
                <div class="cp-grid">
                <?php foreach ($products as $_cp_idx => $item):
                    $row       = is_array($item) ? $item : (array) $item;
                    $itemId    = function_exists('webshop_product_list_item_id') ? webshop_product_list_item_id($row) : (isset($row['id']) ? (int) $row['id'] : 0);
                    $hash      = $getHash($row);
                    $name      = isset($row['name']) ? $row['name'] : (isset($row['product_name']) ? $row['product_name'] : 'Product');
                    $cardPricing = $getCardPricing($row);
                    $price     = (float) $cardPricing['price'];
                    $mrp       = (float) $cardPricing['mrp'];
                    $discount  = (int) $cardPricing['discount_percent'];
                    $listVariantId = !empty($cardPricing['has_variants']) ? (int) $cardPricing['variant_id'] : 0;
                    $listVariantPrice = !empty($cardPricing['has_variants']) ? (float) $cardPricing['variant_price'] : 0.0;
                    $listVariantUq = !empty($cardPricing['has_variants']) ? (float) $cardPricing['variant_unit_quantity'] : 1.0;
                    $displayPrice = $price > 0 ? $price : (isset($cardPricing['price_min']) ? (float) $cardPricing['price_min'] : 0.0);
                    $imgSrc    = $getImg($row);
                    $catLabel  = $getCategory($row);
                    $isActive  = isset($row['product_is_active']) ? $row['product_is_active'] : 'true';
                    $detailUrl = base_url('webshop/product_details/' . $hash);
                    $rAvg = isset($row['ratings_avarage']) ? (float) $row['ratings_avarage'] : 0;
                    $rCount = isset($row['ratings_count']) ? (int) $row['ratings_count'] : 0;
                    $purchaseState = function_exists('webshop_product_list_purchase_state')
                        ? webshop_product_list_purchase_state($row, $isActive)
                        : array('can_purchase' => true, 'label' => '', 'limited' => false, 'qty' => 0.0, 'unavailable' => false);
                    $canPurchase   = !empty($purchaseState['can_purchase']);
                    $statusLabel   = isset($purchaseState['label']) ? (string) $purchaseState['label'] : '';
                    $unavailable   = !empty($purchaseState['unavailable']);
                    $limitedStock  = !empty($purchaseState['limited']);
                    $stockQty      = isset($purchaseState['qty']) ? (float) $purchaseState['qty'] : 0.0;
                    $newProd = false;
                    foreach (array('created_at', 'date', 'product_added_date', 'added') as $_dk) {
                        if (!empty($row[$_dk])) {
                            $ts = @strtotime((string) $row[$_dk]);
                            if ($ts && (time() - $ts) < 90 * 86400) {
                                $newProd = true;
                                break;
                            }
                        }
                    }
                    $rxProd = false;
                    foreach (array('prescription_required', 'prescription', 'rx', 'is_rx', 'need_rx') as $_rk) {
                        if (!empty($row[$_rk]) && $row[$_rk] !== '0' && $row[$_rk] !== 0 && strtolower((string) $row[$_rk]) !== 'no' && strtolower((string) $row[$_rk]) !== 'false') {
                            $rxProd = true;
                            break;
                        }
                    }
                    $bestseller = ($rCount >= 12) || ($rAvg >= 4.5 && $rCount >= 4) || ($discount >= 28 && $rCount >= 2);
                    $reviewPhrase = $rCount === 0 ? 'No reviews yet' : ($rCount === 1 ? '1 review' : $rCount . ' reviews');
                    $starFill = (int) round(max(0, min(5, $rAvg)));
                    $imgFinal = ($imgSrc !== '') ? $imgSrc : $noImgSrc;
                ?>
                    <div class="pc-card<?= $unavailable ? ' pc-card--unavailable' : '' ?>">
                        <div class="pc-media">
                            <div class="pc-img-frame is-loading">
                                <a class="pc-img-link" href="<?= $detailUrl ?>" tabindex="-1" aria-hidden="true">
                                    <img src="<?= htmlspecialchars($imgFinal, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                         class="pc-product-img"
                                         <?= $_cp_idx === 0 ? 'fetchpriority="high" decoding="sync"' : 'loading="lazy" decoding="async"' ?>
                                         onload="var f=this.closest('.pc-img-frame');if(f)f.classList.remove('is-loading');"
                                         onerror="this.onerror=null;this.src='<?= $noImgSrcAttr ?>';var f=this.closest('.pc-img-frame');if(f)f.classList.remove('is-loading');">
                                </a>
                                <?php
                                $CI =& get_instance();
                                $CI->load->view('plane_vanila_theme/gulfpharmacy_theme/components/wishlist_card_button', array(
                                    'product_id'      => $itemId,
                                    'variant_id'      => $listVariantId,
                                    'wishlist_lookup' => $_cp_wl_lookup,
                                    'extra_class'     => 'pc-wish-btn',
                                ));
                                ?>
                                <div class="pc-badges-tl">
                                    <?php if ($rxProd && !$unavailable): ?>
                                        <span class="pc-pill pc-pill-rx">Rx</span>
                                    <?php endif; ?>
                                    <?php if ($limitedStock && !$unavailable): ?>
                                        <span class="pc-pill pc-pill-stock">Limited stock</span>
                                    <?php endif; ?>
                                </div>
                                <div class="pc-badges-tr">
                                    <?php if ($bestseller && !$unavailable): ?>
                                        <span class="pc-pill pc-pill-bs">Bestseller</span>
                                    <?php endif; ?>
                                    <?php if ($newProd && !$unavailable): ?>
                                        <span class="pc-pill pc-pill-new">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="pc-body">
                            <?php if ($catLabel): ?>
                                <div class="pc-cat"><?= $catLabel ?></div>
                            <?php endif; ?>

                            <a href="<?= $detailUrl ?>" class="pc-title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>

                            <div class="pc-rating">
                                <span class="pc-stars" aria-hidden="true"><?= str_repeat('★', $starFill) ?><?= str_repeat('☆', 5 - $starFill) ?></span>
                                <span class="pc-rating-meta">(<?= htmlspecialchars($reviewPhrase, ENT_QUOTES, 'UTF-8') ?>)</span>
                            </div>

                            <div class="pc-price-row">
                                <span class="pc-price"><?php if ($displayPrice > 0) { ?><?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?> <?= number_format($displayPrice, 2) ?><?php } else { ?><span class="pc-price-zero">Price on request</span><?php } ?></span>
                                <?php if ($mrp > 0 && $mrp > $displayPrice && $displayPrice > 0): ?>
                                    <span class="pc-mrp"><?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?> <?= number_format($mrp, 2) ?></span>
                                    <span class="pc-pct-off"><?= (int) $discount ?>% OFF</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($statusLabel !== ''): ?>
                                <p class="pc-stock-status pc-stock-status--unavailable" role="status"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php elseif ($limitedStock): ?>
                                <p class="pc-stock-status pc-stock-status--low" role="status">Only <?= (int) $stockQty ?> left in stock</p>
                            <?php endif; ?>

                            <?php if ($canPurchase): ?>
                            <p class="pc-delivery">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 18h2M15 18h-5M17 18h2l4-4V8a2 2 0 0 0-2-2h-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="7" cy="18" r="2" stroke="currentColor" stroke-width="2"/></svg>
                                <?= htmlspecialchars($deliveryEta, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <?php endif; ?>

                            <div class="pc-actions<?= $canPurchase ? '' : ' pc-actions--unavailable' ?>">
                                <?php if ($canPurchase): ?>
                                    <button type="button" class="pc-btn pc-btn-cart"
                                            onclick="wsAddToCart('<?= (int) $itemId ?>', '<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>', this)"
                                            data-item-id="<?= (int) $itemId ?>" data-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>"
                                            data-product-price="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8') ?>"
                                            data-variant-id="<?= (int) $listVariantId ?>"
                                            data-variant-price="<?= htmlspecialchars((string) $listVariantPrice, ENT_QUOTES, 'UTF-8') ?>"
                                            data-variant-unit-quantity="<?= htmlspecialchars((string) $listVariantUq, ENT_QUOTES, 'UTF-8') ?>"
                                            data-label-default="Add to cart">
                                        Add to cart
                                    </button>
                                    <a href="<?= $detailUrl ?>" class="pc-btn pc-btn-buy">Buy now</a>
                                <?php else: ?>
                                    <button type="button" class="pc-btn pc-btn-cart is-disabled" disabled aria-disabled="true">Add to cart</button>
                                    <a href="<?= $detailUrl ?>" class="pc-btn pc-btn-view">View details</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="cp-pagination" aria-label="Page navigation">
                    <?php
                    $baseUrl = base_url('webshop/category_products/' . $selectedCatId);
                    if ($currentPage > 1): ?>
                        <a href="<?= $baseUrl ?>?page=<?= $currentPage - 1 ?>" class="cp-page-btn">‹ Prev</a>
                    <?php else: ?>
                        <span class="cp-page-btn disabled">‹ Prev</span>
                    <?php endif;

                    $range = 2;
                    for ($p = max(1, $currentPage - $range); $p <= min($totalPages, $currentPage + $range); $p++): ?>
                        <a href="<?= $baseUrl ?>?page=<?= $p ?>"
                           class="cp-page-btn<?= $p === $currentPage ? ' active' : '' ?>"><?= $p ?></a>
                    <?php endfor;

                    if ($currentPage < $totalPages): ?>
                        <a href="<?= $baseUrl ?>?page=<?= $currentPage + 1 ?>" class="cp-page-btn">Next ›</a>
                    <?php else: ?>
                        <span class="cp-page-btn disabled">Next ›</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php');
    } elseif (is_file(VIEWPATH . 'webshop/footer.php')) {
        require_once(VIEWPATH . 'webshop/footer.php');
    }
    ?>
</div>

<script>
function wsAddToCart(itemId, hash, btn) {
    if (!btn || btn.disabled || btn.getAttribute('aria-disabled') === 'true') {
        return;
    }
    var orig = btn.getAttribute('data-label-default') || (btn.textContent || '').trim();
    btn.disabled = true;
    btn.textContent = 'Adding…';
    requestAnimationFrame(function () {
    var listPrice = btn && btn.getAttribute('data-product-price') ? btn.getAttribute('data-product-price') : '';
    var variantId = btn ? parseInt(btn.getAttribute('data-variant-id'), 10) || 0 : 0;
    var variantPrice = btn && btn.getAttribute('data-variant-price') ? btn.getAttribute('data-variant-price') : '0';
    var variantUq = btn && btn.getAttribute('data-variant-unit-quantity') ? btn.getAttribute('data-variant-unit-quantity') : '1';
    var addBody = 'action=add_to_cart&product_id=' + encodeURIComponent(itemId) + '&quantity=1&variant_id=' + encodeURIComponent(variantId);
    if (listPrice !== '' && parseFloat(listPrice) > 0) {
        addBody += '&product_price=' + encodeURIComponent(listPrice) + '&price=' + encodeURIComponent(listPrice);
    }
    if (variantId > 0) {
        addBody += '&variant_price=' + encodeURIComponent(variantPrice) + '&variant_unit_quantity=' + encodeURIComponent(variantUq);
    }
    if (typeof window.webshopAppendCsrfParams === 'function') {
        addBody = window.webshopAppendCsrfParams(addBody);
    }
    fetch('<?= base_url('webshop/webshop_request') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: addBody,
        credentials: 'same-origin'
    })
    .then(function(r) {
        if (r.status === 403) {
            return { status: 'FAIL', error: 'csrf', message: 'Session expired. Please refresh the page and try again.' };
        }
        return r.json();
    })
    .then(function(d) {
        if (typeof window.webshopUpdateCsrfFromJson === 'function') {
            window.webshopUpdateCsrfFromJson(d);
        }
        if (d && d.error === 'csrf') {
            btn.textContent = 'Refresh page';
            btn.disabled = true;
            return;
        }
        if (d && (d.status === 'SUCCESS' || d.success)) {
            btn.textContent = 'Added';
            btn.style.background = '#059669';
            btn.style.borderColor = '#059669';
            btn.style.color = '#fff';
            setTimeout(function(){
                btn.textContent = orig;
                btn.style.background = '';
                btn.style.borderColor = '';
                btn.style.color = '';
                btn.disabled = false;
            }, 1800);
            var badge = document.querySelector('.gp-cart-count, .cart-count, [data-cart-count]');
            if (badge && d.cart_count !== undefined) {
                badge.textContent = d.cart_count;
                badge.style.display = d.cart_count > 0 ? '' : 'none';
            }
        } else if (d && (d.error === 'out_of_stock' || (d.message && /out of stock/i.test(d.message)))) {
            btn.textContent = 'Out of stock';
            btn.classList.add('is-disabled');
            btn.setAttribute('aria-disabled', 'true');
            setTimeout(function(){ btn.textContent = orig; btn.disabled = true; }, 2500);
        } else {
            btn.textContent = 'Try again';
            setTimeout(function(){ btn.textContent = orig; btn.disabled = false; }, 2000);
        }
    })
    .catch(function() {
        btn.textContent = orig;
        btn.disabled = false;
    });
    });
}
</script>
<?php
$_cp_csrf = function_exists('webshop_csrf_pair') ? webshop_csrf_pair() : array('name' => '', 'hash' => '');
$_cp_assets = isset($assets) ? $assets : base_url('assets/webshop/');
?>
<script>window.GP_CSRF=<?= json_encode($_cp_csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>window.GP_PLP_CTX=<?= json_encode(array(
    'request_url'      => base_url('webshop/webshop_request'),
    'login_url'        => base_url('webshop/login'),
    'is_logged_in'     => (bool) $_cp_logged_in,
    'wishlist_lookup'  => $_cp_wl_lookup,
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $_cp_assets ?>gulfpharmacy_theme/js/webshop-csrf.js?ver=20260526c"></script>
</body>
</html>
