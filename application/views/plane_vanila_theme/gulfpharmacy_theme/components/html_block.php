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
$uploadsB = isset($uploads) ? $uploads : '';
if (trim($content) === '') return;
?>
<link rel="stylesheet" href="<?= isset($assets) ? $assets : base_url('assets/webshop/') ?>gulfpharmacy_theme/css/components.css">
<div class="gp-component gp-html-block cms-html-block">
    <?php if ($title !== ''): ?>
    <h2 class="cms-hb-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <?= isset($uploadsB) && $uploadsB ? webshop_normalize_html_media_urls($content, $uploadsB) : $content ?>
</div>
