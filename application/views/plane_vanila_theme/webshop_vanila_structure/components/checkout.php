<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Checkout Page Shell (Reusable Component)
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
    <title>Checkout | <?= isset($Settings->site_name) ? html_escape($Settings->site_name) : 'Webshop' ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <?php if (function_exists('webshop_csrf_pair')): ?>
    <script>window.GP_CSRF=<?= json_encode(webshop_csrf_pair(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <?php endif; ?>
</head>
<body>
<div class="gp-site-wrapper">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once webshop_plane_vanila_view_file('header');
    } else {
        // Fallback for other themes
        if (is_file(VIEWPATH . 'webshop/header.php')) {
            require_once(VIEWPATH . 'webshop/header.php');
        }
    }
    ?>

    <main class="checkout-page-main">
        <?php $this->load->view(webshop_plane_vanila_view('components/checkout_form'), $this->data); ?>
    </main>

    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once webshop_plane_vanila_view_file('footer');
    } else {
        // Fallback for other themes
        if (is_file(VIEWPATH . 'webshop/footer.php')) {
            require_once(VIEWPATH . 'webshop/footer.php');
        }
    }
    ?>
</div>
</body>
</html>


