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
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
</head>
<body>
<div class="gp-site-wrapper">
    <?php require_once webshop_plane_vanila_view_file('header'); ?>

    <main class="login-page-main">
        <?php $this->load->view(webshop_plane_vanila_view('components/login_form'), $this->data); ?>
    </main>

    <?php require_once webshop_plane_vanila_view_file('footer'); ?>
</div>
</body>
</html>
