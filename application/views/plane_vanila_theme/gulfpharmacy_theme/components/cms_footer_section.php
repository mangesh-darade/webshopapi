<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* cms_footer_section.php — Renders a CMS-driven footer block */
$cfg      = isset($config) && is_array($config) ? $config : array();
$navPages = isset($cfg['nav_pages']) && is_array($cfg['nav_pages']) ? $cfg['nav_pages']
          : (isset($cms_nav_pages)   && is_array($cms_nav_pages)   ? $cms_nav_pages   : array());
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
.gp-cms-footer-strip .container{max-width:1280px;margin:0 auto;padding-left:max(20px,env(safe-area-inset-left,0px));padding-right:max(20px,env(safe-area-inset-right,0px));box-sizing:border-box;}
.gp-cms-footer-title{color:#fff;font-size:18px;font-weight:700;margin:0 0 12px;letter-spacing:-0.01em;}
.gp-cms-footer-body{font-size:14px;line-height:1.7;margin-bottom:16px;color:#cbd5e1;}
.gp-cms-footer-body a{color:#4caf89;}
.gp-cms-footer-nav{list-style:none;margin:0 0 16px;padding:0;display:flex;gap:8px;flex-wrap:wrap;}
.gp-cms-footer-nav a{color:#94a3b8;text-decoration:none;font-size:14px;padding:6px 10px;border-radius:8px;transition:color .2s,background .2s;}
.gp-cms-footer-nav a:hover{color:#4caf89;background:rgba(255,255,255,0.04);}
.gp-cms-footer-copy{font-size:13px;color:#64748b;margin:0;text-align:center;line-height:1.5;}
@media (max-width:768px){
    .gp-cms-footer-strip{padding:24px 0;}
    .gp-cms-footer-title{font-size:1.05rem;margin-bottom:10px;}
    .gp-cms-footer-body{font-size:15px;line-height:1.6;}
    .gp-cms-footer-nav{flex-direction:column;gap:2px;margin-bottom:14px;}
    .gp-cms-footer-nav a{display:block;padding:12px 14px;font-size:15px;border-radius:10px;}
}
</style>
