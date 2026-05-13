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
$getPrice = function($item) {
    $row = is_array($item) ? $item : (array) $item;
    $p = isset($row['price']) ? (float) $row['price'] : 0;
    if (isset($row['promo_price']) && (float) $row['promo_price'] > 0 && (float) $row['promo_price'] < $p) {
        $p = (float) $row['promo_price'];
    }
    return $p;
};
$getMrp = function($item) {
    $row = is_array($item) ? $item : (array) $item;
    return isset($row['mrp']) ? (float) $row['mrp'] : 0;
};
$getDiscount = function($price, $mrp) {
    return ($mrp > 0 && $price > 0 && $mrp > $price) ? (int) round(($mrp - $price) / $mrp * 100) : 0;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/category-products.css">
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
                <?php foreach ($products as $item):
                    $row       = is_array($item) ? $item : (array) $item;
                    $itemId    = isset($row['id']) ? $row['id'] : 0;
                    $hash      = $getHash($row);
                    $name      = isset($row['name']) ? $row['name'] : (isset($row['product_name']) ? $row['product_name'] : 'Product');
                    $price     = $getPrice($row);
                    $mrp       = $getMrp($row);
                    $discount  = $getDiscount($price, $mrp);
                    $imgSrc    = $getImg($row);
                    $catLabel  = $getCategory($row);
                    $isActive  = isset($row['product_is_active']) ? $row['product_is_active'] : 'true';
                    $detailUrl = base_url('webshop/product_details/' . $hash);
                    $rAvg = isset($row['ratings_avarage']) ? (float) $row['ratings_avarage'] : 0;
                    $rCount = isset($row['ratings_count']) ? (int) $row['ratings_count'] : 0;
                    $stockQty = null;
                    foreach (array('quantity', 'qty', 'product_quantity', 'stock', 'alert_quantity') as $_sk) {
                        if (isset($row[$_sk]) && $row[$_sk] !== '' && is_numeric($row[$_sk])) {
                            $stockQty = (float) $row[$_sk];
                            break;
                        }
                    }
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
                    $limitedStock = ($stockQty !== null && $stockQty > 0 && $stockQty <= 15);
                    $reviewPhrase = $rCount === 0 ? 'No reviews yet' : ($rCount === 1 ? '1 review' : $rCount . ' reviews');
                    $starFill = (int) round(max(0, min(5, $rAvg)));
                    $imgFinal = ($imgSrc !== '') ? $imgSrc : $noImgSrc;
                ?>
                    <div class="pc-card">
                        <a class="pc-media" href="<?= $detailUrl ?>">
                            <div class="pc-img-frame is-loading">
                                <div class="pc-badges-tl">
                                    <?php if ($discount >= 5): ?>
                                        <span class="pc-pill pc-pill-off"><?= (int) $discount ?>% OFF</span>
                                    <?php endif; ?>
                                    <?php if ($rxProd): ?>
                                        <span class="pc-pill pc-pill-rx">Rx</span>
                                    <?php endif; ?>
                                    <?php if ($limitedStock): ?>
                                        <span class="pc-pill pc-pill-stock">Limited stock</span>
                                    <?php endif; ?>
                                </div>
                                <div class="pc-badges-tr">
                                    <?php if ($bestseller): ?>
                                        <span class="pc-pill pc-pill-bs">Bestseller</span>
                                    <?php endif; ?>
                                    <?php if ($newProd): ?>
                                        <span class="pc-pill pc-pill-new">New</span>
                                    <?php endif; ?>
                                </div>
                                <img src="<?= htmlspecialchars($imgFinal, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                     class="pc-product-img"
                                     loading="lazy"
                                     decoding="async"
                                     onload="var f=this.closest('.pc-img-frame');if(f)f.classList.remove('is-loading');"
                                     onerror="this.onerror=null;this.src='<?= $noImgSrcAttr ?>';var f=this.closest('.pc-img-frame');if(f)f.classList.remove('is-loading');">
                            </div>
                        </a>

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
                                <span class="pc-price"><?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?> <?= number_format($price, 2) ?></span>
                                <?php if ($mrp > 0 && $mrp > $price): ?>
                                    <span class="pc-mrp"><?= htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') ?> <?= number_format($mrp, 2) ?></span>
                                    <span class="pc-pct-off"><?= (int) $discount ?>% OFF</span>
                                <?php endif; ?>
                            </div>

                            <p class="pc-delivery">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 18h2M15 18h-5M17 18h2l4-4V8a2 2 0 0 0-2-2h-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="7" cy="18" r="2" stroke="currentColor" stroke-width="2"/></svg>
                                <?= htmlspecialchars($deliveryEta, ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <div class="pc-actions<?= ($isActive === 'true' || $isActive === true || $isActive === 1) ? '' : ' pc-actions-inactive' ?>">
                                <?php if ($isActive === 'true' || $isActive === true || $isActive === 1): ?>
                                    <button type="button" class="pc-btn pc-btn-cart"
                                            onclick="wsAddToCart('<?= (int) $itemId ?>', '<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>', this)"
                                            data-item-id="<?= (int) $itemId ?>" data-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>"
                                            data-label-default="Add to cart">
                                        Add to cart
                                    </button>
                                    <a href="<?= $detailUrl ?>" class="pc-btn pc-btn-buy">Buy now</a>
                                <?php else: ?>
                                    <div class="pc-unavailable">Currently unavailable</div>
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
    var orig = btn.getAttribute('data-label-default') || (btn.textContent || '').trim();
    btn.disabled = true;
    btn.textContent = 'Adding…';
    fetch('<?= base_url('webshop/webshop_request') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_to_cart&product_id=' + encodeURIComponent(itemId) + '&quantity=1&variant_id=0'
    })
    .then(function(r){ return r.json(); })
    .then(function(d) {
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
        } else {
            btn.textContent = 'Try again';
            setTimeout(function(){ btn.textContent = orig; btn.disabled = false; }, 2000);
        }
    })
    .catch(function() {
        btn.textContent = orig;
        btn.disabled = false;
    });
}
</script>
</body>
</html>
