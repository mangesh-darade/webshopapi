<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Gulf Pharmacy — Wishlist full page (header/footer).
 */
$_shop_name = isset($Settings->site_name) ? $Settings->site_name : 'Webshop';
$_pv_page_title = 'My Wishlist | ' . $_shop_name;
$_pv_page_robots = 'noindex';
$_pv_page_body_class = 'wl-page';
$_wl_js_ver = '20260526g';

$wl_assets = isset($assets) ? $assets : base_url('assets/webshop/');
$wl_assets = rtrim((string) $wl_assets, '/') . '/';
$_wl_gp_root = webshop_plane_vanila_views_apppath();
$CI =& get_instance();

$wishlist_items = isset($wishlist['items']) && is_array($wishlist['items']) ? $wishlist['items'] : array();
$_wl_item_count = count($wishlist_items);
if ($_wl_item_count > 0) {
    $wishlist_count = $_wl_item_count;
}

$_wl_tl_open = APPPATH . 'views/plane_vanila_theme/theme_loader/page_open.php';

if (is_file($_wl_tl_open)) {
    require_once $_wl_tl_open;
    ?>
<main class="pv-main-content wl-page-main">
    <?php $CI->load->view(webshop_plane_vanila_view('components/wishlist/wishlist_items'), get_defined_vars()); ?>
</main>
<?php
    require_once APPPATH . 'views/plane_vanila_theme/theme_loader/page_close.php';
} else {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= htmlspecialchars($_pv_page_robots, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($_pv_page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if (function_exists('webshop_theme_storefront_stylesheets')) {
        webshop_theme_storefront_stylesheets(array('css/wishlist.css?ver=' . $_wl_js_ver));
    } else { ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/common.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/header.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/herbinn-site.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/storefront-layout.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/wishlist.css?ver=' . $_wl_js_ver), ENT_QUOTES, 'UTF-8') ?>">
    <?php } ?>
    <?php if (function_exists('webshop_csrf_pair')): ?>
    <script>window.GP_CSRF=<?= json_encode(webshop_csrf_pair(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <?php endif; ?>
</head>
<body class="herbinn-storefront <?= htmlspecialchars($_pv_page_body_class, ENT_QUOTES, 'UTF-8') ?>">
<div class="gp-site-wrapper">
<?php webshop_require_theme_header(); ?>
<main class="wl-page-main">
    <?php $CI->load->view(webshop_plane_vanila_view('components/wishlist/wishlist_items'), get_defined_vars()); ?>
</main>
<?php webshop_require_theme_footer(); ?>
</div>
</body>
</html>
<?php
}
