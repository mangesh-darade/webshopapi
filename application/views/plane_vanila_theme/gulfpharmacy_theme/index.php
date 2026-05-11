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
    <style>
        :root{--pri:#0F4C81;--sec:#00A884;--bg:#F5F7FA;--text:#1A1A1A;--muted:#6b7280;--card:#fff;--line:#e5e7eb}
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--text)}
        .home-shell{min-height:100vh;background:linear-gradient(180deg,#f8fbff 0,#f5f7fa 100%)}
        .home-container{max-width:1360px;margin:0 auto;padding:0 20px}
        .home-flash{margin:16px 0;padding:12px 14px;border-radius:10px;background:#ecfeff;border:1px solid #bae6fd;color:#075985}
        .hero{position:relative;overflow:hidden;border-radius:22px;background:#0F4C81;min-height:360px;margin:18px 0 20px;box-shadow:0 14px 34px rgba(15,76,129,.22)}
        .hero img{width:100%;height:100%;min-height:360px;object-fit:cover;display:block}
        .hero-overlay{position:absolute;inset:0;background:linear-gradient(90deg,rgba(8,34,57,.75),rgba(8,34,57,.15));display:flex;align-items:center}
        .hero-content{padding:32px 36px;max-width:620px;color:#fff}
        .hero h1{margin:0 0 8px;font-size:clamp(28px,4vw,52px);font-weight:800;line-height:1.1}
        .hero p{margin:0 0 20px;font-size:17px;opacity:.95}
        .hero-actions{display:flex;gap:12px;flex-wrap:wrap}
        .btn-main,.btn-alt{padding:12px 22px;border-radius:12px;text-decoration:none;font-weight:700}
        .btn-main{background:#00A884;color:#fff}
        .btn-alt{background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4)}
        .trust-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:0 0 24px}
        .trust-item{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;box-shadow:0 6px 16px rgba(15,76,129,.06);font-size:13px}
        .trust-item strong{display:block;color:var(--pri);font-size:14px;margin-bottom:4px}
        .section{margin:18px 0 26px}
        .section-head{display:flex;justify-content:space-between;align-items:end;margin-bottom:12px}
        .section h2{margin:0;font-size:clamp(22px,2vw,30px);font-weight:800;color:var(--pri)}
        .section p{margin:0;color:var(--muted);font-size:14px}
        .panel{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px;box-shadow:0 10px 24px rgba(15,76,129,.06)}
        .panel :is(img){max-width:100%;height:auto}
        .panel .gp-cms-section img,.panel .gp-body-content img{border-radius:12px}
        .section .gp-section{padding:0}
        .section .container{max-width:none;padding:0}
        .section .gp-section-title{font-size:28px}
        .section .gp-section-divider{margin:10px auto 18px}
        .section .gp-cat-card,.section .gp-pc-card,.section .gp-grid-card{transition:transform .25s,box-shadow .25s}
        .section .gp-cat-card:hover,.section .gp-pc-card:hover,.section .gp-grid-card:hover{transform:translateY(-4px);box-shadow:0 16px 24px rgba(15,76,129,.14)}
        @media(max-width:992px){.trust-strip{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:700px){.home-container{padding:0 12px}.hero{min-height:280px;border-radius:16px}.hero img{min-height:280px}.hero-content{padding:20px}.trust-strip{grid-template-columns:1fr}}
    </style>
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
<script>const baseUrl="<?= base_url('webshop') ?>";const assets="<?= $assets ?>";</script>
</body>
</html>
