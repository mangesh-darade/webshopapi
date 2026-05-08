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
?>
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
<style>
.gp-carousel-wrap{position:relative;display:flex;align-items:center;gap:12px;margin:0 -20px;padding:0 20px;}
.gp-carousel{display:flex;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:12px 4px 24px;scrollbar-width:none;-ms-overflow-style:none;}
.gp-carousel::-webkit-scrollbar{display:none;}
.gp-carousel-item{flex:0 0 240px;scroll-snap-align:start;}
@media(max-width:600px){.gp-carousel-item{flex:0 0 190px;}}
.gp-pc-card{display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;text-decoration:none;color:inherit;transition:all .3s ease;height:100%;}
.gp-pc-card:hover{transform:translateY(-5px);box-shadow:0 12px 32px rgba(33,69,72,.15);border-color:#4caf89;}
.gp-pc-img-wrap{height:160px;overflow:hidden;background:#f8fafc;}
.gp-pc-img{width:100%;height:100%;object-fit:contain;transition:transform .4s;}
.gp-pc-card:hover .gp-pc-img{transform:scale(1.08);}
.gp-pc-img-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;}
.gp-pc-info{padding:16px;display:flex;flex-direction:column;gap:8px;}
.gp-carousel-btn{flex-shrink:0;width:42px;height:42px;border-radius:50%;border:none;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.1);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;color:var(--gp-primary);z-index:10;}
.gp-carousel-btn:hover{background:var(--gp-primary);color:#fff;transform:scale(1.1);}
.gp-carousel-prev{position:absolute;left:5px;}
.gp-carousel-next{position:absolute;right:5px;}
.cms-pc-title{font-size:24px;font-weight:800;color:#214548;margin:0 0 20px;}
.gp-pc-pricing{display:flex;align-items:center;gap:6px;}
@media(max-width:768px){.gp-carousel-btn{display:none;}}
</style>
<script>
function gpc_scroll(id,dir){var el=document.getElementById(id);if(el)el.scrollBy({left:dir*(window.innerWidth < 600 ? 200 : 260),behavior:'smooth'});}
</script>
