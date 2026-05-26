<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$isDynamic = !empty($is_dynamic_cms_page);
$isCmsHome = isset($home_page_cms) && is_object($home_page_cms) && !empty($home_page_cms->cms_page_found);
$dynamicBanner = isset($page_banner_image_url) ? trim((string) $page_banner_image_url) : '';
$cmsBodyHtml = isset($cms_body_html) ? (string) $cms_body_html : '';
$_gpS  = isset($Settings) && is_object($Settings) ? $Settings : new stdClass();
$_gpWs = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$shopName = webshop_store_display_name($_gpS, $_gpWs);
if ($shopName === '') {
    $shopName = 'Shop';
}
$pageTitle = !empty($page_title) ? $page_title : $shopName;
$metaTagsHtml = isset($meta_tags) ? (string) $meta_tags : '';
if ($metaTagsHtml !== '') {
    $metaTagsHtml = preg_replace('/<title\b[^>]*>.*?<\/title>/is', '', $metaTagsHtml);
    if (function_exists('webshop_rewrite_root_relative_asset_urls')) {
        $metaTagsHtml = webshop_rewrite_root_relative_asset_urls($metaTagsHtml);
    }
}
$banner_image = '';
$storeBanner = '';
$_banner_items = array();
if (function_exists('webshop_website_setting_section_rows')) {
    $_banner_items = webshop_website_setting_section_rows('header');
}
if (empty($_banner_items) && !empty($this->data['website_setting'])) {
    $_banner_items = $this->data['website_setting'];
}
if (!empty($_banner_items)) {
    foreach ($_banner_items as $item) {
        $fk = function_exists('webshop_ws_row_field_key')
            ? webshop_ws_row_field_key($item)
            : (isset($item->fields) ? strtolower(trim((string) $item->fields)) : '');
        if ($fk === 'banner_image') {
            $bv = function_exists('webshop_ws_row_value_string')
                ? webshop_ws_row_value_string($item)
                : (isset($item->value) ? trim((string) $item->value) : '');
            if ($bv !== '') {
                $storeBanner = $bv;
                break;
            }
        }
    }
}
unset($_banner_items);
$cmsHomeFromApi = $isCmsHome && is_object($home_page_cms)
    && isset($home_page_cms->cms_loaded_from_api) && $home_page_cms->cms_loaded_from_api;
if ($isDynamic) {
    $banner_image = $dynamicBanner;
} elseif ($cmsHomeFromApi) {
    $banner_image = $dynamicBanner;
} elseif ($isCmsHome) {
    $banner_image = $dynamicBanner !== '' ? $dynamicBanner : $storeBanner;
} elseif (!isset($home_has_category_grid) || $home_has_category_grid) {
    $banner_image = $storeBanner;
} else {
    $banner_image = '';
}

$_gp_logo_url = '';
if (function_exists('webshop_resolve_header_logo_url') && isset($uploads)) {
    $_gp_logo_url = webshop_resolve_header_logo_url((string) $uploads, $_gpS, $_gpWs, '');
}
$hasHeroBanner = trim((string) $banner_image) !== '';
$_gp_hero_preload_src = '';
if ($hasHeroBanner) {
    $_gp_hero_preload_src = (strpos($banner_image, 'http') === 0)
        ? $banner_image
        : webshop_media_src(isset($uploads) ? (string) $uploads : '', $banner_image);
}
$_gp_lcp_preconnect_url = $_gp_hero_preload_src !== '' ? $_gp_hero_preload_src : $_gp_logo_url;

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
elseif ((!isset($home_has_category_grid) || $home_has_category_grid) && !empty($legacyWelcome['page_text'])) { $bodyHtml = (string) $legacyWelcome['page_text']; }

$cmsBodyEmbeddedAssets = '';
if ($bodyHtml !== '') {
    $preparedBody = webshop_prepare_cms_html_for_output($bodyHtml, $uploads);
    $extracted = webshop_extract_cms_embedded_assets($preparedBody);
    $bodyHtml = $extracted['html'];
    $cmsBodyEmbeddedAssets = trim((string) $extracted['style_blocks'] . "\n" . (string) $extracted['link_tags']);
}
$hasCmsHeroSlider = $bodyHtml !== '' && (stripos($bodyHtml, 'hero-slider') !== false || stripos($bodyHtml, 'id="homeHero"') !== false);
$_hb_cms_slug = isset($dynamic_cms_slug) ? trim((string) $dynamic_cms_slug) : '';
$bodyClasses = 'herbinn-marketing' . ($hasCmsHeroSlider ? ' has-cms-hero-slider' : '');
if ($_hb_cms_slug === '/products') {
    $bodyClasses .= ' hb-products-page';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $_hb_favicon = function_exists('webshop_resolve_storefront_favicon_url')
        ? webshop_resolve_storefront_favicon_url(isset($uploads) ? (string) $uploads : '')
        : '';
    ?>
    <?php if ($_hb_favicon !== '') : ?>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($_hb_favicon, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?= $metaTagsHtml ?>
    <?php if ($_gp_lcp_preconnect_url !== '' && function_exists('webshop_external_origin_preconnect_tag')): ?>
    <?= webshop_external_origin_preconnect_tag($_gp_lcp_preconnect_url) ?>

    <?php endif; ?>
    <?php if ($_gp_hero_preload_src !== ''): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($_gp_hero_preload_src, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
    <?php elseif ($_gp_logo_url !== ''): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($_gp_logo_url, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/herbinn-site.css?ver=20260526i') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/herbinn-overrides.css?ver=20260526i') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/storefront-layout.css?ver=20260526i') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css?ver=20260526h') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/header.css?ver=20260526h') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/cms-blocks.css?ver=20260526h') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/wishlist-fav.css?ver=20260526h') ?>">
    <?php if (!empty($cmsBodyEmbeddedAssets)): ?>
    <?= $cmsBodyEmbeddedAssets ?>

    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClasses, ENT_QUOTES, 'UTF-8') ?>">
