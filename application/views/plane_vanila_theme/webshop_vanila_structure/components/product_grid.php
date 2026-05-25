<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* product_grid.php — CMS dynamic product grid component (PHP 5.6 compatible) */
$cfg      = isset($config)  && is_array($config)  ? $config  : array();
$dynData  = isset($data)    && is_array($data)     ? $data    : array();
$items    = isset($items)   && is_array($items)    ? $items
          : (isset($dynData['products']) && is_array($dynData['products']) ? $dynData['products'] : array());
$title    = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title']) && $cfg['title'] !== '') $title = (string) $cfg['title'];
$cols     = (isset($cfg['columns_desktop']) && (int)$cfg['columns_desktop'] > 0) ? (int)$cfg['columns_desktop'] : 4;
$uploadsB = isset($uploads) ? rtrim($uploads, '/') . '/' : base_url('assets/uploads/');
$currency = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->currency_symbol))
          ? $webshop_settings->currency_symbol : '&#8377;';
$appBasePath = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')), '/');
if ($appBasePath === '/' || $appBasePath === '\\' || $appBasePath === '.') {
    $appBasePath = '';
}
$uid = 'pg' . rand(1000, 9999);
if (empty($items)) return;
$pg_assets = isset($assets) ? $assets : base_url('assets/webshop/');
$_pg_wl_lookup = function_exists('webshop_view_wishlist_lookup')
    ? webshop_view_wishlist_lookup(isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : null)
    : (isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : array());
