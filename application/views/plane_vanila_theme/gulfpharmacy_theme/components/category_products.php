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
$currentPage   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage       = 12;
$totalPages    = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 1;

$uploadsBase   = isset($uploads) ? (string) $uploads : '';
$thumbsBase    = isset($thumbs)  ? (string) $thumbs  : '';
$symbol        = isset($Settings->symbol) ? $Settings->symbol : '';
$shopName      = isset($Settings->site_name) ? $Settings->site_name : 'Shop';

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
    <style>
    /* ── Layout ───────────────────────────────────────────── */
    *,*::before,*::after{box-sizing:border-box}
    .cp-shell{background:#f0f2f2;min-height:100vh}
    .cp-inner{max-width:1440px;margin:0 auto;padding:16px 14px;display:flex;gap:18px;align-items:flex-start}
    /* ── Breadcrumb ──────────────────────────────────────── */
    .cp-breadcrumb{background:#fff;padding:8px 14px;font-size:13px;color:#555;border-bottom:1px solid #e3e6e6}
    .cp-breadcrumb a{color:#007185;text-decoration:none}.cp-breadcrumb a:hover{text-decoration:underline;color:#c7511f}
    .cp-breadcrumb span{margin:0 4px;color:#999}
    /* ── Left sidebar ────────────────────────────────────── */
    .cp-sidebar{width:220px;flex-shrink:0}
    .cp-sidebar-box{background:#fff;border:1px solid #ddd;border-radius:4px;padding:14px;margin-bottom:12px}
    .cp-sidebar-title{font-size:16px;font-weight:700;color:#0F1111;margin:0 0 10px;padding-bottom:8px;border-bottom:1px solid #e8e8e8}
    .cp-sidebar-link{display:block;padding:5px 0;font-size:13px;color:#007185;text-decoration:none;border-radius:3px}
    .cp-sidebar-link:hover{color:#c7511f;text-decoration:underline}
    .cp-sidebar-link.active{font-weight:700;color:#c7511f}
    /* ── Main content ─────────────────────────────────────── */
    .cp-main{flex:1;min-width:0}
    .cp-header-bar{background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px 16px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
    .cp-header-title{font-size:20px;font-weight:700;color:#0F1111;margin:0}
    .cp-header-count{font-size:13px;color:#555}
    /* ── Product grid ─────────────────────────────────────── */
    .cp-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
    /* ── Product card ─────────────────────────────────────── */
    .pc-card{background:#fff;border:1px solid #ddd;border-radius:6px;display:flex;flex-direction:column;position:relative;transition:box-shadow .15s,border-color .15s;overflow:hidden}
    .pc-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.12);border-color:#adb5bd}
    .pc-img-wrap{width:100%;padding-top:100%;position:relative;background:#f7f8fa;overflow:hidden}
    .pc-img-wrap img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;padding:10px;transition:transform .25s}
    .pc-card:hover .pc-img-wrap img{transform:scale(1.04)}
    .pc-no-img{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:48px}
    .pc-badge-wrap{position:absolute;top:8px;left:8px;display:flex;flex-direction:column;gap:4px;z-index:2}
    .pc-badge{font-size:10px;font-weight:700;padding:2px 6px;border-radius:3px;line-height:1.4}
    .pc-badge-discount{background:#CC0C39;color:#fff}
    .pc-badge-cat{background:#e8f0fe;color:#1a73e8}
    .pc-body{padding:10px 12px;flex:1;display:flex;flex-direction:column}
    .pc-cat{font-size:11px;font-weight:700;color:#c7511f;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
    .pc-name{font-size:13px;font-weight:400;color:#0F1111;line-height:1.4;margin:0 0 8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .pc-price-row{display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;margin-bottom:4px}
    .pc-price{font-size:18px;font-weight:700;color:#B12704}
    .pc-mrp{font-size:12px;color:#888;text-decoration:line-through}
    .pc-save{font-size:11px;color:#007600;font-weight:600}
    .pc-price-zero{font-size:14px;color:#999;font-style:italic}
    .pc-rating{display:flex;align-items:center;gap:4px;margin-bottom:8px}
    .pc-stars{color:#FFA41C;font-size:13px;letter-spacing:-1px}
    .pc-actions{margin-top:auto;display:flex;flex-direction:column;gap:6px}
    .pc-btn{width:100%;padding:8px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:background .15s,box-shadow .15s;text-align:center;text-decoration:none;display:block}
    .pc-btn-cart{background:#FFD814;color:#0F1111}.pc-btn-cart:hover{background:#F7CA00;box-shadow:0 2px 6px rgba(0,0,0,.15)}
    .pc-btn-buy{background:#FFA41C;color:#0F1111}.pc-btn-buy:hover{background:#FA8900;box-shadow:0 2px 6px rgba(0,0,0,.15)}
    .pc-unavailable{font-size:11px;color:#c7511f;text-align:center;padding:4px 0}
    /* ── Empty state ─────────────────────────────────────── */
    .cp-empty{background:#fff;border:1px solid #ddd;border-radius:6px;padding:48px 24px;text-align:center;color:#666}
    .cp-empty-icon{font-size:48px;margin-bottom:12px}
    /* ── Pagination ──────────────────────────────────────── */
    .cp-pagination{display:flex;gap:6px;align-items:center;justify-content:center;margin-top:22px;flex-wrap:wrap}
    .cp-page-btn{padding:6px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;background:#fff;color:#007185;text-decoration:none;cursor:pointer;transition:background .15s}
    .cp-page-btn:hover{background:#f0f2f2;border-color:#aaa}
    .cp-page-btn.active{background:#232F3E;color:#fff;border-color:#232F3E;cursor:default}
    .cp-page-btn.disabled{color:#aaa;pointer-events:none}
    /* ── Responsive ──────────────────────────────────────── */
    @media(max-width:1100px){.cp-grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:900px){.cp-sidebar{display:none}.cp-inner{padding:10px}}
    @media(max-width:700px){.cp-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:420px){.cp-grid{grid-template-columns:1fr}}
    </style>
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
    <nav class="cp-breadcrumb">
        <a href="<?= base_url('webshop') ?>">Home</a>
        <span>›</span>
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
                    <p style="font-size:18px;font-weight:600;margin:0 0 8px">No products found</p>
                    <p style="margin:0 0 16px;font-size:14px">This category has no products yet.</p>
                    <a href="<?= base_url('webshop') ?>" style="color:#007185;font-size:14px">← Back to Home</a>
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
                ?>
                    <div class="pc-card">
                        <!-- Image -->
                        <a href="<?= $detailUrl ?>" style="display:block">
                            <div class="pc-img-wrap">
                                <?php if ($imgSrc !== ''): ?>
                                    <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none';this.parentNode.querySelector('.pc-no-img').style.display='flex'">
                                    <span class="pc-no-img" style="display:none">🖼️</span>
                                <?php else: ?>
                                    <span class="pc-no-img">🖼️</span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <!-- Badges -->
                        <div class="pc-badge-wrap">
                            <?php if ($discount >= 5): ?>
                                <span class="pc-badge pc-badge-discount">-<?= $discount ?>%</span>
                            <?php endif; ?>
                        </div>

                        <!-- Body -->
                        <div class="pc-body">
                            <?php if ($catLabel): ?>
                                <div class="pc-cat"><?= $catLabel ?></div>
                            <?php endif; ?>

                            <a href="<?= $detailUrl ?>" style="text-decoration:none">
                                <p class="pc-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                            </a>

                            <!-- Dynamic Ratings -->
                            <div class="pc-rating">
                                <?php 
                                $rAvg = isset($row['ratings_avarage']) ? (float)$row['ratings_avarage'] : 0;
                                $rCount = isset($row['ratings_count']) ? (int)$row['ratings_count'] : 0;
                                ?>
                                <span class="pc-stars">
                                    <?= str_repeat('★', (int) round($rAvg)) ?><?= str_repeat('☆', 5 - (int) round($rAvg)) ?>
                                </span>
                                <span style="font-size:11px;color:#007185">(<?= $rCount ?>)</span>
                            </div>

                            <!-- Price -->
                            <div class="pc-price-row">
                                <?php if ($price > 0): ?>
                                    <span class="pc-price"><?= $symbol ?> <?= number_format($price, 2) ?></span>
                                    <?php if ($mrp > 0 && $mrp > $price): ?>
                                        <span class="pc-mrp"><?= $symbol ?> <?= number_format($mrp, 2) ?></span>
                                        <span class="pc-save">Save <?= $discount ?>%</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="pc-price-zero">Price on request</span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="pc-actions">
                                <?php if ($isActive === 'true' || $isActive === true || $isActive === 1): ?>
                                    <button class="pc-btn pc-btn-cart"
                                            onclick="wsAddToCart('<?= $itemId ?>', '<?= $hash ?>', this)"
                                            data-item-id="<?= $itemId ?>" data-hash="<?= $hash ?>">
                                        🛒 Add to Cart
                                    </button>
                                    <a href="<?= $detailUrl ?>" class="pc-btn pc-btn-buy">Buy Now</a>
                                <?php else: ?>
                                    <div class="pc-unavailable">Currently unavailable</div>
                                    <a href="<?= $detailUrl ?>" class="pc-btn" style="background:#e8e8e8;color:#555">View Details</a>
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
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Adding…';
    fetch('<?= base_url('webshop/webshop_request') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_to_cart&product_id=' + encodeURIComponent(itemId) + '&quantity=1&variant_id=0'
    })
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (d && (d.status === 'SUCCESS' || d.success)) {
            btn.innerHTML = '✅ Added!';
            btn.style.background = '#34a853';
            btn.style.color = '#fff';
            setTimeout(function(){ btn.innerHTML = orig; btn.style.background = ''; btn.style.color = ''; btn.disabled = false; }, 1800);
            // Update cart count badge if present
            var badge = document.querySelector('.gp-cart-count, .cart-count, [data-cart-count]');
            if (badge && d.cart_count !== undefined) {
                badge.textContent = d.cart_count;
                badge.style.display = d.cart_count > 0 ? '' : 'none';
            }
        } else {
            btn.innerHTML = '⚠️ Try Again';
            setTimeout(function(){ btn.innerHTML = orig; btn.disabled = false; }, 2000);
        }
    })
    .catch(function() {
        btn.innerHTML = orig;
        btn.disabled = false;
    });
}
</script>
</body>
</html>
