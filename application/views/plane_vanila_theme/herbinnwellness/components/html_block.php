<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* html_block.php — Renders raw HTML content from CMS */
$cfg     = isset($config) && is_array($config) ? $config : array();
$title   = isset($title) ? trim((string) $title) : '';
if ($title === '' && isset($cfg['title'])) $title = trim((string) $cfg['title']);
if ($title === '' && isset($cfg['heading'])) $title = trim((string) $cfg['heading']);
$content = isset($content) ? (string)$content : '';
if ($content === '' && isset($cfg['content'])) $content = (string)$cfg['content'];
if ($content === '' && isset($data['content'])) $content = (string)$data['content'];
if ($title !== '' && function_exists('webshop_cms_html_content_redundant_with_title')
    && webshop_cms_html_content_redundant_with_title($content, $title)) {
    $content = '';
}
$uploadsB = isset($uploads) ? $uploads : '';
if (trim($content) === '' && $title === '') return;
$preparedBlock = webshop_prepare_cms_html_for_output($content, $uploadsB);
$extracted = webshop_extract_cms_embedded_assets($preparedBlock);
$embeddedHead = trim((string) $extracted['style_blocks'] . "\n" . (string) $extracted['link_tags']);
$fragment = isset($extracted['html']) ? (string) $extracted['html'] : $preparedBlock;
$supportBand = (bool) preg_match('/questions\?|customer service|call our friendly/i', strip_tags($fragment));
$blockClass = 'herbinn-cms-block cms-html-block' . ($supportBand ? ' hb-support-band' : '');
?>
<?php if ($embeddedHead !== ''): ?>
<?= $embeddedHead ?>

<?php endif; ?>
<div class="<?= $blockClass ?>">
    <?php if ($title !== ''): ?>
    <h2 class="cms-hb-title visually-hidden"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <?= webshop_normalize_html_media_urls($fragment, $uploadsB ? $uploadsB : '') ?>
</div>