<div class="home-shell">
    <?php
    $gp_header_logo_fetchpriority = ($_gp_logo_url !== '' && !$hasHeroBanner);
    require_once webshop_plane_vanila_view_file('header');
    unset($gp_header_logo_fetchpriority);
    ?>

    <?php if (!$hasCmsHeroSlider && trim((string) $banner_image) !== ''): ?>
    <section class="hero page-hero-fallback" aria-label="<?= htmlspecialchars('Promotional banner', ENT_QUOTES, 'UTF-8') ?>">
        <div class="hero-inner">
            <?php $bSrc = (strpos($banner_image, 'http') === 0) ? $banner_image : webshop_media_src($uploads, $banner_image); ?>
            <img src="<?= htmlspecialchars($bSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>" decoding="async" fetchpriority="high">
        </div>
    </section>
    <?php endif; ?>

    <main class="home-container">
        <?php
        $cmsSlug = isset($dynamic_cms_slug) ? trim((string) $dynamic_cms_slug) : '';
        $showHomeIdentityBand = !$isDynamic || $cmsSlug === '/' || $cmsSlug === '/home-page' || $cmsSlug === '/home';
        $homeTagline = '';
        if ($showHomeIdentityBand && $isCmsHome && is_object($home_page_cms)) {
            if (isset($home_page_cms->page_summary) && trim((string) $home_page_cms->page_summary) !== '') {
                $homeTagline = trim((string) $home_page_cms->page_summary);
            } elseif (isset($home_page_cms->page_description) && trim((string) $home_page_cms->page_description) !== '') {
                $homeTagline = trim((string) $home_page_cms->page_description);
            }
        }
        ?>
        <?php if ($showHomeIdentityBand && $homeTagline !== ''): ?>
        <p class="home-micro-tagline"><?= htmlspecialchars($homeTagline, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($bodyHtml !== ''): ?>
            <div class="home-cms-body gp-body-content"><?= $bodyHtml ?></div>
        <?php elseif ($isDynamic): ?>
            <section class="section section--body">
                <div class="panel panel--body gp-cms-page-head">
                    <?php if (!empty($page_title)): ?>
                    <h1 class="gp-cms-page-title"><?= htmlspecialchars((string) $page_title, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php endif; ?>
                    <?php if (!empty($cms_page_load_error)): ?>
                    <p class="gp-cms-empty-notice">CMS content is missing. On WAMP: open <a href="http://localhost/ElintOm/install_cms_schema.php">install_cms_schema.php</a> once, then add or edit the page in ElintOm → CMS Pages. Ensure <code>application/config/elintom_api.local.php</code> uses <code>http://localhost/ElintOm/</code> and your ElintOm API private key.</p>
                    <?php else: ?>
                    <p class="gp-cms-empty-notice">No content has been published for this page yet. Add HTML blocks or sections in ElintOm → CMS Pages.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($showCatGrid): ?>
            <section class="section section--categories">
                <?php
                $catGridTitle = isset($home_category_grid_title) ? trim((string) $home_category_grid_title) : '';
                if ($catGridTitle === '') {
                    $catGridTitle = 'Shop by Category';
                }
                ?>
                <div class="section-head section-head--ruled">
                    <h2><?= htmlspecialchars($catGridTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
                <div class="panel panel--catalog">
                    <?= $this->load->view(webshop_plane_vanila_view('components/category_grid'), array(
                        'items' => $catItems,
                        'uploads' => $uploads,
                        'thumbs' => $thumbs,
                        'config' => array('title' => '', 'columns_desktop' => 5),
                    ), true) ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($home_has_product_grid) && !empty($home_product_grid_items) && is_array($home_product_grid_items)): ?>
            <section class="section section--featured">
                <?php if (!empty($home_product_grid_title)): ?>
                <div class="section-head section-head--ruled">
                    <h2><?= htmlspecialchars((string) $home_product_grid_title, ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
                <?php endif; ?>
                <div class="panel panel--featured">
                    <?= $this->load->view(webshop_plane_vanila_view('components/product_showcase'), array(
                        'items' => $home_product_grid_items,
                        'uploads' => $uploads,
                        'thumbs' => $thumbs,
                        'title' => '',
                        'show_carousel' => false,
                        'show_grid' => true,
                    ), true) ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isCmsHome && !$isDynamic && !empty($legacyCert['page_text'])): ?>
            <section class="section"><div class="panel"><?= $this->load->view(webshop_plane_vanila_view('components/html_block'), array('config' => array('content' => $legacyCert['page_text']), 'uploads' => $uploads), true) ?></div></section>
        <?php endif; ?>
        <?php if ($isCmsHome && !$isDynamic && !empty($legacyUpdates['page_text'])): ?>
            <section class="section"><div class="panel"><?= $this->load->view(webshop_plane_vanila_view('components/html_block'), array('config' => array('content' => $legacyUpdates['page_text']), 'uploads' => $uploads), true) ?></div></section>
        <?php endif; ?>
    </main>

    <?php require_once webshop_plane_vanila_view_file('footer'); ?>
</div>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script defer src="<?= webshop_theme_assets_url('js/herbinn-home.js?ver=20260526h') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof AOS !== 'undefined') {
    AOS.init({ once: true, offset: 80 });
  }
});
window.HB_INDEX_CTX=<?= json_encode(array('baseUrl' => base_url('webshop'), 'assets' => $assets), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
</body>
</html>
