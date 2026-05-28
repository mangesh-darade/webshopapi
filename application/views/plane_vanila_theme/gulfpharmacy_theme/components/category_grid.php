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
$itemCount = count($items);
/* ≤4 items: fixed column width, left-packed (see .gp-cg-grid--sparse in category-grid.css). */
$gridExtra = ($itemCount > 0 && $itemCount <= 4) ? ' gp-cg-grid--sparse' : '';
?>
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/category-grid.css') ?>">
<section class="gp-component category-grid-section gp-category-grid-section" aria-labelledby="<?= $uid ?>">
    <?php if ($title !== ''): ?>
    <h2 class="section-title section-title--ruled gp-category-section-title" id="<?= $uid ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-cg-grid gp-cg-grid-<?= $cols ?>col<?= $gridExtra ?>">
        <?php foreach ($items as $cat):
            $cat    = is_object($cat) ? (array)$cat : (is_array($cat) ? $cat : array());
            $catId  = isset($cat['id'])   ? (int)$cat['id']   : 0;
            $categoryNameRaw = isset($cat['name']) ? (string) $cat['name'] : '';
            $categoryName = htmlspecialchars(html_entity_decode($categoryNameRaw, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $categoryImageUrl = '';
            if (isset($thumbs) && isset($uploads) && $catId > 0) {
                $categoryImageUrl = trim((string) webshop_category_image_src($uploads, $thumbs, (object)$cat));
            } elseif (!empty($cat['image'])) {
                $categoryImageUrl = trim((string) ($uploadsB . ltrim($cat['image'], '/')));
            }
        ?>
        <a href="<?= base_url('webshop/category_products/' . $catId) ?>" class="gp-cg-card category-grid">
            <div>
                <div class="gp-cg-img-wrap">
                    <div class="gp-cg-no-img"<?= $categoryImageUrl !== '' ? ' style="display:none"' : '' ?>>
                        <div class="gp-no-image-wrap">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
                            <span class="gp-no-image-label">No image</span>
                        </div>
                    </div>
                    <?php if ($categoryImageUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($categoryImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $categoryName ?>" loading="lazy" class="gp-cg-img" data-fallback-target=".gp-cg-no-img">
                    <?php endif; ?>
                </div>
                <h5><?= $categoryName ?></h5>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<script defer src="<?= webshop_theme_assets_url('js/image-fallback.js?ver=20260528a') ?>"></script>
