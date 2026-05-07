<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* category_grid.php — PHP 5.6 compatible */
$cfg      = isset($config)  && is_array($config)  ? $config  : array();
$dynData  = isset($data)    && is_array($data)     ? $data    : array();
$items    = isset($items)   && is_array($items)    ? $items
          : (isset($dynData['categories']) && is_array($dynData['categories']) ? $dynData['categories'] : array());
$title    = (isset($cfg['title']) && $cfg['title'] !== '') ? $cfg['title'] : 'Shop by Category';
$cols     = (isset($cfg['columns_desktop']) && (int)$cfg['columns_desktop'] > 0) ? (int)$cfg['columns_desktop'] : 5;
$uploadsB = isset($uploads) ? rtrim($uploads, '/') . '/' : '';
if (empty($items)) return;
$uid = 'cg' . rand(1000, 9999);
?>
<section class="gp-component category-grid-section" aria-labelledby="<?= $uid ?>">
    <?php if ($title !== ''): ?>
    <h2 class="section-title" id="<?= $uid ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-cg-grid gp-cg-grid-<?= $cols ?>col">
        <?php foreach ($items as $cat):
            $cat    = is_object($cat) ? (array)$cat : (is_array($cat) ? $cat : array());
            $catId  = isset($cat['id'])   ? (int)$cat['id']   : 0;
            $catName = htmlspecialchars(isset($cat['name']) ? $cat['name'] : '', ENT_QUOTES, 'UTF-8');
            $catImgSrc = '';
            if (isset($thumbs) && isset($uploads) && $catId > 0) {
                $catImgSrc = htmlspecialchars(webshop_category_image_src($uploads, $thumbs, (object)$cat), ENT_QUOTES, 'UTF-8');
            } elseif (!empty($cat['image'])) {
                $catImgSrc = htmlspecialchars($uploadsB . ltrim($cat['image'], '/'), ENT_QUOTES, 'UTF-8');
            }
        ?>
        <a href="<?= base_url('webshop/category_products/' . $catId) ?>" class="gp-cg-card category-grid">
            <div>
                <?php if ($catImgSrc !== ''): ?>
                <img src="<?= $catImgSrc ?>" alt="<?= $catName ?>" loading="lazy">
                <?php else: ?>
                <div class="gp-cg-no-img">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
                </div>
                <?php endif; ?>
                <h5><?= $catName ?></h5>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<style>
.gp-cg-grid{display:grid;gap:20px;margin-top:12px;}
.gp-cg-grid-3col{grid-template-columns:repeat(3,1fr);}
.gp-cg-grid-4col{grid-template-columns:repeat(4,1fr);}
.gp-cg-grid-5col{grid-template-columns:repeat(5,1fr);}
.gp-cg-grid-6col{grid-template-columns:repeat(6,1fr);}
@media(max-width:900px){.gp-cg-grid{grid-template-columns:repeat(3,1fr) !important;}}
@media(max-width:480px){.gp-cg-grid{grid-template-columns:repeat(2,1fr) !important;}}
.gp-cg-card{text-decoration:none;color:inherit;}
.gp-cg-card>div{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;text-align:center;transition:transform .3s,box-shadow .3s,border-color .3s;display:-webkit-box;display:flex;-webkit-box-orient:vertical;flex-direction:column;}
.gp-cg-card:hover>div{transform:translateY(-8px);box-shadow:0 20px 40px rgba(33,69,72,.1);border-color:#214548;}
.gp-cg-card img{width:100%;height:130px;object-fit:cover;transition:transform .4s;display:block;}
.gp-cg-card:hover img{transform:scale(1.06);}
.gp-cg-no-img{height:130px;background:#f8fafc;display:-webkit-box;display:flex;-webkit-box-align:center;align-items:center;-webkit-box-pack:center;justify-content:center;}
.gp-cg-card h5{padding:14px 12px;margin:0;font-size:13px;font-weight:700;color:#214548;border-top:1px solid #f1f5f9;min-height:44px;display:-webkit-box;display:flex;-webkit-box-align:center;align-items:center;-webkit-box-pack:center;justify-content:center;}
</style>
