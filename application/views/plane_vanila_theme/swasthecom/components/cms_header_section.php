<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* cms_header_section.php — Renders a CMS-driven header nav strip */
$cfg      = isset($config) && is_array($config) ? $config : array();
$navPages = isset($cfg['nav_pages']) && is_array($cfg['nav_pages']) ? $cfg['nav_pages']
          : (isset($cms_nav_pages)   && is_array($cms_nav_pages)   ? $cms_nav_pages   : array());
// title/heading from CMS section_heading; falls back to outer $page_title.
$pgTitle  = '';
foreach (array('title', 'heading', 'page_title') as $k) {
    if (isset($cfg[$k]) && trim((string) $cfg[$k]) !== '') { $pgTitle = (string) $cfg[$k]; break; }
}
if ($pgTitle === '' && isset($page_title)) { $pgTitle = (string) $page_title; }
$bodyHtml = isset($cfg['content']) ? (string) $cfg['content'] : '';
$webshop  = base_url('webshop');
?>
<div class="gp-cms-header-strip">
    <div class="container gp-cms-header-inner">
        <?php if ($pgTitle !== ''): ?>
        <span class="gp-cms-hstrip-title"><?= htmlspecialchars($pgTitle, ENT_QUOTES,'UTF-8') ?></span>
        <?php endif; ?>
        <?php if (!empty($navPages)): ?>
        <nav aria-label="CMS navigation strip">
            <ul class="gp-cms-hstrip-nav">
                <?php foreach ($navPages as $np):
                    $href  = isset($np['href'])  ? $np['href']  : (isset($np['url']) ? $np['url'] : '#');
                    $label = isset($np['title']) ? $np['title'] : (isset($np['page_name']) ? $np['page_name'] : '');
                ?>
                <li><a href="<?= htmlspecialchars($href, ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES,'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php if ($bodyHtml !== ''): ?>
        <div class="gp-cms-hstrip-body"><?= $bodyHtml ?></div>
        <?php endif; ?>
    </div>
</div>
<?php if (function_exists('webshop_theme_assets_url')) : ?>
<link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/components.css?ver=20260601a'), ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
