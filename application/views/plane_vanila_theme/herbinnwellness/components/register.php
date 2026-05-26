<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Register Page Shell — loads themed header/footer + register_form component
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account | <?= isset($Settings->site_name) ? html_escape($Settings->site_name) : 'Webshop' ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <?php if (function_exists('webshop_theme_storefront_stylesheets')) {
        webshop_theme_storefront_stylesheets(array('css/register-form.css'));
    } else { ?>
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/herbinn-site.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/storefront-layout.css') ?>">
    <?php } ?>
</head>
<body class="herbinn-storefront">
<div class="gp-site-wrapper">
    <?php webshop_require_theme_header(); ?>

    <main class="register-page-main">
        <?php $this->load->view(webshop_plane_vanila_view('components/register_form'), $this->data); ?>
    </main>

    <?php webshop_require_theme_footer(); ?>
</div>
</body>
</html>
