<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$cfg      = isset($config)  && is_array($config)  ? $config  : array();
$dynData  = isset($data)    && is_array($data)     ? $data    : array();
$items    = isset($items)   && is_array($items)    ? $items
          : (isset($dynData['categories']) && is_array($dynData['categories']) ? $dynData['categories'] : array());
$title = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title']) && trim((string) $cfg['title']) !== '') {
    $title = (string) $cfg['title'];
}
$cols     = (isset($cfg['columns_desktop']) && (int)$cfg['columns_desktop'] > 0) ? (int)$cfg['columns_desktop'] : 3;
$uploadsB = isset($uploads) ? (string) $uploads : '';
$thumbsB  = isset($thumbs) ? (string) $thumbs : '';
$noImgSrc = function_exists('webshop_no_image_src') ? webshop_no_image_src($uploadsB, $thumbsB) : '';
$noImgAttr = $noImgSrc !== '' ? htmlspecialchars($noImgSrc, ENT_QUOTES, 'UTF-8') : '';
if (empty($items)) return;
$uid = 'cg' . rand(1000, 9999);
$itemCount = count($items);
$gridExtra = ($itemCount > 0 && $itemCount <= 4) ? ' gp-cg-grid--sparse' : '';
$bodyPartial = function_exists('webshop_plane_vanila_view_file')
    ? webshop_plane_vanila_view_file('components/partials/category_card_body')
    : '';
?>
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/category-card.css?ver=20260530e') ?>">
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/category-grid.css?ver=20260530e') ?>">
<section class="gp-component category-grid-section gp-category-grid-section" aria-labelledby="<?= $uid ?>">
    <?php if ($title !== ''): ?>
    <h2 class="section-title section-title--ruled gp-category-section-title" id="<?= $uid ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="gp-cg-grid gp-cg-grid-<?= $cols ?>col<?= $gridExtra ?>">
        <?php foreach ($items as $cat):
            $cat = function_exists('webshop_category_section_item') ? webshop_category_section_item($cat) : (is_object($cat) ? (array) $cat : (is_array($cat) ? $cat : array()));
            $catId  = isset($cat['id']) ? (int) $cat['id'] : 0;
            $catUrl = base_url('webshop/category_products/' . $catId);
            $learnMoreUrl = function_exists('webshop_category_learn_more_url')
                ? webshop_category_learn_more_url($cat, $catUrl)
                : $catUrl;
            $categoryNameRaw = isset($cat['name']) ? (string) $cat['name'] : '';
            $categoryName = htmlspecialchars(html_entity_decode($categoryNameRaw, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $categoryImageUrl = function_exists('webshop_category_image_src')
                ? trim((string) webshop_category_image_src($uploadsB, $thumbsB, (object) $cat))
                : '';
            $imgFinal = ($categoryImageUrl !== '') ? $categoryImageUrl : $noImgSrc;
            $isNoImage = ($imgFinal === '' || $imgFinal === $noImgSrc);
        ?>
        <div class="gp-cat-card gp-cg-card category-grid">
            <a href="<?= htmlspecialchars($catUrl, ENT_QUOTES, 'UTF-8') ?>" class="gp-cat-card__media-link">
                <div class="gp-cat-card__media">
                    <?php if ($imgFinal !== ''): ?>
                    <img src="<?= htmlspecialchars($imgFinal, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $categoryName ?>" loading="lazy" class="gp-cat-card__img<?= $isNoImage ? ' gp-cat-card__img--placeholder' : '' ?>"<?= $noImgAttr !== '' ? ' data-fallback-src="' . $noImgAttr . '"' : '' ?>>
                    <?php endif; ?>
                </div>
            </a>
            <?php if ($bodyPartial !== '' && is_file($bodyPartial)): ?>
                <?php $titleTag = 'h3'; require $bodyPartial; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<script defer src="<?= webshop_theme_assets_url('js/image-fallback.js?ver=20260529a') ?>"></script>
