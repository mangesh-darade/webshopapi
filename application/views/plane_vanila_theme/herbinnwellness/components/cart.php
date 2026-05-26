<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Cart Page Shell (Reusable Component)
 */
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Cart | <?= isset($Settings->site_name) ? html_escape($Settings->site_name) : 'Webshop' ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <?php if (function_exists('webshop_theme_storefront_stylesheets')) {
        webshop_theme_storefront_stylesheets(array('css/cart.css'));
    } else { ?>
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/header.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/herbinn-site.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/storefront-layout.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/cart.css') ?>">
    <?php } ?>
    <style>
    .cart-page-main{min-height:40vh}
    .cart-container{max-width:1200px;margin:40px auto;padding:0 20px;font-family:Inter,system-ui,sans-serif}
    .cart-title{font-size:2rem;margin:0 0 30px;color:var(--gp-text,#1a202c);font-weight:700;line-height:1.2}
    </style>
</head>
<body class="herbinn-storefront">
<div class="gp-site-wrapper">
    <?php webshop_require_theme_header(); ?>

    <main class="cart-page-main">
        <?php $this->load->view(webshop_plane_vanila_view('components/cart_view'), $this->data); ?>
    </main>

    <?php webshop_require_theme_footer();
    ?>
</div>
</body>
</html>

