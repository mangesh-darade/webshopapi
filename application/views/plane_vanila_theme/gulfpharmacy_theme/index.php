<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$isDynamic = !empty($is_dynamic_cms_page);
$isCmsHome = isset($home_page_cms) && is_object($home_page_cms);
$dynamicBanner = isset($page_banner_image_url) ? trim((string) $page_banner_image_url) : '';
$dynamicLogo = isset($page_logo_image_url) ? trim((string) $page_logo_image_url) : '';
$cmsBodyHtml = isset($cms_body_html) ? (string) $cms_body_html : '';
$shopName = isset($Settings->site_name) && $Settings->site_name !== '' ? $Settings->site_name : 'My Shop';
$pageTitle = !empty($page_title) ? $page_title : $shopName;
$metaTagsHtml = isset($meta_tags) ? (string) $meta_tags : '';
if ($metaTagsHtml !== '') {
    // Avoid duplicate <title> in head when API meta already contains one.
    $metaTagsHtml = preg_replace('/<title\b[^>]*>.*?<\/title>/is', '', $metaTagsHtml);
}
$flashMsg = $this->session->flashdata('message');

$banner_image = '';
$logo_image = '';
if (!$isDynamic && !$isCmsHome && !empty($this->data['website_setting'])) {
    foreach ($this->data['website_setting'] as $item) {
        if ($item->fields === 'banner_image' && !empty($item->value)) { $banner_image = $item->value; }
        if ($item->fields === 'logo_image' && !empty($item->value)) { $logo_image = $item->value; }
    }
}
if ($isDynamic || $isCmsHome) {
    $banner_image = $dynamicBanner;
    $logo_image = $dynamicLogo;
}

$legacySections = isset($this->data['custom_pages']['header_strip']) && is_array($this->data['custom_pages']['header_strip'])
    ? $this->data['custom_pages']['header_strip'] : array();
$legacyWelcome = $legacyCert = $legacyUpdates = null;
foreach ($legacySections as $s) {
    if ($s['page_key'] === 'homepagewelcomemessagesection') { $legacyWelcome = $s; }
    if ($s['page_key'] === 'homepagecompanycertificationsection') { $legacyCert = $s; }
    if ($s['page_key'] === 'homepagecompanyupdatessection') { $legacyUpdates = $s; }
}

$catItems = !empty($main_categories) && is_array($main_categories)
    ? $main_categories
    : (isset($this->data['categories']['main']) && is_array($this->data['categories']['main']) ? $this->data['categories']['main'] : array());
$showCatGrid = (!isset($home_has_category_grid) || $home_has_category_grid) && !empty($catItems);
$bodyHtml = '';
if (!empty($home_section_html_block)) { $bodyHtml = (string) $home_section_html_block; }
elseif (!empty($cmsBodyHtml)) { $bodyHtml = $cmsBodyHtml; }
elseif ($isCmsHome && !empty($home_page_cms->page_text)) { $bodyHtml = (string) $home_page_cms->page_text; }
elseif (!empty($legacyWelcome['page_text'])) { $bodyHtml = (string) $legacyWelcome['page_text']; }
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
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/index-home.css">
</head>
<body>
<div class="home-shell">
    <?php require_once(VIEWPATH . 'plane_vanila_theme/gulfpharmacy_theme/header.php'); ?>

    <main class="home-container">
        <?php if ($flashMsg): ?><div class="home-flash"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <?php if (trim((string) $banner_image) !== ''): ?>
        <section class="hero">
            <?php $bSrc = (strpos($banner_image, 'http') === 0) ? $banner_image : webshop_media_src($uploads, $banner_image); ?>
            <img src="<?= htmlspecialchars($bSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
        </section>
        <?php endif; ?>

        <?php if ($bodyHtml !== ''): ?>
            <section class="section">
                <div class="panel"><?= webshop_normalize_html_media_urls($bodyHtml, $uploads) ?></div>
            </section>
        <?php endif; ?>

        <?php if (!empty($home_has_product_grid) && !empty($home_product_grid_items) && is_array($home_product_grid_items)): ?>
            <section class="section">
                <div class="section-head">
                    <h2><?= isset($home_product_grid_title) && $home_product_grid_title ? htmlspecialchars($home_product_grid_title, ENT_QUOTES, 'UTF-8') : 'Featured Products' ?></h2>
                    <p>Best-selling and trending products</p>
                </div>
                <div class="panel">
                    <?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/product_showcase', array(
                        'items' => $home_product_grid_items,
                        'uploads' => $uploads,
                        'thumbs' => $thumbs,
                        'title' => '',
                        'show_carousel' => true,
                        'show_grid' => true,
                    ), true) ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($showCatGrid): ?>
            <section class="section">
                <div class="section-head">
                    <h2><?= isset($home_category_grid_title) && $home_category_grid_title !== '' ? htmlspecialchars($home_category_grid_title, ENT_QUOTES, 'UTF-8') : 'Shop by Category' ?></h2>
                    <p>Quickly explore medicine and wellness categories</p>
                </div>
                <div class="panel">
                    <?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/category_grid', array(
                        'items' => $catItems,
                        'uploads' => $uploads,
                        'thumbs' => $thumbs,
                        'config' => array('title' => '', 'columns_desktop' => 5),
                    ), true) ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!$isDynamic && !empty($legacyCert['page_text'])): ?>
            <section class="section"><div class="panel"><?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/html_block', array('config' => array('content' => $legacyCert['page_text']), 'uploads' => $uploads), true) ?></div></section>
        <?php endif; ?>
        <?php if (!$isDynamic && !empty($legacyUpdates['page_text'])): ?>
            <section class="section"><div class="panel"><?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/html_block', array('config' => array('content' => $legacyUpdates['page_text']), 'uploads' => $uploads), true) ?></div></section>
        <?php endif; ?>
    </main>

    <?php require_once(VIEWPATH . 'plane_vanila_theme/gulfpharmacy_theme/footer.php'); ?>
</div>
<script src="<?= $assets ?>gulfpharmacy_theme/js/main.js?ver=200406"></script>
<script src="<?= $assets ?>gulfpharmacy_theme/js/index.js" defer></script>
<script>window.GP_INDEX_CTX=<?= json_encode(array('baseUrl' => base_url('webshop'), 'assets' => $assets), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;const baseUrl=window.GP_INDEX_CTX.baseUrl;const assets=window.GP_INDEX_CTX.assets;</script>
</body>
</html>
