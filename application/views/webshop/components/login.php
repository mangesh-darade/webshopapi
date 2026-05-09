<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Login Page Shell (Reusable Component)
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
    <title>Sign In | <?= isset($Settings->site_name) ? html_escape($Settings->site_name) : 'Webshop' ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
</head>
<body>
<div class="gp-site-wrapper">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
    } else {
        if (is_file(VIEWPATH . 'webshop/header.php')) {
            require_once(VIEWPATH . 'webshop/header.php');
        }
    }
    ?>

    <main class="login-page-main">
        <?php $this->load->view('webshop/components/login_form', $this->data); ?>
    </main>

    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php');
    } else {
        if (is_file(VIEWPATH . 'webshop/footer.php')) {
            require_once(VIEWPATH . 'webshop/footer.php');
        }
    }
    ?>
</div>
</body>
</html>

