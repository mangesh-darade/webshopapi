<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Product showcase component for home pages:
 * - renders optional product carousel
 * - renders optional product grid
 */
$items = isset($items) && is_array($items) ? $items : array();
$uploadsBase = isset($uploads) ? $uploads : '';
$thumbsBase = isset($thumbs) ? $thumbs : '';
/* Parent may render the ruled heading; pass title => '' to omit duplicate H2 inside this component. */
$showInnerHeading = false;
$sectionTitle = '';
if (isset($title)) {
    $t = trim((string) $title);
    if ($t !== '') {
        $showInnerHeading = true;
        $sectionTitle = $t;
    }
}
$showCarousel = isset($show_carousel) ? (bool) $show_carousel : true;
$showGrid = isset($show_grid) ? (bool) $show_grid : true;
if (empty($items)) {
    return;
}
$ariaFallback = $sectionTitle !== '' ? $sectionTitle : 'Products';
$ariaLandmark = $showInnerHeading ? 'aria-labelledby="product-showcase-heading"' : 'aria-label="' . htmlspecialchars($ariaFallback, ENT_QUOTES, 'UTF-8') . '"';
?>
<section class="gp-section" <?= $ariaLandmark ?>>
    <div class="container">
        <?php if ($showInnerHeading): ?>
        <h2 id="product-showcase-heading" class="gp-section-title"><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="gp-section-divider"></div>
        <?php endif; ?>

        <?php if ($showCarousel): ?>
            <?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/product_carousel', array('items' => $items, 'uploads' => $uploadsBase, 'thumbs' => $thumbsBase), true) ?>
        <?php endif; ?>

        <?php if ($showGrid): ?>
            <?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/product_grid', array('items' => $items, 'uploads' => $uploadsBase, 'thumbs' => $thumbsBase), true) ?>
        <?php endif; ?>
    </div>
</section>
