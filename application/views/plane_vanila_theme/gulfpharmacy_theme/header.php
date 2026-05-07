<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$shop_name  = isset($Settings->site_name) && $Settings->site_name !== '' ? $Settings->site_name : (isset($ws->site_name) ? $ws->site_name : 'My Shop');
$logo_url   = '';
if (!empty($ws->logo) || !empty($ws->header_logo)) {
    $logo_file = !empty($ws->header_logo) ? $ws->header_logo : $ws->logo;
    $logo_url  = isset($uploads) && $uploads !== ''
        ? rtrim($uploads, '/') . '/' . ltrim($logo_file, '/')
        : '';
}
$cms_nav   = isset($cms_nav_pages) && is_array($cms_nav_pages) ? $cms_nav_pages : array();
$cart_cnt  = isset($cart_items) && is_array($cart_items) ? count($cart_items) : 0;
$wish_cnt  = isset($wishlist_count) ? (int)$wishlist_count : 0;
$webshop_url = base_url('webshop');
?>
<header class="gp-header" id="gp-header">
    <div class="gp-header-inner container">
        <!-- Logo -->
        <a class="gp-logo" href="<?= $webshop_url ?>">
            <?php if ($logo_url !== ''): ?>
                <img src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>" class="gp-logo-img">
            <?php else: ?>
                <span class="gp-logo-text"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>

        <!-- Primary Nav -->
        <nav class="gp-nav" id="gp-nav" aria-label="Main navigation">
            <ul class="gp-nav-list">
                <li><a href="<?= $webshop_url ?>" class="gp-nav-link">Home</a></li>
                <?php foreach ($cms_nav as $np): ?>
                <li><a href="<?= htmlspecialchars(isset($np['href']) ? $np['href'] : '', ENT_QUOTES, 'UTF-8') ?>" class="gp-nav-link"><?= htmlspecialchars(isset($np['title']) ? $np['title'] : '', ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Header Actions -->
        <div class="gp-header-actions">
            <!-- Search -->
            <button class="gp-icon-btn" id="gp-search-toggle" aria-label="Search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
            <!-- Wishlist -->
            <a class="gp-icon-btn" href="<?= $webshop_url ?>/wishlist" aria-label="Wishlist">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <?php if ($wish_cnt > 0): ?><span class="gp-badge"><?= $wish_cnt ?></span><?php endif; ?>
            </a>
            <!-- Cart -->
            <a class="gp-icon-btn gp-cart-btn" href="<?= $webshop_url ?>/cart" aria-label="Cart">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <?php if ($cart_cnt > 0): ?><span class="gp-badge"><?= $cart_cnt ?></span><?php endif; ?>
            </a>
            <!-- Mobile menu toggle -->
            <button class="gp-hamburger" id="gp-hamburger" aria-label="Menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <!-- Search Bar (hidden by default) -->
    <div class="gp-search-bar" id="gp-search-bar" hidden>
        <div class="container">
            <form action="<?= $webshop_url ?>/search_products" method="GET" class="gp-search-form" role="search">
                <input type="search" name="q" class="gp-search-input" placeholder="Search products…" aria-label="Search products" autocomplete="off">
                <button type="submit" class="gp-search-submit">Search</button>
            </form>
        </div>
    </div>
</header>

<style>
:root {
    --gp-primary: #214548;
    --gp-primary-light: #2f6366;
    --gp-accent: #4caf89;
    --gp-text: #1a2e30;
    --gp-muted: #6b7280;
    --gp-border: #e2e8f0;
    --gp-bg: #fff;
    --gp-header-h: 70px;
}
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; font-family: 'Inter', system-ui, sans-serif; color: var(--gp-text); background: #f8fafc; }
.container { max-width: 1280px; margin: 0 auto; padding: 0 20px; }

/* HEADER */
.gp-header { background: var(--gp-bg); border-bottom: 1px solid var(--gp-border); position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
.gp-header-inner { display: flex; align-items: center; gap: 24px; height: var(--gp-header-h); }
.gp-logo { display: flex; align-items: center; text-decoration: none; flex-shrink: 0; }
.gp-logo-img { height: 48px; width: auto; object-fit: contain; }
.gp-logo-text { font-size: 22px; font-weight: 800; color: var(--gp-primary); letter-spacing: -0.5px; }
.gp-nav { margin-left: auto; }
.gp-nav-list { list-style: none; margin: 0; padding: 0; display: flex; gap: 4px; }
.gp-nav-link { display: block; padding: 8px 14px; color: var(--gp-text); text-decoration: none; font-size: 15px; font-weight: 500; border-radius: 8px; transition: background .2s, color .2s; white-space: nowrap; }
.gp-nav-link:hover { background: #f0faf6; color: var(--gp-primary); }
.gp-nav-has-dropdown { position: relative; }
.gp-dropdown { display: none; position: absolute; top: 100%; left: 0; background: #fff; border: 1px solid var(--gp-border); border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,.12); min-width: 200px; padding: 8px; list-style: none; margin: 4px 0 0; z-index: 200; }
.gp-nav-has-dropdown:hover .gp-dropdown { display: block; }
.gp-dropdown-link { display: block; padding: 8px 12px; color: var(--gp-text); text-decoration: none; font-size: 14px; border-radius: 8px; transition: background .15s; }
.gp-dropdown-link:hover { background: #f0faf6; color: var(--gp-primary); }
.gp-caret { font-size: 11px; }
.gp-header-actions { display: flex; align-items: center; gap: 4px; margin-left: 8px; }
.gp-icon-btn { position: relative; display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; border: none; background: transparent; color: var(--gp-text); cursor: pointer; transition: background .2s, color .2s; text-decoration: none; }
.gp-icon-btn:hover { background: #f0faf6; color: var(--gp-primary); }
.gp-badge { position: absolute; top: 4px; right: 4px; min-width: 18px; height: 18px; background: var(--gp-accent); color: #fff; font-size: 10px; font-weight: 700; border-radius: 9px; display: flex; align-items: center; justify-content: center; padding: 0 4px; }
.gp-search-bar { background: var(--gp-bg); border-top: 1px solid var(--gp-border); padding: 12px 0; }
.gp-search-form { display: flex; gap: 8px; }
.gp-search-input { flex: 1; border: 1.5px solid var(--gp-border); border-radius: 10px; padding: 10px 16px; font-size: 15px; outline: none; transition: border-color .2s; }
.gp-search-input:focus { border-color: var(--gp-primary); }
.gp-search-submit { background: var(--gp-primary); color: #fff; border: none; border-radius: 10px; padding: 10px 22px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background .2s; }
.gp-search-submit:hover { background: var(--gp-primary-light); }
.gp-hamburger { display: none; flex-direction: column; gap: 5px; background: none; border: none; cursor: pointer; padding: 8px; border-radius: 8px; }
.gp-hamburger span { display: block; width: 22px; height: 2px; background: var(--gp-text); border-radius: 2px; transition: .3s; }
@media (max-width: 768px) {
    .gp-hamburger { display: flex; }
    .gp-nav { display: none; position: fixed; inset: var(--gp-header-h) 0 0 0; background: #fff; z-index: 900; overflow-y: auto; flex-direction: column; }
    .gp-nav.open { display: flex; }
    .gp-nav-list { flex-direction: column; padding: 20px; gap: 4px; }
    .gp-nav-link { font-size: 17px; padding: 12px 16px; }
    .gp-dropdown { display: none !important; }
}
</style>
<script>
(function(){
    var tog = document.getElementById('gp-search-toggle');
    var bar = document.getElementById('gp-search-bar');
    if (tog && bar) tog.addEventListener('click', function(){ bar.hidden = !bar.hidden; if (!bar.hidden) bar.querySelector('input').focus(); });
    var ham = document.getElementById('gp-hamburger');
    var nav = document.getElementById('gp-nav');
    if (ham && nav) ham.addEventListener('click', function(){
        var open = nav.classList.toggle('open');
        ham.setAttribute('aria-expanded', open);
    });
})();
</script>
