<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme
    : 'gulfpharmacy';

$pageTitle    = !empty($page_title) ? (string) $page_title : 'Page';
$metaTagsHtml = isset($meta_tags) ? (string) $meta_tags : '';
if ($metaTagsHtml !== '') {
    $metaTagsHtml = preg_replace('/<title\b[^>]*>.*?<\/title>/is', '', $metaTagsHtml);
    if (function_exists('webshop_rewrite_root_relative_asset_urls')) {
        $metaTagsHtml = webshop_rewrite_root_relative_asset_urls($metaTagsHtml);
    }
}

$bodyHtml = '';
if (!empty($cms_body_html))                                                         $bodyHtml = (string) $cms_body_html;
elseif (!empty($home_section_html_block))                                           $bodyHtml = (string) $home_section_html_block;
elseif (isset($cms_page) && is_object($cms_page) && !empty($cms_page->page_text))  $bodyHtml = (string) $cms_page->page_text;

$headerHtml = isset($cms_header_sections_html) ? (string) $cms_header_sections_html : '';
$footerHtml = isset($cms_footer_sections_html) ? (string) $cms_footer_sections_html : '';
$uploadsForCms = isset($uploads) ? $uploads : '';
if ($headerHtml !== '' && function_exists('webshop_prepare_cms_html_for_output')) {
    $headerHtml = webshop_prepare_cms_html_for_output($headerHtml, $uploadsForCms);
}
if ($footerHtml !== '' && function_exists('webshop_prepare_cms_html_for_output')) {
    $footerHtml = webshop_prepare_cms_html_for_output($footerHtml, $uploadsForCms);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?= $metaTagsHtml ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <style>
        .cms-shell{min-height:100vh;background:#f8fafc}
        .cms-container{max-width:900px;margin:0 auto;padding:40px 20px}
        .cms-title{font-size:clamp(26px,3vw,42px);font-weight:800;color:#1a2e30;margin:0 0 24px;line-height:1.15}
        .cms-body{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:32px;box-shadow:0 6px 20px rgba(0,0,0,.05);font-size:16px;line-height:1.7;color:#374151}
        .cms-body img{max-width:100%;height:auto;border-radius:10px}
        .cms-body h2,.cms-body h3{color:#1a2e30}
        .cms-body a{color:#2d6a4f}
        @media(max-width:640px){.cms-container{padding:20px 14px}.cms-body{padding:20px}}
    </style>
</head>
<body>
<div class="cms-shell">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
    }
    ?>

    <?php if ($headerHtml !== ''): ?>
        <?= $headerHtml ?>
    <?php endif; ?>

    <main class="cms-container">
        <h1 class="cms-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if ($bodyHtml !== ''): ?>
            <div class="cms-body">
                <?= function_exists('webshop_prepare_cms_html_for_output')
                    ? webshop_prepare_cms_html_for_output($bodyHtml, $uploadsForCms)
                    : (function_exists('webshop_normalize_html_media_urls')
                        ? webshop_normalize_html_media_urls($bodyHtml, $uploadsForCms)
                        : $bodyHtml) ?>
            </div>
        <?php else: ?>
            <div class="cms-body"><p>No content available for this page.</p></div>
        <?php endif; ?>
    </main>

    <?php if ($footerHtml !== ''): ?>
        <?= $footerHtml ?>
    <?php endif; ?>

    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php');
    }
    ?>
</div>
<script src="<?= $assets ?>gulfpharmacy_theme/js/main.js?ver=200406"></script>
</body>
</html>
