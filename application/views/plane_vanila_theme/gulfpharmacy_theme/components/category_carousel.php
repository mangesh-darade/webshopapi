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
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/category-carousel.css?ver=20260528a') ?>">
<section class="gp-component dynamic-category-carousel">
    <?php if ($title !== ''): ?>
    <h2 class="cms-cc-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-cc-wrap">
        <button class="gp-cc-btn gp-cc-prev" data-carousel-id="<?= $uid ?>" data-direction="-1" aria-label="Previous">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <div class="gp-cc-carousel" id="<?= $uid ?>">
            <?php foreach ($items as $cat):
                $cat = is_object($cat) ? (array)$cat : (is_array($cat) ? $cat : array());
                $catId = isset($cat['id']) ? (int)$cat['id'] : 0;
                $categoryNameRaw = isset($cat['name']) ? (string) $cat['name'] : '';
                $categoryName = htmlspecialchars(html_entity_decode($categoryNameRaw, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                $categoryImageUrl = '';
                if (isset($thumbs) && isset($uploads) && $catId > 0) {
                    $categoryImageUrl = trim((string) webshop_category_image_src($uploads, $thumbs, (object)$cat));
                } elseif (!empty($cat['image'])) {
                    $categoryImageUrl = trim((string) ($uploadsB . ltrim($cat['image'], '/')));
                }
            ?>
            <div class="gp-cc-item">
                <a href="<?= base_url('webshop/category_products/' . $catId) ?>" class="gp-cc-card">
                    <div class="gp-cc-img-wrap">
                        <div class="gp-cc-no-img"<?= $categoryImageUrl !== '' ? ' style="display:none"' : '' ?>>
                            <div class="gp-no-image-wrap">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
                                <span class="gp-no-image-label">No image</span>
                            </div>
                        </div>
                        <?php if ($categoryImageUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($categoryImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $categoryName ?>" loading="lazy" class="gp-cc-img" data-fallback-target=".gp-cc-no-img">
                        <?php endif; ?>
                    </div>
                    <div class="gp-cc-name"><?= $categoryName ?></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="gp-cc-btn gp-cc-next" data-carousel-id="<?= $uid ?>" data-direction="1" aria-label="Next">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
</section>
<script defer src="<?= webshop_theme_assets_url('js/category-carousel.js?ver=20260528a') ?>"></script>
<script defer src="<?= webshop_theme_assets_url('js/image-fallback.js?ver=20260528a') ?>"></script>
