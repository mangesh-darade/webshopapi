<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* product_carousel.php — PHP 5.6 compatible */
$cfg     = isset($config)  && is_array($config)  ? $config  : array();
$dynData = isset($data)    && is_array($data)     ? $data    : array();
$items   = isset($items)   && is_array($items)    ? $items
         : (isset($dynData['products']) && is_array($dynData['products']) ? $dynData['products'] : array());
$title   = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title']) && $cfg['title'] !== '') $title = (string) $cfg['title'];
$uploadsB = isset($uploads) ? rtrim($uploads, '/') . '/' : '';
$uid     = 'pc' . rand(1000, 9999);
$currency = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->currency_symbol))
          ? $webshop_settings->currency_symbol : '&#8377;';
if (empty($items)) return;
$_pc_wl_lookup = function_exists('webshop_view_wishlist_lookup')
    ? webshop_view_wishlist_lookup(isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : null)
    : (isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : array());
$CI =& get_instance();
?>
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/product-carousel.css?ver=20260526a') ?>">
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/wishlist-fav.css?ver=20260526a') ?>">
<section class="gp-component dynamic-product-carousel">
    <?php if ($title !== ''): ?>
    <h2 class="cms-pc-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-carousel-wrap">
        <button type="button" class="gp-carousel-btn gp-carousel-prev" data-carousel-id="<?= $uid ?>" data-direction="-1" aria-label="Previous">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="gp-carousel" id="<?= $uid ?>">
            <?php foreach ($items as $p):
                $p     = is_object($p) ? (array)$p : (is_array($p) ? $p : array());
                $productImageUrl = trim((string) webshop_product_image_src($uploadsB, isset($thumbs) ? $thumbs : '', $p));
                $productNameRaw  = isset($p['name']) ? (string) $p['name'] : '';
                $productNameEsc  = htmlspecialchars($productNameRaw, ENT_QUOTES, 'UTF-8');
                $_pcSettings = isset($Settings) ? $Settings : null;
                if (function_exists('webshop_product_list_card_pricing')) {
                    $_pcCard = webshop_product_list_card_pricing($p, $_pcSettings);
                    $price = (float) $_pcCard['price'];
                    $orig = (!empty($_pcCard['mrp']) && (float) $_pcCard['mrp'] > $price) ? (float) $_pcCard['mrp'] : 0;
                    $_pcVid = !empty($_pcCard['has_variants']) ? (int) $_pcCard['variant_id'] : 0;
                } else {
                    $_pcVid = 0;
                    $promo = isset($p['promo_price']) ? (float)$p['promo_price'] : 0;
                    $base  = isset($p['price'])       ? (float)$p['price']       : 0;
                    $price = ($promo > 0) ? $promo : $base;
                    $orig  = ($promo > 0 && $base > $promo) ? $base : 0;
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
                $pId = function_exists('webshop_product_list_item_id') ? webshop_product_list_item_id($p) : (isset($p['id']) ? (int) $p['id'] : 0);
                $pcPurchase = function_exists('webshop_product_list_purchase_state')
                    ? webshop_product_list_purchase_state($p, true)
                    : array('can_purchase' => true, 'label' => '', 'unavailable' => false);
                $pcUnavailable = !empty($pcPurchase['unavailable']);
                $pcStatusLabel = isset($pcPurchase['label']) ? (string) $pcPurchase['label'] : '';
                $pcCanPurchase = !empty($pcPurchase['can_purchase']);
                $url   = base_url('webshop/product_details/' . rawurlencode($hash));
                if (strpos($url, '/ElintOm/') !== false && strpos($_SERVER['REQUEST_URI'], '/webshopapi/') !== false) {
                    $url = str_replace('/ElintOm/', '/webshopapi/', $url);
                }
            ?>
            <div class="gp-carousel-item">
                <article class="gp-pc-card<?= $pcUnavailable ? ' gp-pc-card--unavailable' : '' ?>">
                    <div class="gp-pc-img-wrap">
                        <a href="<?= $url ?>" class="gp-pc-img-link" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="gp-pc-img-placeholder"<?= $productImageUrl !== '' ? ' style="display:none"' : '' ?>>
                                <div class="gp-no-image-wrap">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
                                    <span class="gp-no-image-label">No image</span>
                                </div>
                            </div>
                            <?php if ($productImageUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($productImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $productNameEsc ?>" loading="lazy" class="gp-pc-img" data-fallback-target=".gp-pc-img-placeholder">
                            <?php endif; ?>
                        </a>
                        <?php $CI->load->view(webshop_plane_vanila_view('components/wishlist_card_button'), array(
                            'product_id'      => $pId,
                            'variant_id'      => isset($_pcVid) ? $_pcVid : 0,
                            'wishlist_lookup' => $_pc_wl_lookup,
                        )); ?>
                    </div>
                    <div class="gp-pc-info">
                        <a href="<?= $url ?>" class="gp-product-name" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>"><?= $productNameEsc ?></a>
                        <div class="gp-pc-pricing">
                            <?php if ($price > 0): ?>
                            <span class="gp-price-current"><?= $currency ?><?= number_format($price, 2) ?></span>
                            <?php else: ?>
                            <span class="gp-price-current is-muted">&mdash;</span>
                            <?php endif; ?>
                            <?php if ($orig > 0 && $price > 0): ?>
                            <span class="gp-price-old"><?= $currency ?><?= number_format($orig, 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($pcStatusLabel !== ''): ?>
                        <p class="gp-pc-stock-status<?= $pcUnavailable ? ' gp-pc-stock-status--unavailable' : '' ?>" role="status"><?= htmlspecialchars($pcStatusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if (!$pcCanPurchase): ?>
                        <span class="gp-pc-add-btn is-disabled" aria-disabled="true">Add to Cart</span>
                        <?php else: ?>
                        <a href="<?= $url ?>" class="gp-pc-add-btn" data-id="<?= $pId ?>" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">Add to Cart</a>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="gp-carousel-btn gp-carousel-next" data-carousel-id="<?= $uid ?>" data-direction="1" aria-label="Next">
             <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
</section>
<script defer src="<?= webshop_theme_assets_url('js/product-carousel.js') ?>"></script>
<script defer src="<?= webshop_theme_assets_url('js/image-fallback.js?ver=20260528a') ?>"></script>
