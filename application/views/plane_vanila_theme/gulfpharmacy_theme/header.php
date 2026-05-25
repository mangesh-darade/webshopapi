<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$S  = isset($Settings) && is_object($Settings) ? $Settings : new stdClass();
$uploadsBase = isset($uploads) ? (string) $uploads : '';
$logo_url = webshop_resolve_header_logo_url($uploadsBase, $S, $ws, '');

$shop_name = webshop_store_display_name($S, $ws);
if ($shop_name === '') {
    $shop_name = 'Shop';
}
$cms_nav   = isset($cms_nav_pages) && is_array($cms_nav_pages) ? $cms_nav_pages : array();
$cart_cnt  = isset($cart_items) && is_array($cart_items) ? count($cart_items) : 0;
$wish_cnt  = isset($wishlist_count) ? (int)$wishlist_count : 0;
$webshop_url = base_url('webshop');

// Pull user session for profile dropdown + sidebar greeting without leaking session details client-side.
$ws_sess = $this->session->userdata('webshop');
$is_login = false;
$user_name = '';
$user_email = '';
if ($ws_sess) {
    if (is_object($ws_sess)) {
        $is_login   = !empty($ws_sess->is_login);
        $user_name  = isset($ws_sess->name)  ? (string) $ws_sess->name  : '';
        $user_email = isset($ws_sess->email) ? (string) $ws_sess->email : '';
    } else {
        $is_login   = !empty($ws_sess['is_login']);
        $user_name  = isset($ws_sess['name'])  ? (string) $ws_sess['name']  : '';
        $user_email = isset($ws_sess['email']) ? (string) $ws_sess['email'] : '';
    }
}
$user_first_name = $user_name !== '' ? trim((string) strtok($user_name, ' ')) : '';

$gp_logo_lcp_hint = !empty($gp_header_logo_fetchpriority) && $logo_url !== '';
$_gp_header_slots = function_exists('webshop_header_gather_display_slots')
    ? webshop_header_gather_display_slots()
    : array('announcement' => array(), 'top_html' => array(), 'phone' => array());
