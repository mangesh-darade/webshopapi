<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Login Page Shell (Reusable Component)
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | <?= isset($Settings->site_name) ? html_escape($Settings->site_name) : 'Webshop' ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <?php if (function_exists('webshop_theme_storefront_stylesheets')) {
        webshop_theme_storefront_stylesheets(array('css/login-form.css'));
    } else { ?>
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <?php } ?>
</head>
<body class="herbinn-storefront">
<div class="gp-site-wrapper">
    <?php webshop_require_theme_header(); ?>

    <main class="login-page-main">
        <?php $this->load->view(webshop_plane_vanila_view('components/login_form'), $this->data); ?>
    </main>

    <?php webshop_require_theme_footer(); ?>
</div>
</body>
</html>
