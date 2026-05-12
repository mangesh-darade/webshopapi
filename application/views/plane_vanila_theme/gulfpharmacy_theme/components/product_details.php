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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/product-details-shell.css">
</head>
<body>
<div class="gp-site-wrapper">
    <?php 
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
    } else {
        require_once(VIEWPATH . 'webshop/header.php');
    }
    ?>

    <main class="gp-main">
        <?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/theme_product_details', array_merge($this->data, array('theme_variant' => $variant)), true) ?>
    </main>

    <?php 
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php');
    } else {
        require_once(VIEWPATH . 'webshop/footer.php');
    }
    ?>
</div>

<script src="<?= $assets ?>gulfpharmacy_theme/js/jquery.min.js"></script>
<script src="<?= $assets ?>gulfpharmacy_theme/js/main.js"></script>
<script src="<?= $assets ?>gulfpharmacy_theme/js/jquery.responsiveTabs.min.js"></script>
<script src="//cdn.jsdelivr.net/jquery.slick/1.5.9/slick.min.js"></script>
<script>window.GP_PRODUCT_DETAILS_CTX=<?= json_encode(array(
    'base_url'        => base_url('webshop/'),
    'currency_symbol' => isset($this->data['Settings']->symbol) ? $this->data['Settings']->symbol : 'Rs.',
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $assets ?>gulfpharmacy_theme/js/product-details.js"></script>
</body>
</html>
