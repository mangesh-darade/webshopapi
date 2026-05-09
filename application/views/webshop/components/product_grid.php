<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* product_grid.php — CMS dynamic product grid component (PHP 5.6 compatible) */
$cfg      = isset($config)  && is_array($config)  ? $config  : array();
$dynData  = isset($data)    && is_array($data)     ? $data    : array();
$items    = isset($items)   && is_array($items)    ? $items
          : (isset($dynData['products']) && is_array($dynData['products']) ? $dynData['products'] : array());
$title    = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title']) && $cfg['title'] !== '') $title = (string) $cfg['title'];
if ($title === '') $title = 'Featured Products';
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
?>
<section class="gp-component dynamic-product-grid" aria-labelledby="<?= $uid ?>">
    <?php if ($title !== ''): ?>
    <h2 class="cms-pg-title" id="<?= $uid ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-product-grid gp-product-grid-<?= $cols ?>col">
        <?php foreach ($items as $p):
            $p     = is_object($p) ? (array)$p : (is_array($p) ? $p : array());
            $img   = htmlspecialchars(webshop_product_image_src($uploadsB, isset($thumbs) ? $thumbs : '', $p), ENT_QUOTES, 'UTF-8');
            $name  = htmlspecialchars(isset($p['name']) ? $p['name'] : '', ENT_QUOTES, 'UTF-8');
            $promo = isset($p['promo_price']) ? (float)$p['promo_price'] : 0;
            $base  = isset($p['price'])       ? (float)$p['price']       : 0;
            $price = ($promo > 0) ? $promo : $base;
            $orig  = ($promo > 0 && $base > $promo) ? $base : 0;
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
            $pId   = isset($p['id']) ? (int)$p['id'] : 0;
            $url   = base_url('webshop/product_details/' . rawurlencode($hash));
            // Ensure URL doesn't point to ElintOm if we are in webshopapi
            if (strpos($url, '/ElintOm/') !== false && strpos($_SERVER['REQUEST_URI'], '/webshopapi/') !== false) {
                $url = str_replace('/ElintOm/', '/webshopapi/', $url);
            }

        ?>
        <div class="gp-product-card">
            <a href="<?= $url ?>" class="gp-product-img-wrap" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">
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
                    <span class="gp-price-current"><?= $currency ?><?= number_format($price, 2) ?></span>
                    <?php if ($orig > 0): ?>
                    <span class="gp-price-old"><?= $currency ?><?= number_format($orig, 2) ?></span>
                    <?php endif; ?>
                </div>
                <a href="<?= $url ?>" class="gp-add-to-cart-btn" data-id="<?= $pId ?>" data-product-hash="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;vertical-align:middle;display:inline-block;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    Add to Cart
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<style>
.gp-product-grid{display:grid;gap:24px;margin-top:12px;}
.gp-product-grid-1col{grid-template-columns:repeat(1,1fr);}
.gp-product-grid-2col{grid-template-columns:repeat(2,1fr);}
.gp-product-grid-3col{grid-template-columns:repeat(3,1fr);}
.gp-product-grid-4col{grid-template-columns:repeat(4,1fr);}
.gp-product-grid-5col{grid-template-columns:repeat(5,1fr);}
@media(max-width:1024px){.gp-product-grid{grid-template-columns:repeat(3,1fr) !important;}}
@media(max-width:768px){.gp-product-grid{grid-template-columns:repeat(2,1fr) !important;gap:16px;}}
@media(max-width:480px){.gp-product-grid{grid-template-columns:1fr !important;}}
.gp-product-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;transition:all .3s ease;display:flex;flex-direction:column;position:relative;}
.gp-product-card:hover{transform:translateY(-8px);box-shadow:0 20px 40px rgba(33,69,72,.12);border-color:#4caf89;}
.gp-product-img-wrap{display:block;overflow:hidden;height:240px;position:relative;background:#f8fafc;}
.gp-product-img{width:100%;height:100%;object-fit:contain;transition:transform .5s;}
.gp-product-card:hover .gp-product-img{transform:scale(1.08);}
.gp-product-img-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f1f5f9;}
.gp-product-badge{position:absolute;top:12px;left:12px;background:#ef4444;color:#fff;font-size:11px;font-weight:800;padding:4px 10px;border-radius:6px;text-transform:uppercase;letter-spacing:0.5px;box-shadow:0 4px 12px rgba(239,68,68,0.3);z-index:2;}
.gp-product-info{padding:20px;display:flex;flex-direction:column;gap:6px;flex:1;}
.gp-product-cat{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;}
.gp-product-name{font-size:15px;font-weight:700;color:#1a2e30;text-decoration:none;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:42px;}
.gp-product-name:hover{color:#4caf89;}
.gp-product-pricing{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.gp-price-current{font-size:18px;font-weight:800;color:#214548;}
.gp-price-old{font-size:14px;color:#94a3b8;text-decoration:line-through;}
.gp-add-to-cart-btn{margin-top:auto;background:#214548;color:#fff;text-align:center;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:700;text-decoration:none;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.gp-add-to-cart-btn:hover{background:#4caf89;box-shadow:0 8px 20px rgba(76,175,137,0.3);}
.cms-pg-title{font-size:24px;font-weight:800;color:#214548;margin:0 0 20px;letter-spacing:-0.5px;}
</style>
