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
