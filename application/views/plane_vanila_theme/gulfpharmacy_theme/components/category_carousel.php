<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* category_carousel.php — PHP 5.6 compatible */
$cfg      = isset($config)  && is_array($config)  ? $config  : array();
$dynData  = isset($data)    && is_array($data)     ? $data    : array();
$items    = isset($items)   && is_array($items)    ? $items
          : (isset($dynData['categories']) && is_array($dynData['categories']) ? $dynData['categories'] : array());
$title    = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title']) && $cfg['title'] !== '') $title = (string) $cfg['title'];
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
                $rawNm = isset($cat['name']) ? (string) $cat['name'] : '';
                $catName = htmlspecialchars(html_entity_decode($rawNm, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
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
.gp-cc-card{display:block;background:#fff;border:1px solid rgba(226,232,240,.9);border-radius:12px;overflow:hidden;text-decoration:none;color:inherit;transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease;box-shadow:0 2px 10px rgba(15,23,42,.05);}
.gp-cc-card:hover{transform:translateY(-3px);box-shadow:0 14px 32px rgba(15,118,110,.09),0 4px 12px rgba(15,23,42,.05);border-color:rgba(15,118,110,.2);}
.gp-cc-img-wrap{width:100%;aspect-ratio:1/1;background:#f4f6f8;overflow:hidden;border-radius:12px 12px 0 0;}
.gp-cc-img{width:100%;height:100%;object-fit:cover;object-position:center;transition:transform .35s ease;}
.gp-cc-card:hover .gp-cc-img{transform:scale(1.05);}
.gp-cc-no-img{width:100%;height:100%;display:flex;align-items:center;justify-content:center;}
.gp-cc-name{padding:14px 12px 16px;font-size:13px;font-weight:700;color:#111827;text-align:center;min-height:48px;max-height:48px;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;line-height:1.35;word-break:break-word;background:#fff;}
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
