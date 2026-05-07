<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* webshop/cms_page.php
 * Generic CMS page renderer used by Webshop::cms_page($slug).
 * All CMS data is provided from the controller via $data[].
 *
 * Available variables:
 *   $cms_page            → stdClass: page_name, page_text, url, page_type, status
 *   $cms_page_sections   → array of rendered section HTMLs (if section engine active)
 *   $cms_body_html       → pre-rendered body HTML from API/section engine
 *   $cms_header_sections_html / $cms_footer_sections_html
 *   $page_banner_image_url / $page_logo_image_url
 *   $page_title, $meta_tags
 *   $webshop_settings, $uploads, $thumbs, $assets
 */

$cmsPage     = isset($cms_page) && is_object($cms_page) ? $cms_page : null;
$pageTitle   = !empty($page_title) ? (string)$page_title : ($cmsPage ? (string)$cmsPage->page_name : 'Page');
$bannerUrl   = isset($page_banner_image_url) ? trim((string)$page_banner_image_url) : '';
$logoUrl     = isset($page_logo_image_url)   ? trim((string)$page_logo_image_url)   : '';
$headerHtml  = isset($cms_header_sections_html) ? (string)$cms_header_sections_html : '';
$footerHtml  = isset($cms_footer_sections_html) ? (string)$cms_footer_sections_html : '';
$bodyHtml    = isset($cms_body_html)            ? (string)$cms_body_html            : '';

// If body HTML is empty, fall back to cms_page->page_text
if ($bodyHtml === '' && $cmsPage && !empty($cmsPage->page_text)) {
    $bodyHtml = (string)$cmsPage->page_text;
}

// Section blocks (from Webshop_section_engine / API sections[])
$sections    = isset($cms_page_sections) && is_array($cms_page_sections) ? $cms_page_sections : array();

$ws          = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$shopName    = isset($Settings->site_name) && $Settings->site_name !== '' ? $Settings->site_name : 'My Shop';
$uploadsB    = isset($uploads) ? $uploads : '';

$theme       = isset($ws->webshop_theme) ? $ws->webshop_theme : 'default';
$assetsPath  = isset($assets) ? $assets : base_url('assets/webshop/');

