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
<style>
.gp-cms-header-strip{background:#214548;padding:10px 0;}
.gp-cms-header-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.gp-cms-hstrip-title{color:#fff;font-size:14px;font-weight:600;}
.gp-cms-hstrip-nav{list-style:none;margin:0;padding:0;display:flex;gap:6px;flex-wrap:wrap;}
.gp-cms-hstrip-nav a{color:rgba(255,255,255,.85);text-decoration:none;font-size:13px;padding:4px 10px;border-radius:6px;transition:background .2s,color .2s;}
.gp-cms-hstrip-nav a:hover{background:rgba(255,255,255,.15);color:#fff;}
.gp-cms-hstrip-body{flex:1 1 100%;color:#cbd5e1;font-size:13px;line-height:1.5;}
.gp-cms-hstrip-body a{color:#fff;text-decoration:underline;}
@media (max-width:768px){
    .gp-cms-header-strip{padding:10px 0;}
    .gp-cms-header-inner{gap:10px;}
    .gp-cms-hstrip-nav{gap:4px;}
    .gp-cms-hstrip-nav a{font-size:13px;padding:6px 10px;}
}
</style>