?>
<link rel="stylesheet" href="<?= $pg_assets ?>gulfpharmacy_theme/css/product-grid.css?ver=20260526a">
<link rel="stylesheet" href="<?= $pg_assets ?>gulfpharmacy_theme/css/wishlist-fav.css?ver=20260526a">
<section class="gp-component dynamic-product-grid" aria-labelledby="<?= $uid ?>">
    <?php if ($title !== ''): ?>
    <h2 class="cms-pg-title" id="<?= $uid ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-product-grid gp-product-grid-<?= $cols ?>col">
        <?php foreach ($items as $p):
            $p     = is_object($p) ? (array)$p : (is_array($p) ? $p : array());
            $img   = htmlspecialchars(webshop_product_image_src($uploadsB, isset($thumbs) ? $thumbs : '', $p), ENT_QUOTES, 'UTF-8');
            $name  = htmlspecialchars(isset($p['name']) ? $p['name'] : '', ENT_QUOTES, 'UTF-8');
            $_pgSettings = isset($Settings) ? $Settings : null;
            if (function_exists('webshop_product_list_card_pricing')) {
                $_pgCard = webshop_product_list_card_pricing($p, $_pgSettings);
                $price = (float) $_pgCard['price'];
                $orig = (!empty($_pgCard['mrp']) && (float) $_pgCard['mrp'] > $price) ? (float) $_pgCard['mrp'] : 0;
                $_pgVid = !empty($_pgCard['has_variants']) ? (int) $_pgCard['variant_id'] : 0;
            } else {
                $_pgVid = 0;
                $promo = isset($p['promo_price']) ? (float)$p['promo_price'] : 0;
                $base  = isset($p['price'])       ? (float)$p['price']       : 0;
                $price = ($promo > 0) ? $promo : $base;
                $orig  = ($promo > 0 && $base > $promo) ? $base : 0;
                if (!isset($_pgVid)) {
                    $_pgVid = 0;
                }
            }
            $hash  = '';
            foreach (array('hash_id', 'proudctIdHash', 'product_hash', 'id_hash', 'hash') as $hk) {
                if (isset($p[$hk]) && trim((string) $p[$hk]) !== '') {
                    $hash = trim((string) $p[$hk]);
                    break;
                }
            }
            if ($hash === '') {
                $hash = md5((string)(isset($p['id']) ? $p['id'] : ''));
            }
            $pId   = function_exists('webshop_product_list_item_id') ? webshop_product_list_item_id($p) : (isset($p['id']) ? (int) $p['id'] : 0);
            $pgPurchase = function_exists('webshop_product_list_purchase_state')
                ? webshop_product_list_purchase_state($p, true)
                : array('can_purchase' => true, 'label' => '', 'unavailable' => false);
            $pgUnavailable = !empty($pgPurchase['unavailable']);
            $pgStatusLabel = isset($pgPurchase['label']) ? (string) $pgPurchase['label'] : '';
            $pgCanPurchase = !empty($pgPurchase['can_purchase']);
            $url   = base_url('webshop/product_details/' . rawurlencode($hash));
            // Ensure URL doesn't point to ElintOm if we are in webshopapi
            if (strpos($url, '/ElintOm/') !== false && strpos($_SERVER['REQUEST_URI'], '/webshopapi/') !== false) {
                $url = str_replace('/ElintOm/', '/webshopapi/', $url);
            }
        ?>
        <div class="gp-product-card<?= $pgUnavailable ? ' gp-product-card--unavailable' : '' ?>">
            <div class="gp-product-img-wrap">
                <a href="<?= $url ?>" class="gp-product-img-link" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($img !== ''): ?>
                <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $name ?>" class="gp-product-img" loading="lazy">
                <?php else: ?>
                <div class="gp-product-img-placeholder">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
                </div>
                <?php endif; ?>
                <?php if ($orig > 0): ?>
                <span class="gp-product-badge">Sale</span>
                <?php endif; ?>
                </a>
                <?php $CI =& get_instance(); $CI->load->view('plane_vanila_theme/gulfpharmacy_theme/components/wishlist_card_button', array(
                    'product_id'      => $pId,
                    'variant_id'      => isset($_pgVid) ? $_pgVid : 0,
                    'wishlist_lookup' => $_pg_wl_lookup,
                )); ?>
            </div>
            <div class="gp-product-info">
                <?php 
                $catName = '';
                $catId = isset($p['category_id']) ? (int)$p['category_id'] : 0;
                $subId = isset($p['subcategory_id']) ? (int)$p['subcategory_id'] : 0;
                $cats = isset($categories) ? $categories : (isset($dynData['categories']) ? $dynData['categories'] : array());
                
                if ($subId > 0 && isset($cats[$catId][$subId])) {
                    $catName = is_object($cats[$catId][$subId]) ? $cats[$catId][$subId]->name : (isset($cats[$catId][$subId]['name']) ? $cats[$catId][$subId]['name'] : '');
                } elseif ($catId > 0 && isset($cats['main'][$catId])) {
                    $catName = is_object($cats['main'][$catId]) ? $cats['main'][$catId]->name : (isset($cats['main'][$catId]['name']) ? $cats['main'][$catId]['name'] : '');
                }
                if ($catName !== ''): ?>
                <div class="gp-product-cat"><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <a href="<?= $url ?>" class="gp-product-name" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>"><?= $name ?></a>
                <div class="gp-product-pricing">
                    <?php if ($price > 0): ?>
                    <span class="gp-price-current"><?= $currency ?><?= number_format($price, 2) ?></span>
                    <?php else: ?>
                    <span class="gp-price-current is-muted" title="<?= htmlspecialchars('Price not available', ENT_QUOTES, 'UTF-8') ?>">&mdash;</span>
                    <?php endif; ?>
                    <?php if ($orig > 0 && $price > 0): ?>
                    <span class="gp-price-old"><?= $currency ?><?= number_format($orig, 2) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($pgStatusLabel !== ''): ?>
                <p class="gp-stock-status gp-stock-status--unavailable" role="status"><?= htmlspecialchars($pgStatusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (!$pgCanPurchase): ?>
                <span class="gp-add-to-cart-btn is-disabled" aria-disabled="true">Add to Cart</span>
                <?php else: ?>
                <a href="<?= $url ?>" class="gp-add-to-cart-btn" data-id="<?= $pId ?>" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="gp-add-to-cart-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    Add to Cart
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
