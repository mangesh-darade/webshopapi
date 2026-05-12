<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* category_carousel.php — PHP 5.6 compatible */
$cfg      = isset($config)  && is_array($config)  ? $config  : array();
$dynData  = isset($data)    && is_array($data)     ? $data    : array();
$items    = isset($items)   && is_array($items)    ? $items
          : (isset($dynData['categories']) && is_array($dynData['categories']) ? $dynData['categories'] : array());
$title    = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title']) && $cfg['title'] !== '') $title = (string) $cfg['title'];
if ($title === '') $title = 'Browse Categories';
$uploadsB = isset($uploads) ? rtrim($uploads, '/') . '/' : '';
$uid = 'cc' . rand(1000, 9999);
if (empty($items)) return;
?>
<section class="gp-component dynamic-category-carousel">
    <?php if ($title !== ''): ?>
    <h2 class="cms-cc-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-cc-wrap">
        <button class="gp-cc-btn gp-cc-prev" onclick="gcc_scroll('<?= $uid ?>',-1)" aria-label="Previous">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="gp-cc-carousel" id="<?= $uid ?>">
            <?php foreach ($items as $cat):
                $cat = is_object($cat) ? (array)$cat : (is_array($cat) ? $cat : array());
                $catId = isset($cat['id']) ? (int)$cat['id'] : 0;
                $catName = htmlspecialchars(isset($cat['name']) ? $cat['name'] : '', ENT_QUOTES, 'UTF-8');
                $catImgSrc = '';
                if (isset($thumbs) && isset($uploads) && $catId > 0) {
                    $catImgSrc = htmlspecialchars(webshop_category_image_src($uploads, $thumbs, (object)$cat), ENT_QUOTES, 'UTF-8');
                } elseif (!empty($cat['image'])) {
                    $catImgSrc = htmlspecialchars($uploadsB . ltrim($cat['image'], '/'), ENT_QUOTES, 'UTF-8');
                }
            ?>
            <div class="gp-cc-item">
                <a href="<?= base_url('webshop/category_products/' . $catId) ?>" class="gp-cc-card">
                    <div class="gp-cc-img-wrap">
                        <?php if ($catImgSrc !== ''): ?>
                        <img src="<?= $catImgSrc ?>" alt="<?= $catName ?>" loading="lazy" class="gp-cc-img">
                        <?php else: ?>
                        <div class="gp-cc-no-img">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="gp-cc-name"><?= $catName ?></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="gp-cc-btn gp-cc-next" onclick="gcc_scroll('<?= $uid ?>',1)" aria-label="Next">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
