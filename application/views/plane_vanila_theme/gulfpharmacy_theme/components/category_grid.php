<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* category_grid.php — PHP 5.6 compatible */
$cfg      = isset($config)  && is_array($config)  ? $config  : array();
$dynData  = isset($data)    && is_array($data)     ? $data    : array();
$items    = isset($items)   && is_array($items)    ? $items
          : (isset($dynData['categories']) && is_array($dynData['categories']) ? $dynData['categories'] : array());
$title = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title']) && trim((string) $cfg['title']) !== '') {
    $title = (string) $cfg['title'];
}
$cols     = (isset($cfg['columns_desktop']) && (int)$cfg['columns_desktop'] > 0) ? (int)$cfg['columns_desktop'] : 5;
$uploadsB = isset($uploads) ? rtrim($uploads, '/') . '/' : '';
if (empty($items)) return;
$uid = 'cg' . rand(1000, 9999);
$cg_assets = isset($assets) ? $assets : base_url('assets/webshop/');
?>
<link rel="stylesheet" href="<?= $cg_assets ?>gulfpharmacy_theme/css/category-grid.css">
<section class="gp-component category-grid-section" aria-labelledby="<?= $uid ?>">
    <?php if ($title !== ''): ?>
    <h2 class="section-title" id="<?= $uid ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-cg-grid gp-cg-grid-<?= $cols ?>col">
        <?php foreach ($items as $cat):
            $cat    = is_object($cat) ? (array)$cat : (is_array($cat) ? $cat : array());
            $catId  = isset($cat['id'])   ? (int)$cat['id']   : 0;
            $rawNm = isset($cat['name']) ? (string) $cat['name'] : '';
            $catName = htmlspecialchars(html_entity_decode($rawNm, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
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
                <div class="gp-cg-img-wrap">
                    <img src="<?= $catImgSrc ?>" alt="<?= $catName ?>" loading="lazy" class="gp-cg-img">
                </div>
                <?php else: ?>
                <div class="gp-cg-img-wrap gp-cg-no-img">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
                </div>
                <?php endif; ?>
                <h5><?= $catName ?></h5>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
