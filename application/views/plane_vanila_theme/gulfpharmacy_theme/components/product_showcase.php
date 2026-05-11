<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Product showcase component for home pages:
 * - renders optional product carousel
 * - renders optional product grid
 */
$items = isset($items) && is_array($items) ? $items : array();
$uploadsBase = isset($uploads) ? $uploads : '';
$thumbsBase = isset($thumbs) ? $thumbs : '';
$sectionTitle = isset($title) && trim((string) $title) !== '' ? (string) $title : 'Featured Products';
$showCarousel = isset($show_carousel) ? (bool) $show_carousel : true;
$showGrid = isset($show_grid) ? (bool) $show_grid : true;
if (empty($items)) {
    return;
}
?>
<section class="gp-section" aria-labelledby="product-showcase-heading">
    <div class="container">
        <h2 id="product-showcase-heading" class="gp-section-title"><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="gp-section-divider"></div>

        <?php if ($showCarousel): ?>
            <?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/product_carousel', array('items' => $items, 'uploads' => $uploadsBase, 'thumbs' => $thumbsBase), true) ?>
        <?php endif; ?>

        <?php if ($showGrid): ?>
            <?= $this->load->view('plane_vanila_theme/gulfpharmacy_theme/components/product_grid', array('items' => $items, 'uploads' => $uploadsBase, 'thumbs' => $thumbsBase), true) ?>
        <?php endif; ?>
    </div>
</section>
