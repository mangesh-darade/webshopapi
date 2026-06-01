<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* banner.php — Hero banner / call-to-action section component */
$cfg      = isset($config) && is_array($config) ? $config : array();
$title    = isset($cfg['title'])   ? htmlspecialchars($cfg['title'],   ENT_QUOTES,'UTF-8') : '';
$subtitle = isset($cfg['content']) ? htmlspecialchars($cfg['content'], ENT_QUOTES,'UTF-8') : '';
$cta_text = isset($cfg['cta_text'])? htmlspecialchars($cfg['cta_text'],ENT_QUOTES,'UTF-8') : 'Shop Now';
$cta_link = isset($cfg['link'])    ? htmlspecialchars($cfg['link'],    ENT_QUOTES,'UTF-8') : base_url('webshop/search_products');
$uploadsB = isset($uploads) ? $uploads : '';
$imgFile  = isset($cfg['image']) && $cfg['image'] !== '' ? $cfg['image'] : '';
$imgSrc   = '';
if ($imgFile !== '') {
    $imgSrc = (strpos($imgFile,'http') === 0) ? $imgFile : ($uploadsB ? webshop_media_src($uploadsB, $imgFile) : $imgFile);
}
$bgColor  = isset($cfg['bg_color']) && $cfg['bg_color'] !== '' ? $cfg['bg_color'] : 'linear-gradient(135deg,#214548 0%,#2f6366 100%)';
$textColor = isset($cfg['text_color']) && $cfg['text_color'] !== '' ? $cfg['text_color'] : '#ffffff';
?>
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/components.css') ?>">
<div class="gp-component gp-banner-section" style="background:<?= $bgColor ?>;color:<?= $textColor ?>;">
    <?php if ($imgSrc !== ''): ?>
    <img src="<?= $imgSrc ?>" alt="<?= $title ?>" class="gp-banner-bg-img" loading="lazy">
    <div class="gp-banner-overlay"></div>
    <?php endif; ?>
    <div class="gp-banner-content">
        <?php if ($title !== ''): ?><h2 class="gp-banner-title"><?= $title ?></h2><?php endif; ?>
        <?php if ($subtitle !== ''): ?><p class="gp-banner-sub"><?= $subtitle ?></p><?php endif; ?>
        <a href="<?= $cta_link ?>" class="gp-banner-cta"><?= $cta_text ?></a>
    </div>
</div>
