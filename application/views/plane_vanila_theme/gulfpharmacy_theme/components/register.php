<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Register Page Shell — loads themed header/footer + register_form component
 */
$_theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';

$_themed_header = VIEWPATH . 'plane_vanila_theme/' . $_theme . '_theme/header.php';
$_themed_footer = VIEWPATH . 'plane_vanila_theme/' . $_theme . '_theme/footer.php';
$_has_themed_header = is_file($_themed_header);
$_has_themed_footer = is_file($_themed_footer);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account | <?= isset($Settings->site_name) ? html_escape($Settings->site_name) : 'Webshop' ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
</head>
<body>
<div class="gp-site-wrapper">
    <?php if ($_has_themed_header): ?>
        <?php require_once $_themed_header; ?>
    <?php elseif (is_file(VIEWPATH . 'webshop/header.php')): ?>
        <?php require_once VIEWPATH . 'webshop/header.php'; ?>
    <?php endif; ?>

    <main class="register-page-main">
        <?php $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/register_form', $this->data); ?>
    </main>

    <?php if ($_has_themed_footer): ?>
        <?php require_once $_themed_footer; ?>
    <?php elseif (is_file(VIEWPATH . 'webshop/footer.php')): ?>
        <?php require_once VIEWPATH . 'webshop/footer.php'; ?>
    <?php endif; ?>
</div>
</body>
</html>
