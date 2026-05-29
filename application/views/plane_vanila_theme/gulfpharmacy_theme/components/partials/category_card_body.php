<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Card body: title + short description + Learn More (shared carousel/grid). */
$catRow = isset($cat) ? $cat : array();
$catRow = is_object($catRow) ? (array) $catRow : (is_array($catRow) ? $catRow : array());
$catUrl = isset($catUrl) ? (string) $catUrl : '';
$learnMoreUrl = isset($learnMoreUrl) ? (string) $learnMoreUrl : '';
if ($learnMoreUrl === '' && function_exists('webshop_category_learn_more_url')) {
    $learnMoreUrl = webshop_category_learn_more_url($catRow, $catUrl);
}
if ($learnMoreUrl === '') {
    $learnMoreUrl = $catUrl;
}
$titleTag = isset($titleTag) ? (string) $titleTag : 'h3';
$titleClass = isset($titleClass) ? (string) $titleClass : 'gp-cat-card__title';
$categoryNameRaw = isset($catRow['name']) ? (string) $catRow['name'] : '';
$categoryName = htmlspecialchars(html_entity_decode($categoryNameRaw, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$copy = function_exists('webshop_category_card_copy')
    ? webshop_category_card_copy($catRow, 'carousel')
    : array('blurb' => '', 'short' => '', 'long' => '');
$tagline = isset($copy['blurb']) && trim((string) $copy['blurb']) !== ''
    ? trim((string) $copy['blurb'])
    : (isset($copy['short']) ? trim((string) $copy['short']) : '');
if ($tagline === '' && isset($copy['long']) && trim((string) $copy['long']) !== '') {
    $tagline = trim((string) $copy['long']);
}
if ($tagline !== '' && function_exists('webshop_category_description_plain')) {
    $tagline = webshop_category_description_plain($tagline, 72);
}
$catUrlEsc = htmlspecialchars($catUrl, ENT_QUOTES, 'UTF-8');
$learnMoreUrlEsc = htmlspecialchars($learnMoreUrl, ENT_QUOTES, 'UTF-8');
?>
<div class="gp-cat-card__body">
    <?php if ($catUrl !== ''): ?>
    <a href="<?= $catUrlEsc ?>" class="gp-cat-card__title-link">
        <?php if ($titleTag === 'h5'): ?>
        <h5 class="<?= htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8') ?>"><?= $categoryName ?></h5>
        <?php else: ?>
        <h3 class="<?= htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8') ?>"><?= $categoryName ?></h3>
        <?php endif; ?>
    </a>
    <?php else: ?>
        <?php if ($titleTag === 'h5'): ?>
        <h5 class="<?= htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8') ?>"><?= $categoryName ?></h5>
        <?php else: ?>
        <h3 class="<?= htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8') ?>"><?= $categoryName ?></h3>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($tagline !== ''): ?>
    <p class="gp-cat-card__desc"><?= htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <a href="<?= $learnMoreUrlEsc ?>" class="gp-cat-card__cta">Learn More</a>
</div>
