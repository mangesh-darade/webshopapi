<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$S  = isset($Settings) && is_object($Settings) ? $Settings : new stdClass();
$uploadsBase = isset($uploads) ? (string) $uploads : '';
$logo_url = function_exists('webshop_resolve_header_logo_url')
    ? webshop_resolve_header_logo_url($uploadsBase, $S, $ws, '')
    : '';
$logo_src = $logo_url;

$shop_name = webshop_store_display_name($S, $ws);
$cms_nav = isset($cms_nav_pages) && is_array($cms_nav_pages) ? $cms_nav_pages : array();
$webshop_url = base_url('webshop');
$home_url = $webshop_url;
$ws_base = rtrim($webshop_url, '/');

$hb_cta = function_exists('webshop_herbinn_header_cta_from_storefront')
    ? webshop_herbinn_header_cta_from_storefront()
    : array('label' => '', 'href' => '');
$cta_label = isset($hb_cta['label']) ? trim((string) $hb_cta['label']) : '';
$cta_href = isset($hb_cta['href']) ? trim((string) $hb_cta['href']) : '';
if ($cta_href !== '') {
    $cta_href = webshop_resolve_cms_path_href($cta_href, $ws_base);
}

$gp_logo_lcp_hint = !empty($gp_header_logo_fetchpriority) && $logo_src !== '';
?>
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/herbinn-site.css?ver=20260526k') ?>">
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/herbinn-overrides.css?ver=20260526k') ?>">
<nav class="navbar" id="mainNav" role="navigation" aria-label="Main">
    <div class="container nav-inner">
        <a class="navbar-brand" href="<?= htmlspecialchars($home_url, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($logo_src !== '') : ?>
            <img src="<?= htmlspecialchars($logo_src, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>"
                 width="180" height="72"<?= $gp_logo_lcp_hint ? ' fetchpriority="high" decoding="sync"' : ' decoding="async"' ?>>
            <?php else : ?>
            <span class="navbar-brand-text"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>
        <button type="button" class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <ul class="nav-links" id="navLinks">
            <?php foreach ($cms_nav as $np) :
                $href = isset($np['href']) ? (string) $np['href'] : '';
                $title = isset($np['title']) ? (string) $np['title'] : '';
                if ($title === '' || $href === '') {
                    continue;
                }
            ?>
            <li><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></a></li>
            <?php endforeach; ?>
            <?php if ($cta_label !== '' && $cta_href !== '') : ?>
            <li>
                <a href="<?= htmlspecialchars($cta_href, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-sm"><?= htmlspecialchars($cta_label, ENT_QUOTES, 'UTF-8') ?></a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
