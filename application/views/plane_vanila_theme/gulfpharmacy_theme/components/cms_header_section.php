<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* cms_header_section.php — Renders a CMS-driven header nav strip */
$cfg      = isset($config) && is_array($config) ? $config : array();
$navPages = isset($cfg['nav_pages']) && is_array($cfg['nav_pages']) ? $cfg['nav_pages']
          : (isset($cms_nav_pages)   && is_array($cms_nav_pages)   ? $cms_nav_pages   : array());
$pgTitle  = isset($cfg['page_title']) ? $cfg['page_title'] : (isset($page_title) ? $page_title : '');
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
    </div>
</div>
<style>
.gp-cms-header-strip{background:#214548;padding:10px 0;}
.gp-cms-header-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.gp-cms-hstrip-title{color:#fff;font-size:14px;font-weight:600;}
.gp-cms-hstrip-nav{list-style:none;margin:0;padding:0;display:flex;gap:6px;flex-wrap:wrap;}
.gp-cms-hstrip-nav a{color:rgba(255,255,255,.8);text-decoration:none;font-size:13px;padding:4px 10px;border-radius:6px;transition:background .2s,color .2s;}
.gp-cms-hstrip-nav a:hover{background:rgba(255,255,255,.15);color:#fff;}
</style>
