<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Gulf Pharmacy — Wishlist full page (header/footer).
 *
 * Uses plane_vanila_theme/theme_loader when present; otherwise falls back to
 * gulfpharmacy_theme/header.php + footer.php so the route works even when
 * theme_loader files were never deployed.
 */
$_shop_name = isset($Settings->site_name) ? $Settings->site_name : 'Webshop';
$_pv_page_title = 'My Wishlist | ' . $_shop_name;
$_pv_page_robots = 'noindex';
$_pv_page_css_file = 'wishlist';
$_pv_page_body_class = 'wl-page';

$_wl_tl_open = APPPATH . 'views/plane_vanila_theme/theme_loader/page_open.php';
$_wl_gp_root = APPPATH . 'views/plane_vanila_theme/gulfpharmacy_theme/';
$CI =& get_instance();

if (is_file($_wl_tl_open)) {
    require_once $_wl_tl_open;
    ?>
<main class="pv-main-content wl-page-main">
    <?php $CI->load->view('plane_vanila_theme/gulfpharmacy_theme/Components/wishlist/wishlist_items', get_defined_vars()); ?>
</main>
<?php
    require_once APPPATH . 'views/plane_vanila_theme/theme_loader/page_close.php';
} else {
    // Fallback: same chrome as my_account / home when theme_loader is absent from disk.
    $wl_assets = isset($assets) ? $assets : base_url('assets/webshop/');
    $wl_assets = rtrim((string) $wl_assets, '/') . '/';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= htmlspecialchars($_pv_page_robots, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($_pv_page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($wl_assets, ENT_QUOTES, 'UTF-8') ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($wl_assets, ENT_QUOTES, 'UTF-8') ?>gulfpharmacy_theme/css/wishlist.css">
</head>
<body class="<?= htmlspecialchars($_pv_page_body_class, ENT_QUOTES, 'UTF-8') ?>">
<?php
if (is_file($_wl_gp_root . 'header.php')) {
    include $_wl_gp_root . 'header.php';
}
?>
<main class="pv-main-content wl-page-main">
    <?php $CI->load->view('plane_vanila_theme/gulfpharmacy_theme/Components/wishlist/wishlist_items', get_defined_vars()); ?>
</main>
<?php
if (is_file($_wl_gp_root . 'footer.php')) {
    include $_wl_gp_root . 'footer.php';
}
?>
</body>
</html>
<?php
}
