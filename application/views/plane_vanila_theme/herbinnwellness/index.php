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
    // Avoid duplicate <title> in head when API meta already contains one.
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
/* Hero banner only here; header logo is resolved in header.php (CMS + Storefront logo_image row via helpers). */
$cmsHomeFromApi = $isCmsHome && is_object($home_page_cms)
    && isset($home_page_cms->cms_loaded_from_api) && $home_page_cms->cms_loaded_from_api;
if ($isDynamic) {
    /* CMS page route (`cms_page`): show only this page's banner — never inherit global storefront banner */
    $banner_image = $dynamicBanner;
} elseif ($cmsHomeFromApi) {
    /* Home loaded from ElintOm API: hero uses CMS page banner image only (no global store fallback when CMS clears banner) */
    $banner_image = $dynamicBanner;
} elseif ($isCmsHome) {
    /* API unavailable — stub home_page_cms: keep legacy store banner fallback */
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
?>
<!doctype html>
<html lang="en">
<head>
    <?php if (function_exists('webshop_require_storefront_analytics_head')) { webshop_require_storefront_analytics_head(); } ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/header.css?ver=20260526f') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/cms-blocks.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/index-home.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/components.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/wishlist-fav.css?ver=20260526a') ?>">
    <?php if (function_exists('webshop_theme_has_stylesheet') && webshop_theme_has_stylesheet('css/cms-herbinn-global.css')) : ?>
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/cms-herbinn-global.css?ver=20260601a') ?>">
    <?php endif; ?>
    <?php if (function_exists('webshop_theme_has_stylesheet') && webshop_theme_has_stylesheet('css/herbinn-footer.css')) : ?>
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/herbinn-footer.css?ver=20260601b') ?>">
    <?php endif; ?>
    <?php $gp_footer_styles_in_head = true; ?>
    <?php if (function_exists('webshop_async_stylesheet_tag')): ?>
    <?= webshop_async_stylesheet_tag($assets . 'css/techmarket-font-awesome.css') ?>

    <?php endif; ?>
    <?php if (!empty($cmsBodyEmbeddedAssets)): ?>
    <?= $cmsBodyEmbeddedAssets ?>

    <?php endif; ?>
</head>
<body>
<div class="home-shell">
    <?php
    $gp_header_logo_fetchpriority = ($_gp_logo_url !== '' && !$hasHeroBanner);
    require_once webshop_plane_vanila_view_file('header');
    unset($gp_header_logo_fetchpriority);
    ?>

    <?php if (trim((string) $banner_image) !== ''): ?>
    <section class="hero" aria-label="<?= htmlspecialchars('Promotional banner', ENT_QUOTES, 'UTF-8') ?>">
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
            <section class="section section--body">
                <div class="panel panel--body gp-body-content home-cms-body"><?= $bodyHtml ?></div>
            </section>
        <?php elseif ($isDynamic): ?>
            <section class="section section--body">
                <div class="panel panel--body gp-cms-page-head">
                    <?php if (!empty($page_title)): ?>
                    <h1 class="gp-cms-page-title"><?= htmlspecialchars((string) $page_title, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php endif; ?>
                    <?php if (!empty($cms_page_load_error)): ?>
                    <p class="gp-cms-empty-notice">CMS content is missing. On WAMP: open <a href="http://localhost/ElintOm/install_cms_schema.php">install_cms_schema.php</a> once, then add or edit the page in ElintOm → CMS Pages. Ensure your host profile in <code>application/config/elintom_api_switch.php</code> uses <code>http://localhost/ElintOm/</code> and your ElintOm API private key.</p>
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
                        /* Legacy fallback only: CMS uses product_grid / product_carousel explicitly in sections */
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

    <?php $gp_footer_styles_in_head = true; require_once webshop_plane_vanila_view_file('footer'); ?>
</div>
<script defer src="<?= webshop_theme_assets_url('js/main.js?ver=200406') ?>"></script>
<script defer src="<?= webshop_theme_assets_url('js/index.js') ?>"></script>
<script>window.GP_INDEX_CTX=<?= json_encode(array('baseUrl' => base_url('webshop'), 'assets' => $assets), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;const baseUrl=window.GP_INDEX_CTX.baseUrl;const assets=window.GP_INDEX_CTX.assets;</script>
</body>
</html>
