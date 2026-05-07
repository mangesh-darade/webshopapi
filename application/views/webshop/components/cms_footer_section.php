<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* cms_footer_section.php — Renders a CMS-driven footer block */
$cfg      = isset($config) && is_array($config) ? $config : array();
$navPages = isset($cfg['nav_pages']) && is_array($cfg['nav_pages']) ? $cfg['nav_pages']
          : (isset($cms_nav_pages)   && is_array($cms_nav_pages)   ? $cms_nav_pages   : array());
$copyright= isset($cfg['copyright']) ? $cfg['copyright'] : ('&copy; ' . date('Y') . ' All rights reserved.');
$bodyText = isset($cfg['content']) ? $cfg['content'] : '';
?>
<div class="gp-cms-footer-strip">
    <div class="container">
        <?php if ($bodyText !== ''): ?>
        <div class="gp-cms-footer-body"><?= $bodyText ?></div>
        <?php endif; ?>
        <?php if (!empty($navPages)): ?>
        <nav aria-label="CMS footer navigation">
            <ul class="gp-cms-footer-nav">
                <?php foreach ($navPages as $np):
                    $href  = isset($np['href'])  ? $np['href']  : (isset($np['url']) ? $np['url'] : '#');
                    $label = isset($np['title']) ? $np['title'] : (isset($np['page_name']) ? $np['page_name'] : '');
                ?>
                <li><a href="<?= htmlspecialchars($href, ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES,'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <p class="gp-cms-footer-copy"><?= $copyright ?></p>
    </div>
</div>
<style>
.gp-cms-footer-strip{background:#1a373a;color:#94a3b8;padding:28px 0;margin-top:0;}
.gp-cms-footer-body{font-size:14px;line-height:1.7;margin-bottom:16px;color:#cbd5e1;}
.gp-cms-footer-nav{list-style:none;margin:0 0 16px;padding:0;display:flex;gap:8px;flex-wrap:wrap;}
.gp-cms-footer-nav a{color:#94a3b8;text-decoration:none;font-size:13px;transition:color .2s;}
.gp-cms-footer-nav a:hover{color:#4caf89;}
.gp-cms-footer-copy{font-size:13px;color:#64748b;margin:0;text-align:center;}
</style>
