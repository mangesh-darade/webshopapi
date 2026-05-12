<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$shop_name  = isset($Settings->site_name) && $Settings->site_name !== '' ? $Settings->site_name : (isset($ws->site_name) ? $ws->site_name : 'My Shop');
$logo_url = '';
if (!empty($ws->logo) || !empty($ws->header_logo)) {
    $logo_file = trim((string) (!empty($ws->header_logo) ? $ws->header_logo : $ws->logo));
    if ($logo_file !== '') {
        $uploadsBase = isset($uploads) ? (string) $uploads : '';
        if ($uploadsBase !== '') {
            $logo_url = webshop_media_src($uploadsBase, $logo_file);
        } elseif (preg_match('#^https?://#i', $logo_file)) {
            $logo_url = $logo_file;
        }
    }
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
                <img src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>" class="gp-logo-img" decoding="async" onerror="this.style.display='none';var fb=document.getElementById('gp-logo-text-fallback');if(fb){fb.style.display='inline';fb.removeAttribute('aria-hidden');}">
                <span id="gp-logo-text-fallback" class="gp-logo-text" style="display:none" aria-hidden="true"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?></span>
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
                <span class="gp-badge gp-cart-count" <?= $cart_cnt > 0 ? '' : 'style="display:none"' ?>><?= $cart_cnt ?></span>
            </a>
            <!-- Mobile menu toggle -->
            <button class="gp-hamburger" id="gp-hamburger" aria-label="Menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <!-- Search bar: collapsed on desktop (toggle), always visible on mobile -->
    <div class="gp-search-bar" id="gp-search-bar">
        <div class="container">
            <div class="gp-search-form" role="search">
                <div class="gp-search-field">
                    <span class="gp-search-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    </span>
                    <input type="search" name="search" id="gp-search-input" class="gp-search-input"
                           placeholder="Search medicines, brands, categories…" aria-label="Search medicines, brands, categories"
                           aria-autocomplete="list" aria-controls="gp-suggest-list"
                           aria-expanded="false" autocomplete="off" spellcheck="false">
                    <button type="button" class="gp-search-voice" id="gp-search-voice" aria-label="Voice search" title="Voice search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3Z"/><path d="M19 10v1a7 7 0 0 1-14 0v-1M12 18v4M8 22h8"/></svg>
                    </button>
                    <button type="button" class="gp-search-clear" id="gp-search-clear" aria-label="Clear search" hidden>&times;</button>
                    <div class="gp-suggest" id="gp-suggest" hidden>
                        <ul class="gp-suggest-list" id="gp-suggest-list" role="listbox"></ul>
                        <div class="gp-suggest-empty" id="gp-suggest-empty" hidden>No matching products</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
<script>window.GP_HEADER_CTX=<?= json_encode(array('webshop_url' => $webshop_url), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $assets ?>gulfpharmacy_theme/js/header.js"></script>