// Choose appropriate header/footer partials based on active theme
$themePartialDir = '';
if ($theme === 'gulfpharmacy') {
    $themePartialDir = VIEWPATH . 'plane_vanila_theme/gulfpharmacy_theme/';
} elseif ($theme === 'nw') {
    $themePartialDir = VIEWPATH . 'webshop/nw_theme/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES,'UTF-8') ?> | <?= htmlspecialchars($shopName, ENT_QUOTES,'UTF-8') ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if ($theme === 'gulfpharmacy'): ?>
    <link rel="stylesheet" href="<?= $assetsPath ?>gulfpharmacy_theme/css/main.css?ver=210616_01">
    <link rel="stylesheet" href="<?= $assetsPath ?>gulfpharmacy_theme/css/new-template.css?ver=230912_06">
    <link rel="stylesheet" href="<?= $assetsPath ?>gulfpharmacy_theme/css/common.css">
    <?php elseif ($theme === 'nw'): ?>
    <link rel="stylesheet" href="<?= $assetsPath ?>nw_theme/css/main.css">
    <?php endif; ?>
    <style>
    :root{--gp-primary:#214548;--gp-accent:#4caf89;--gp-text:#1a2e30;--gp-border:#e2e8f0;}
    *,*::before,*::after{box-sizing:border-box;}
    body{margin:0;font-family:'Inter',system-ui,sans-serif;color:var(--gp-text);background:#f8fafc;}
    .container{max-width:1280px;margin:0 auto;padding:0 20px;}
    /* Page hero */
    .cms-page-hero{background:linear-gradient(135deg,#214548,#2f6366);color:#fff;padding:48px 0;text-align:center;margin-bottom:0;}
    .cms-page-hero.has-banner{position:relative;overflow:hidden;min-height:260px;display:flex;align-items:center;justify-content:center;}
    .cms-page-hero-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.5;}
    .cms-page-hero-inner{position:relative;z-index:2;padding:40px 20px;}
    .cms-page-title{font-size:clamp(24px,4vw,44px);font-weight:800;margin:0 0 10px;text-shadow:0 2px 12px rgba(0,0,0,.3);}
    .cms-page-logo{max-height:80px;width:auto;border-radius:8px;margin-bottom:16px;display:block;margin-left:auto;margin-right:auto;}
    /* Page body */
    .cms-page-body{padding:48px 0;}
    .cms-page-content{background:#fff;border-radius:16px;padding:36px 40px;box-shadow:0 2px 12px rgba(0,0,0,.04);line-height:1.8;font-size:15px;color:#374151;}
    .cms-page-content h1,.cms-page-content h2,.cms-page-content h3{color:var(--gp-primary);font-weight:800;margin-top:28px;}
    .cms-page-content img{max-width:100%;height:auto;border-radius:10px;margin:12px 0;}
    .cms-page-content a{color:var(--gp-accent);text-decoration:underline;}
    .cms-page-content table{width:100%;border-collapse:collapse;margin:16px 0;}
    .cms-page-content td,.cms-page-content th{border:1px solid var(--gp-border);padding:10px 14px;}
    .cms-page-content th{background:#f8fafc;font-weight:700;}
    .cms-page-content blockquote{border-left:4px solid var(--gp-accent);padding:12px 20px;margin:20px 0;background:#f0faf6;border-radius:0 10px 10px 0;font-style:italic;}
    /* CMS section blocks */
    .cms-section-block{margin-bottom:28px;}
    @media(max-width:640px){.cms-page-content{padding:24px 20px;}.cms-page-body{padding:28px 0;}}
    </style>
</head>
<body>

<?php if ($themePartialDir !== '' && is_file($themePartialDir . 'header.php')): ?>
<?php require_once($themePartialDir . 'header.php'); ?>
<?php endif; ?>



<!-- Page Hero -->
<div class="cms-page-hero <?= $bannerUrl !== '' ? 'has-banner' : '' ?>">
    <?php if ($bannerUrl !== ''): ?>
    <img src="<?= htmlspecialchars($bannerUrl, ENT_QUOTES,'UTF-8') ?>" alt="<?= htmlspecialchars($pageTitle, ENT_QUOTES,'UTF-8') ?>" class="cms-page-hero-img">
    <?php endif; ?>
    <div class="cms-page-hero-inner">
        <?php if ($logoUrl !== ''): ?>
        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES,'UTF-8') ?>" alt="Page logo" class="cms-page-logo">
        <?php endif; ?>
        <h1 class="cms-page-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES,'UTF-8') ?></h1>
    </div>
</div>

<!-- CMS Dynamic Sections (product_grid, category_grid, banner, html_block …) -->
<?php if (!empty($sections)): ?>
<div class="cms-page-body">
    <div class="container">
        <?php foreach ($sections as $secHtml): ?>
        <div class="cms-section-block"><?= $secHtml ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Page Body Content (from API page_text or section_engine html_block) -->
<?php if ($bodyHtml !== '' && empty($sections)): ?>
<div class="cms-page-body">
    <div class="container">
        <div class="cms-page-content">
            <?= isset($uploadsB) && $uploadsB !== '' ? webshop_normalize_html_media_urls($bodyHtml, $uploadsB) : $bodyHtml ?>
        </div>
    </div>
</div>
<?php elseif ($bodyHtml !== ''): ?>
<!-- Additional content below sections -->
<div class="container" style="padding-bottom:40px;">
    <div class="cms-page-content"><?= isset($uploadsB) && $uploadsB !== '' ? webshop_normalize_html_media_urls($bodyHtml, $uploadsB) : $bodyHtml ?></div>
</div>
<?php endif; ?>



<?php if ($themePartialDir !== '' && is_file($themePartialDir . 'footer.php')): ?>
<?php require_once($themePartialDir . 'footer.php'); ?>
<?php endif; ?>

<?php if ($theme === 'gulfpharmacy'): ?>
<script src="<?= $assetsPath ?>gulfpharmacy_theme/js/main.js?ver=200406" defer></script>
<?php endif; ?>
<script>const baseUrl="<?= base_url('webshop') ?>";</script>
</body>
</html>