?>
<link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
<link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header-drawers.css">
<header class="gp-header" id="gp-header">
    <?php if (!empty($_gp_header_slots['announcement'])) : ?>
    <div class="gp-header-announcement" role="region" aria-label="Store announcement">
        <?php foreach ($_gp_header_slots['announcement'] as $_gp_ann) :
            $_gp_ann_html = function_exists('webshop_footer_row_body_html')
                ? webshop_footer_row_body_html((string) $_gp_ann['field_key'], (string) $_gp_ann['value'], $uploadsBase, '')
                : nl2br(htmlspecialchars((string) $_gp_ann['value'], ENT_QUOTES, 'UTF-8'));
        ?>
        <div class="gp-header-announcement-item"><?= $_gp_ann_html ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($_gp_header_slots['top_html'])) : ?>
    <div class="gp-header-top-html" role="region" aria-label="Header notice">
        <?php foreach ($_gp_header_slots['top_html'] as $_gp_top) :
            $_gp_top_html = function_exists('webshop_footer_row_body_html')
                ? webshop_footer_row_body_html((string) $_gp_top['field_key'], (string) $_gp_top['value'], $uploadsBase, '')
                : nl2br(htmlspecialchars((string) $_gp_top['value'], ENT_QUOTES, 'UTF-8'));
        ?>
        <div class="gp-header-top-html-item"><?= $_gp_top_html ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($_gp_header_slots['phone'])) : ?>
    <div class="gp-header-phone-strip container" role="region" aria-label="Contact phone">
        <?php foreach ($_gp_header_slots['phone'] as $_gp_ph) :
            $_gp_ph_href = function_exists('webshop_footer_row_link_href')
                ? webshop_footer_row_link_href((string) $_gp_ph['field_key'], (string) $_gp_ph['value'])
                : '';
            $_gp_ph_label = trim((string) $_gp_ph['label']) !== '' ? (string) $_gp_ph['label'] : 'Call us';
            $_gp_ph_text = trim(strip_tags((string) $_gp_ph['value']));
        ?>
        <?php if ($_gp_ph_href !== '') : ?>
        <a class="gp-header-phone-link" href="<?= htmlspecialchars($_gp_ph_href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($_gp_ph_text !== '' ? $_gp_ph_text : $_gp_ph_label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php elseif ($_gp_ph_text !== '') : ?>
        <span class="gp-header-phone-text"><?= htmlspecialchars($_gp_ph_text, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="gp-header-inner container">
        <!-- Logo -->
        <a class="gp-logo" href="<?= $webshop_url ?>">
            <?php if ($logo_url !== ''): ?>
                <img src="<?= htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>" class="gp-logo-img" width="180" height="48"<?= $gp_logo_lcp_hint ? ' fetchpriority="high" decoding="sync"' : ' decoding="async"' ?> onerror="this.style.display='none';var fb=document.getElementById('gp-logo-text-fallback');if(fb){fb.style.display='inline';fb.removeAttribute('aria-hidden');}">
                <span id="gp-logo-text-fallback" class="gp-logo-text" style="display:none" aria-hidden="true"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
                <span class="gp-logo-text"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </a>

        <!-- Primary Nav: published CMS Pages only (page_name from admin) -->
        <?php if (!empty($cms_nav)): ?>
        <nav class="gp-nav" id="gp-nav" aria-label="Main navigation">
            <ul class="gp-nav-list">
                <?php foreach ($cms_nav as $np): ?>
                <li><a href="<?= htmlspecialchars(isset($np['href']) ? $np['href'] : '', ENT_QUOTES, 'UTF-8') ?>" class="gp-nav-link"><?= htmlspecialchars(isset($np['title']) ? $np['title'] : '', ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <!-- Header Actions -->
        <div class="gp-header-actions">
            <!-- Search -->
            <button class="gp-icon-btn" id="gp-search-toggle" aria-label="Search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
            <!-- Profile -->
            <div class="gp-profile-wrap">
                <button type="button" class="gp-icon-btn gp-profile-btn" id="gp-profile-btn"
                        aria-label="Account menu" aria-haspopup="true" aria-expanded="false" aria-controls="gp-profile-dropdown">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                </button>
                <div class="gp-profile-dropdown" id="gp-profile-dropdown" role="menu" aria-labelledby="gp-profile-btn">
                    <?php if ($is_login): ?>
                        <div class="gp-profile-greet">
                            <p class="gp-profile-greet-line1">Hello,</p>
                            <p class="gp-profile-greet-line2"><?= htmlspecialchars($user_first_name !== '' ? $user_first_name : 'there', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <a class="gp-profile-link" href="<?= $webshop_url ?>/your_account" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                            My Account
                        </a>
                        <a class="gp-profile-link" href="<?= $webshop_url ?>/your_orders" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            My Orders
                        </a>
                        <a class="gp-profile-link" href="<?= $webshop_url ?>/your_address" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            Saved Addresses
                        </a>
                        <a class="gp-profile-link" href="<?= $webshop_url ?>/your_tracking" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            Track order
                        </a>
                        <a class="gp-profile-link" href="<?= $webshop_url ?>/wishlist" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                            Wishlist
                        </a>
                        <div class="gp-profile-divider" role="presentation"></div>
                        <a class="gp-profile-link gp-profile-signout" href="<?= $webshop_url ?>/logout" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                            Sign out
                        </a>
                    <?php else: ?>
                        <div class="gp-profile-greet">
                            <p class="gp-profile-greet-line1">Welcome!</p>
                            <p class="gp-profile-greet-line2">Sign in for a faster checkout</p>
                        </div>
                        <a class="gp-profile-link" href="<?= $webshop_url ?>/login" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                            Sign in
                        </a>
                        <a class="gp-profile-link" href="<?= $webshop_url ?>/register" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
                            Create account
                        </a>
                        <div class="gp-profile-divider" role="presentation"></div>
                        <a class="gp-profile-link" href="<?= $webshop_url ?>/your_orders" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                            Track an order
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Wishlist -->
            <a class="gp-icon-btn" href="<?= $webshop_url ?>/wishlist" aria-label="Wishlist">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="gp-badge gp-wishlist-count" <?= $wish_cnt > 0 ? '' : 'style="display:none"' ?>><?= $wish_cnt ?></span>
            </a>
            <!-- Cart -->
            <a class="gp-icon-btn gp-cart-btn" href="<?= $webshop_url ?>/cart" aria-label="Cart">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="gp-badge gp-cart-count" <?= $cart_cnt > 0 ? '' : 'style="display:none"' ?>><?= $cart_cnt ?></span>
            </a>
            <!-- Mobile menu toggle -->
            <button class="gp-hamburger" id="gp-hamburger" aria-label="Menu" aria-expanded="false" aria-controls="gp-sidebar-drawer">
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

<!-- Mobile sidebar drawer (left) -->
<aside class="gp-drawer gp-sidebar" id="gp-sidebar-drawer" data-side="left" role="dialog" aria-modal="true" aria-label="Menu" aria-hidden="true">
    <div class="gp-sidebar-greet">
        <?php if ($is_login): ?>
            <p class="gp-sidebar-greet-name">Hi, <?= htmlspecialchars($user_first_name !== '' ? $user_first_name : 'there', ENT_QUOTES, 'UTF-8') ?></p>
            <p class="gp-sidebar-greet-sub"><?= htmlspecialchars($user_email !== '' ? $user_email : 'Welcome back', ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <p class="gp-sidebar-greet-name">Welcome</p>
            <p class="gp-sidebar-greet-sub"><a href="<?= $webshop_url ?>/login">Sign in</a> or <a href="<?= $webshop_url ?>/register">register</a></p>
        <?php endif; ?>
    </div>

    <div class="gp-drawer-head">
        <h2 class="gp-drawer-title">Menu</h2>
        <button type="button" class="gp-drawer-close" aria-label="Close menu">&times;</button>
    </div>

    <div class="gp-drawer-body">
        <?php if (!empty($cms_nav)): ?>
        <div class="gp-sidebar-section">
            <h3 class="gp-sidebar-section-title">Pages</h3>
            <?php foreach ($cms_nav as $np): ?>
            <a class="gp-sidebar-link" href="<?= htmlspecialchars(isset($np['href']) ? $np['href'] : '', ENT_QUOTES, 'UTF-8') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                <?= htmlspecialchars(isset($np['title']) ? $np['title'] : '', ENT_QUOTES, 'UTF-8') ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="gp-sidebar-section">
            <h3 class="gp-sidebar-section-title">My account</h3>
            <?php if ($is_login): ?>
                <a class="gp-sidebar-link" href="<?= $webshop_url ?>/your_account">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    Account dashboard
                </a>
                <a class="gp-sidebar-link" href="<?= $webshop_url ?>/your_orders">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    My orders
                </a>
                <a class="gp-sidebar-link" href="<?= $webshop_url ?>/your_tracking">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    Track order
                </a>
                <a class="gp-sidebar-link" href="<?= $webshop_url ?>/your_address">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    Saved addresses
                </a>
                <a class="gp-sidebar-link" href="<?= $webshop_url ?>/wishlist">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    Wishlist
                </a>
                <a class="gp-sidebar-link gp-sidebar-signout" href="<?= $webshop_url ?>/logout" data-danger="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                    Sign out
                </a>
            <?php else: ?>
                <a class="gp-sidebar-link" href="<?= $webshop_url ?>/login">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                    Sign in
                </a>
                <a class="gp-sidebar-link" href="<?= $webshop_url ?>/register">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
                    Create account
                </a>
            <?php endif; ?>
        </div>
    </div>
</aside>

<!-- Mini cart drawer (right) -->
<aside class="gp-drawer gp-minicart" id="gp-minicart-drawer" data-side="right" role="dialog" aria-modal="true" aria-label="Shopping cart" aria-hidden="true">
    <div class="gp-drawer-head">
        <h2 class="gp-drawer-title">Your cart</h2>
        <button type="button" class="gp-drawer-close" aria-label="Close cart">&times;</button>
    </div>
    <div class="gp-drawer-body gp-minicart-body">
        <!-- Populated by header-drawers.js on open. -->
    </div>
    <div class="gp-drawer-foot" hidden>
        <!-- Subtotal + actions injected by header-drawers.js. -->
    </div>
</aside>


<script>window.GP_HEADER_CTX=<?= json_encode(array(
    'webshop_url'     => $webshop_url,
    'is_login'        => (bool) $is_login,
    'user_name'       => $user_name,
    'user_first_name' => $user_first_name,
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $assets ?>gulfpharmacy_theme/js/header.js"></script>
<script defer src="<?= $assets ?>gulfpharmacy_theme/js/header-drawers.js"></script>
