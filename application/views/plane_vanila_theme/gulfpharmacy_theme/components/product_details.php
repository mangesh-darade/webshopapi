<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme)
    ? (string) $webshop_settings->webshop_theme
    : 'gulfpharmacy';
$variant = ($theme === 'nw') ? 'nw' : 'gulfpharmacy';
$pageTitle = 'Product Details';
if (isset($product) && is_array($product) && !empty($product['name'])) {
    $pageTitle = (string) $product['name'];
}
if (isset($entity_meta_title) && trim((string) $entity_meta_title) !== '') {
    $pageTitle = trim((string) $entity_meta_title);
}
$_pd_lcp_img = '';
$_pd_uploads = isset($uploads) ? (string) $uploads : '';
$_pd_thumbs = isset($thumbs) ? (string) $thumbs : '';
if (!empty($gallary_images) && is_array($gallary_images)) {
    foreach ($gallary_images as $_pd_gi) {
        $_pd_row = is_array($_pd_gi) ? $_pd_gi : (array) $_pd_gi;
        $_pd_file = isset($_pd_row['photo']) ? trim((string) $_pd_row['photo']) : '';
        if ($_pd_file === '' && isset($_pd_row['image'])) {
            $_pd_file = trim((string) $_pd_row['image']);
        }
        if ($_pd_file !== '') {
            $_pd_lcp_img = webshop_media_src($_pd_uploads, $_pd_file);
            break;
        }
    }
}
if ($_pd_lcp_img === '' && !empty($product) && is_array($product)) {
    $_pd_lcp_img = webshop_product_image_src($_pd_uploads, $_pd_thumbs, $product);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <?php if ($_pd_lcp_img !== '' && function_exists('webshop_external_origin_preconnect_tag')): ?>
    <?= webshop_external_origin_preconnect_tag($_pd_lcp_img) ?>

    <?php endif; ?>
    <?php if ($_pd_lcp_img !== ''): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($_pd_lcp_img, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/header.css?ver=20260525g') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/product-details-shell.css') ?>">
</head>
<body>
<div class="gp-site-wrapper">
    <?php
    if (function_exists('webshop_require_theme_header')) {
        webshop_require_theme_header();
    } elseif ($theme === 'nw' || $theme === 'gulfpharmacy' || $theme === 'herbinnwellness') {
        require_once webshop_plane_vanila_view_file('header');
    } else {
        require_once(VIEWPATH . 'webshop/header.php');
    }
    ?>

    <main class="gp-main">
        <?= $this->load->view(webshop_plane_vanila_view('components/theme_product_details'), array_merge($this->data, array('theme_variant' => $variant)), true) ?>
    </main>

    <?php
    if (function_exists('webshop_require_theme_footer')) {
        webshop_require_theme_footer();
    } elseif ($theme === 'nw' || $theme === 'gulfpharmacy' || $theme === 'herbinnwellness') {
        require_once webshop_plane_vanila_view_file('footer');
    } else {
        require_once(VIEWPATH . 'webshop/footer.php');
    }
    ?>
</div>

<script defer src="<?= webshop_theme_assets_url('js/main.js?ver=200406') ?>"></script>
<script defer src="<?= webshop_theme_assets_url('js/jquery.responsiveTabs.min.js') ?>"></script>
<script defer src="//cdn.jsdelivr.net/jquery.slick/1.5.9/slick.min.js"></script>
<script>window.GP_PRODUCT_DETAILS_CTX=<?= json_encode(array(
    'base_url'        => base_url('webshop/'),
    'currency_symbol' => isset($this->data['Settings']->symbol) ? $this->data['Settings']->symbol : 'Rs.',
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= webshop_theme_assets_url('js/product-details.js') ?>"></script>
</body>
</html>
