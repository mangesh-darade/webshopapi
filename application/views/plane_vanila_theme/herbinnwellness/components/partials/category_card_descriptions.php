<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$catRow = isset($cat) ? $cat : array();
$catRow = is_object($catRow) ? (array) $catRow : (is_array($catRow) ? $catRow : array());
$layout = isset($layout) ? strtolower(trim((string) $layout)) : 'grid';
$copy = function_exists('webshop_category_card_copy')
    ? webshop_category_card_copy($catRow, $layout)
    : array('blurb' => '', 'short' => '', 'long' => '');
$blurb  = isset($copy['blurb']) ? trim((string) $copy['blurb']) : '';
$short  = isset($copy['short']) ? trim((string) $copy['short']) : '';
$long   = isset($copy['long']) ? trim((string) $copy['long']) : '';
$hasCopy = ($layout === 'carousel' && $blurb !== '') || ($short !== '' || $long !== '');
if ($hasCopy):
?>
<div class="gp-cat-desc gp-cat-desc--<?= htmlspecialchars($layout, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($layout === 'carousel' && $blurb !== ''): ?>
    <p class="gp-cat-desc__blurb"><?= htmlspecialchars($blurb, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
        <?php if ($short !== ''): ?>
        <p class="gp-cat-desc__short"><?= htmlspecialchars($short, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($long !== ''): ?>
        <p class="gp-cat-desc__long"><?= htmlspecialchars($long, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
