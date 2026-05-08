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
<div class="gp-component gp-html-block cms-html-block">
    <?php if ($title !== ''): ?>
    <h2 class="cms-hb-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <?= isset($uploadsB) && $uploadsB ? webshop_normalize_html_media_urls($content, $uploadsB) : $content ?>
</div>
<style>
.cms-hb-title{font-size:24px;font-weight:800;color:#214548;margin:0 0 20px;}
.gp-html-block{line-height:1.75;font-size:15px;color:#374151;}
.gp-html-block img{max-width:100%;height:auto;border-radius:10px;margin:12px 0;}
.gp-html-block h1,.gp-html-block h2,.gp-html-block h3{color:#214548;font-weight:800;}
.gp-html-block a{color:#4caf89;text-decoration:underline;}
.gp-html-block table{width:100%;border-collapse:collapse;}
.gp-html-block td,.gp-html-block th{border:1px solid #e2e8f0;padding:10px 14px;}
</style>
