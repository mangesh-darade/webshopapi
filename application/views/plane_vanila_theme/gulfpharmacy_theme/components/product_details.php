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
    <style>
        /* GLOBAL LAYOUT RESET - Fix for shrinking issue */
        html, body { 
            width: 100% !important; 
            max-width: 100% !important; 
            margin: 0 !important; 
            padding: 0 !important; 
            overflow-x: hidden !important; 
            background: #f1f5f9;
        }
        body { display: block !important; transform: none !important; zoom: 1 !important; min-height: 100vh; }
        .gp-site-wrapper { width: 100%; max-width: 100%; min-height: 100vh; display: flex; flex-direction: column; background: #fff; margin: 0 auto; }
        .gp-main { flex: 1; padding: 40px 0; background: #fff; }
        @media (max-width: 768px) { .gp-main { padding: 20px 0; } }
    </style>
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
<script>
    baseUrl = "<?= base_url('webshop/') ?>";
    const currencySymbol = "<?= isset($this->data['Settings']->symbol) ? $this->data['Settings']->symbol : 'Rs.' ?>";
    function initProductDetailsUi() {
        if (window.jQuery && jQuery.fn && jQuery.fn.slick) {
            var $slider = jQuery('.slider-product');
            if ($slider.length && !$slider.hasClass('slick-initialized')) {
                $slider.slick({autoplay:false,dots:false,speed:500,slidesToShow:1,adaptiveHeight:true,arrows:false});
            }
            $slider.on('beforeChange', function(event, slick, currentSlide, nextSlide) {
                jQuery(".slider-product-contain .thumbs a").removeClass("selected").eq(nextSlide).addClass("selected");
            });
            jQuery(".slider-product-contain .thumbs a").off('click').on('click', function() {
                jQuery(".slider-product-contain .thumbs a").removeClass("selected");
                jQuery(this).addClass("selected");
                jQuery('.slider-product').slick('slickGoTo', jQuery(this).index());
            });
        }
        if (window.jQuery && jQuery.fn && jQuery.fn.responsiveTabs && jQuery('#product-tabs').length) {
            jQuery('#product-tabs').responsiveTabs({startCollapsed:false,scrollToAccordion:false,setHash:false});
        }
        if (window.jQuery) {
            jQuery(document).off('click.pqtyinc').on('click.pqtyinc', '.btn-increase', function() {
                var $input = jQuery(this).siblings('.itemQty');
                var qty = parseInt($input.val(), 10) || 1;
                $input.val(qty + 1);
            });
            jQuery(document).off('click.pqtydec').on('click.pqtydec', '.btn-decrease', function() {
                var $input = jQuery(this).siblings('.itemQty');
                var qty = parseInt($input.val(), 10) || 1;
                if (qty > 1) { $input.val(qty - 1); }
            });
        }
    }
    jQuery(document).ready(function() {
        initProductDetailsUi();
    });
</script>
</body>
</html>
