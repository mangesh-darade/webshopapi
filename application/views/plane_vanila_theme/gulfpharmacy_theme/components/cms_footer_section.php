<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* cms_footer_section.php — Renders a CMS-driven footer block */
$cfg      = isset($config) && is_array($config) ? $config : array();
$navPages = array();
foreach (array(
    isset($cfg['nav_pages']) ? $cfg['nav_pages'] : null,
    isset($cms_footer_nav_pages) ? $cms_footer_nav_pages : null,
    isset($cms_nav_pages) ? $cms_nav_pages : null,
) as $candidatePages) {
    if (is_array($candidatePages) && !empty($candidatePages)) {
        $navPages = $candidatePages;
        break;
    }
}
$copyright= isset($cfg['copyright']) ? $cfg['copyright'] : ('&copy; ' . date('Y') . ' All rights reserved.');
$bodyText = isset($cfg['content']) ? $cfg['content'] : '';
// Optional section title typed in admin (saved as title/heading).
$ftTitle  = '';
foreach (array('title', 'heading') as $k) {
    if (isset($cfg[$k]) && trim((string) $cfg[$k]) !== '') { $ftTitle = (string) $cfg[$k]; break; }
}
?>
<div class="gp-cms-footer-strip">
    <div class="container">
        <?php if ($ftTitle !== ''): ?>
        <h2 class="gp-cms-footer-title"><?= htmlspecialchars($ftTitle, ENT_QUOTES,'UTF-8') ?></h2>
        <?php endif; ?>
        <?php if ($bodyText !== ''): ?>
        <div class="gp-cms-footer-body"><?= $bodyText ?></div>
        <?php endif; ?>
        <?php if (!empty($navPages)): ?>
        <nav aria-label="CMS footer navigation">
            <ul class="gp-cms-footer-nav">
                <?php foreach ($navPages as $np):
                    $href  = isset($np['href'])  ? $np['href']  : (isset($np['url']) ? $np['url'] : '#');
                    $label = isset($np['title']) ? $np['title'] : (isset($np['page_name']) ? $np['page_name'] : '');
                    if (trim((string) $label) === '') {
                        continue;
                    }
                ?>
                <li><a href="<?= htmlspecialchars($href, ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES,'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <p class="gp-cms-footer-copy"><?= $copyright ?></p>
    </div>
</div>
<link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/components.css'), ENT_QUOTES, 'UTF-8') ?>">