</section>
<style>
.cms-cc-title{font-size:24px;font-weight:800;color:#214548;margin:0 0 20px;}
.gp-cc-wrap{position:relative;display:flex;align-items:center;gap:12px;margin:0 -20px;padding:0 20px;}
.gp-cc-carousel{display:flex;gap:16px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:10px 4px 20px;scrollbar-width:none;-ms-overflow-style:none;}
.gp-cc-carousel::-webkit-scrollbar{display:none;}
.gp-cc-item{flex:0 0 190px;scroll-snap-align:start;}
.gp-cc-card{display:block;background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;text-decoration:none;color:inherit;transition:all .3s ease;}
.gp-cc-card:hover{transform:translateY(-5px);box-shadow:0 12px 28px rgba(33,69,72,.12);border-color:#214548;}
.gp-cc-img-wrap{height:130px;background:#f8fafc;overflow:hidden;}
.gp-cc-img{width:100%;height:100%;object-fit:cover;transition:transform .4s;}
.gp-cc-card:hover .gp-cc-img{transform:scale(1.06);}
.gp-cc-no-img{width:100%;height:100%;display:flex;align-items:center;justify-content:center;}
.gp-cc-name{padding:12px 10px;font-size:13px;font-weight:700;color:#214548;text-align:center;min-height:44px;display:flex;align-items:center;justify-content:center;}
.gp-cc-btn{flex-shrink:0;width:42px;height:42px;border-radius:50%;border:none;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.1);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;color:#214548;z-index:10;}
.gp-cc-btn:hover{background:#214548;color:#fff;transform:scale(1.08);}
.gp-cc-prev{position:absolute;left:5px;}
.gp-cc-next{position:absolute;right:5px;}
/* Auto-hide arrows at scroll boundaries (works on all sizes once JS toggles classes). */
.gp-cc-wrap.is-at-start .gp-cc-prev,
.gp-cc-wrap.is-at-end .gp-cc-next{opacity:0;pointer-events:none;visibility:hidden;}
@media(max-width:768px){
    .gp-cc-item{flex:0 0 165px;}
    .gp-cc-btn{width:34px;height:34px;background:rgba(255,255,255,.94);box-shadow:0 4px 14px rgba(0,0,0,.18);}
    .gp-cc-btn svg{width:16px;height:16px;}
    .gp-cc-prev{left:2px;}
    .gp-cc-next{right:2px;}
}
</style>
<script>
function gcc_scroll(id,dir){var el=document.getElementById(id);if(el)el.scrollBy({left:dir*(window.innerWidth < 600 ? 180 : 220),behavior:'smooth'});}
(function(){
    var car = document.getElementById('<?= $uid ?>');
    if (!car) return;
    var wrap = car.parentElement;
    if (!wrap) return;
    function updateEdges(){
        var atStart = car.scrollLeft <= 4;
        var atEnd = car.scrollLeft + car.clientWidth >= car.scrollWidth - 4;
        wrap.classList.toggle('is-at-start', atStart);
        wrap.classList.toggle('is-at-end', atEnd);
    }
    car.addEventListener('scroll', updateEdges, { passive: true });
    window.addEventListener('resize', updateEdges);
    setTimeout(updateEdges, 50);
})();
</script>
<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* category_carousel.php — Horizontal scrolling category carousel */
$cfg     = isset($config)  && is_array($config)  ? $config  : array();
$dynData = isset($data)    && is_array($data)     ? $data    : array();
$items   = isset($items)   && is_array($items)    ? $items   : (isset($dynData['categories']) && is_array($dynData['categories']) ? $dynData['categories'] : array());
$title   = isset($cfg['title']) && $cfg['title'] !== '' ? $cfg['title'] : 'Browse Categories';
$uploadsB = isset($uploads) ? rtrim($uploads, '/') . '/' : '';
$uid     = 'cc' . rand(1000,9999);
if (empty($items)) return;
?>
<section class="gp-component dynamic-category-carousel">
    <?php if ($title !== ''): ?>
    <h2 class="cms-cc-title"><?= htmlspecialchars($title, ENT_QUOTES,'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-carousel-wrap">
        <button class="gp-carousel-btn" onclick="gpc_scroll('<?= $uid ?>',-1)" aria-label="Previous">&#8592;</button>
        <div class="gp-carousel" id="<?= $uid ?>">
            <?php foreach ($items as $cat):
                $cat = is_object($cat) ? (array)$cat : $cat;
                $catId = isset($cat['id']) ? (int)$cat['id'] : 0;
                $catName = htmlspecialchars(isset($cat['name']) ? $cat['name'] : '', ENT_QUOTES, 'UTF-8');
                if (isset($thumbs) && isset($uploads) && $catId > 0) {
                    $catImg = htmlspecialchars(webshop_category_image_src($uploads, $thumbs, (object)$cat), ENT_QUOTES,'UTF-8');
                } elseif (!empty($cat['image'])) {
                    $catImg = htmlspecialchars($uploadsB . ltrim($cat['image'],'/'), ENT_QUOTES,'UTF-8');
                } else { $catImg = ''; }
            ?>
            <div class="gp-carousel-item">
                <a href="<?= base_url('webshop/category_products/' . $catId) ?>" class="gp-cc-card">
                    <?php if ($catImg): ?><img src="<?= $catImg ?>" alt="<?= $catName ?>" loading="lazy"><?php endif; ?>
                    <span><?= $catName ?></span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="gp-carousel-btn" onclick="gpc_scroll('<?= $uid ?>',1)" aria-label="Next">&#8594;</button>
    </div>
</section>
<style>
.gp-cc-card{display:flex;flex-direction:column;align-items:center;text-align:center;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;text-decoration:none;color:#214548;font-size:13px;font-weight:700;transition:transform .3s,box-shadow .3s;}
.gp-cc-card:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(33,69,72,.1);}
.gp-cc-card img{width:100%;height:110px;object-fit:cover;}
.gp-cc-card span{padding:10px 12px;display:block;}
.cms-cc-title{font-size:22px;font-weight:800;color:#214548;margin:0 0 16px;}
</style>
