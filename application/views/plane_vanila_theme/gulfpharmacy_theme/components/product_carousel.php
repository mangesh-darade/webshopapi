<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* product_carousel.php — PHP 5.6 compatible */
$cfg     = isset($config)  && is_array($config)  ? $config  : array();
$dynData = isset($data)    && is_array($data)     ? $data    : array();
$items   = isset($items)   && is_array($items)    ? $items
         : (isset($dynData['products']) && is_array($dynData['products']) ? $dynData['products'] : array());
$title   = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title']) && $cfg['title'] !== '') $title = (string) $cfg['title'];
if ($title === '') $title = 'Best Sellers';
$uploadsB = isset($uploads) ? rtrim($uploads, '/') . '/' : '';
$uid     = 'pc' . rand(1000, 9999);
$currency = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->currency_symbol))
          ? $webshop_settings->currency_symbol : '&#8377;';
$appBasePath = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')), '/');
if ($appBasePath === '/' || $appBasePath === '\\' || $appBasePath === '.') {
    $appBasePath = '';
}
if (empty($items)) return;
$pc_assets = isset($assets) ? $assets : base_url('assets/webshop/');
?>
<link rel="stylesheet" href="<?= $pc_assets ?>gulfpharmacy_theme/css/product-carousel.css">
<section class="gp-component dynamic-product-carousel">
    <?php if ($title !== ''): ?>
    <h2 class="cms-pc-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-carousel-wrap">
        <button class="gp-carousel-btn gp-carousel-prev" onclick="gpc_scroll('<?= $uid ?>',-1)" aria-label="Previous">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="gp-carousel" id="<?= $uid ?>">
            <?php foreach ($items as $p):
                $p     = is_object($p) ? (array)$p : (is_array($p) ? $p : array());
                $img   = htmlspecialchars(webshop_product_image_src($uploadsB, isset($thumbs) ? $thumbs : '', $p), ENT_QUOTES, 'UTF-8');
                $name  = htmlspecialchars(isset($p['name']) ? $p['name'] : '', ENT_QUOTES, 'UTF-8');
                $promo = isset($p['promo_price']) ? (float)$p['promo_price'] : 0;
                $base  = isset($p['price'])       ? (float)$p['price']       : 0;
                $price = ($promo > 0) ? $promo : $base;
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
                $url   = base_url('webshop/product_details/' . rawurlencode($hash));
             
            ?>
            <div class="gp-carousel-item">
                <a href="<?= $url ?>" class="gp-pc-card" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="gp-pc-img-wrap">
                        <?php if ($img !== ''): ?>
                        <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $name ?>" loading="lazy" class="gp-pc-img">
                        <?php else: ?>
                        <div class="gp-pc-img-placeholder">
                             <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="gp-pc-info">
                        <span class="gp-product-name"><?= $name ?></span>
                        <div class="gp-pc-pricing">
                            <span class="gp-price-current"><?= $currency ?><?= number_format($price, 2) ?></span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="gp-carousel-btn gp-carousel-next" onclick="gpc_scroll('<?= $uid ?>',1)" aria-label="Next">
             <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
</section>
<script defer src="<?= $pc_assets ?>gulfpharmacy_theme/js/product-carousel.js"></script>
