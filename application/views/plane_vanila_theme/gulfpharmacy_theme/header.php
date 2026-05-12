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

    <!-- Search Bar (hidden by default) -->
    <div class="gp-search-bar" id="gp-search-bar" hidden>
        <div class="container">
            <div class="gp-search-form" role="search">
                <div class="gp-search-field">
                    <span class="gp-search-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    </span>
                    <input type="search" name="search" id="gp-search-input" class="gp-search-input"
                           placeholder="Search products…" aria-label="Search products"
                           aria-autocomplete="list" aria-controls="gp-suggest-list"
                           aria-expanded="false" autocomplete="off" spellcheck="false">
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
.gp-search-form { display: flex; align-items: stretch; }
.gp-search-field { position: relative; flex: 1; min-width: 0; }
.gp-search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gp-muted); display: inline-flex; pointer-events: none; }
.gp-search-input { width: 100%; border: 1.5px solid var(--gp-border); border-radius: 10px; padding: 10px 40px 10px 42px; font-size: 15px; outline: none; transition: border-color .2s, box-shadow .2s; background: #fff; }
.gp-search-input:focus { border-color: var(--gp-primary); box-shadow: 0 0 0 3px rgba(33,69,72,.12); }
.gp-search-clear { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border: none; background: transparent; color: var(--gp-muted); font-size: 22px; line-height: 1; cursor: pointer; border-radius: 50%; padding: 0; }
.gp-search-clear:hover { background: #f1f5f9; color: var(--gp-text); }

/* Suggestions dropdown */
.gp-suggest { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: #fff; border: 1px solid var(--gp-border); border-radius: 12px; box-shadow: 0 16px 40px rgba(15,23,42,.14); z-index: 1100; max-height: 70vh; overflow: hidden; display: flex; flex-direction: column; }
.gp-suggest-list { list-style: none; margin: 0; padding: 6px; overflow-y: auto; max-height: calc(70vh - 48px); -webkit-overflow-scrolling: touch; }
.gp-suggest-item { display: flex; align-items: center; gap: 12px; padding: 8px 10px; border-radius: 8px; cursor: pointer; text-decoration: none; color: var(--gp-text); transition: background .15s; min-height: 44px; }
.gp-suggest-item:hover, .gp-suggest-item.is-active { background: #f0faf6; }
.gp-suggest-thumb { width: 44px; height: 44px; flex-shrink: 0; border-radius: 8px; background: #f1f5f9; object-fit: cover; }
.gp-suggest-body { flex: 1; min-width: 0; }
.gp-suggest-name { font-size: 14px; font-weight: 500; line-height: 1.3; margin: 0 0 2px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.gp-suggest-name mark { background: #fff3a3; color: inherit; padding: 0 1px; border-radius: 2px; }
.gp-suggest-price { font-size: 13px; color: var(--gp-primary); font-weight: 700; }
.gp-suggest-mrp { font-size: 12px; color: var(--gp-muted); text-decoration: line-through; margin-left: 6px; font-weight: 400; }
.gp-suggest-empty { padding: 18px 16px; text-align: center; color: var(--gp-muted); font-size: 14px; }
.gp-suggest-loading { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 18px; color: var(--gp-muted); font-size: 13px; }
.gp-suggest-spinner { width: 14px; height: 14px; border: 2px solid #e2e8f0; border-top-color: var(--gp-primary); border-radius: 50%; animation: gp-spin .8s linear infinite; }
@keyframes gp-spin { to { transform: rotate(360deg); } }
.gp-hamburger { display: none; flex-direction: column; gap: 5px; background: none; border: none; cursor: pointer; padding: 8px; border-radius: 8px; }
.gp-hamburger span { display: block; width: 22px; height: 2px; background: var(--gp-text); border-radius: 2px; transition: .3s; }
@media (max-width: 768px) {
    .gp-hamburger { display: flex; }
    .gp-nav { display: none; position: fixed; inset: var(--gp-header-h) 0 0 0; background: #fff; z-index: 900; overflow-y: auto; flex-direction: column; }
    .gp-nav.open { display: flex; }
    .gp-nav-list { flex-direction: column; padding: 20px; gap: 4px; }
    .gp-nav-link { font-size: 17px; padding: 12px 16px; }
    .gp-dropdown { display: none !important; }
    .gp-suggest { max-height: 75vh; }
    .gp-suggest-list { max-height: calc(75vh - 48px); }
    .gp-suggest-item { padding: 10px; }
    .gp-suggest-thumb { width: 48px; height: 48px; }
}
</style>
<script>
(function(){
    var tog = document.getElementById('gp-search-toggle');
    var bar = document.getElementById('gp-search-bar');
    var input = document.getElementById('gp-search-input');
    var clearBtn = document.getElementById('gp-search-clear');
    var box = document.getElementById('gp-suggest');
    var list = document.getElementById('gp-suggest-list');
    var emptyEl = document.getElementById('gp-suggest-empty');
    var ham = document.getElementById('gp-hamburger');
    var nav = document.getElementById('gp-nav');
    if (ham && nav) ham.addEventListener('click', function(){
        var open = nav.classList.toggle('open');
        ham.setAttribute('aria-expanded', open);
    });

    if (!tog || !bar || !input || !box) return;

    var searchBase = <?= json_encode($webshop_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var suggestUrl = searchBase + '/search_suggest';
    var DEBOUNCE_MS = 220;
    var MIN_CHARS = 2;

    var cache = Object.create(null); // in-memory cache for the page session
    var inflight = null;              // AbortController for the live request
    var debounceTimer = null;
    var activeIndex = -1;
    var lastItems = [];

    tog.addEventListener('click', function(){
        bar.hidden = !bar.hidden;
        if (!bar.hidden) {
            setTimeout(function(){ input.focus(); }, 0);
        } else {
            hideSuggest();
        }
    });

    input.addEventListener('input', function(){
        var q = input.value.trim();
        clearBtn.hidden = q.length === 0;
        if (debounceTimer) clearTimeout(debounceTimer);
        if (q.length < MIN_CHARS) {
            cancelInflight();
            hideSuggest();
            return;
        }
        debounceTimer = setTimeout(function(){ fetchSuggestions(q); }, DEBOUNCE_MS);
    });

    input.addEventListener('focus', function(){
        if (lastItems.length && input.value.trim().length >= MIN_CHARS) showSuggest();
    });

    input.addEventListener('keydown', function(e){
        if (box.hidden) {
            if (e.key === 'ArrowDown' && lastItems.length) { e.preventDefault(); showSuggest(); setActive(0); }
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIndex + 1, lastItems.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter') {
            // No standalone results page — Enter only opens the highlighted suggestion.
            e.preventDefault();
            if (activeIndex >= 0 && lastItems[activeIndex]) {
                window.location.href = lastItems[activeIndex].url;
            } else if (lastItems.length) {
                window.location.href = lastItems[0].url;
            }
        } else if (e.key === 'Escape') {
            hideSuggest();
        }
    });

    clearBtn.addEventListener('click', function(){
        input.value = '';
        clearBtn.hidden = true;
        lastItems = [];
        hideSuggest();
        input.focus();
    });

    document.addEventListener('click', function(e){
        if (!bar.contains(e.target) && !tog.contains(e.target)) hideSuggest();
    });

    function cancelInflight() {
        if (inflight) { try { inflight.abort(); } catch (e) {} inflight = null; }
    }

    function fetchSuggestions(q) {
        var key = q.toLowerCase();
        if (cache[key]) {
            render(q, cache[key]);
            return;
        }
        renderLoading();
        cancelInflight();
        inflight = ('AbortController' in window) ? new AbortController() : null;
        var opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
        if (inflight) opts.signal = inflight.signal;
        fetch(suggestUrl + '?q=' + encodeURIComponent(q), opts)
            .then(function(r){ return r.ok ? r.json() : { items: [] }; })
            .then(function(data){
                var items = (data && Array.isArray(data.items)) ? data.items : [];
                cache[key] = items;
                if (input.value.trim().toLowerCase() === key) render(q, items);
            })
            .catch(function(){ /* aborted or network error — silent */ });
    }

    function renderLoading() {
        list.innerHTML = '<li class="gp-suggest-loading"><span class="gp-suggest-spinner"></span>Searching…</li>';
        emptyEl.hidden = true;
        showSuggest();
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(c){
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }

    function highlight(name, q) {
        var safe = escapeHtml(name);
        if (!q) return safe;
        var pat = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        try { return safe.replace(new RegExp('(' + pat + ')', 'ig'), '<mark>$1</mark>'); }
        catch (e) { return safe; }
    }

    function render(q, items) {
        lastItems = items || [];
        activeIndex = -1;
        if (!lastItems.length) {
            list.innerHTML = '';
            emptyEl.hidden = false;
            showSuggest();
            return;
        }
        emptyEl.hidden = true;
        var html = '';
        for (var i = 0; i < lastItems.length; i++) {
            var it = lastItems[i];
            var priceHtml = '';
            if (typeof it.price === 'number' && it.price > 0) {
                priceHtml = '<div class="gp-suggest-price">' + escapeHtml(it.price.toFixed(2));
                if (it.mrp && it.mrp > it.price) priceHtml += '<span class="gp-suggest-mrp">' + escapeHtml(Number(it.mrp).toFixed(2)) + '</span>';
                priceHtml += '</div>';
            }
            html += '<li role="option"><a class="gp-suggest-item" href="' + escapeHtml(it.url) + '" data-idx="' + i + '">' +
                    '<img class="gp-suggest-thumb" src="' + escapeHtml(it.image || '') + '" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">' +
                    '<div class="gp-suggest-body"><div class="gp-suggest-name">' + highlight(it.name, q) + '</div>' + priceHtml + '</div>' +
                    '</a></li>';
        }
        list.innerHTML = html;
        // Track hover so keyboard & mouse share the same active state.
        Array.prototype.forEach.call(list.querySelectorAll('.gp-suggest-item'), function(el){
            el.addEventListener('mouseenter', function(){ setActive(parseInt(el.getAttribute('data-idx'), 10)); });
        });
        showSuggest();
    }

    function setActive(i) {
        activeIndex = i;
        var els = list.querySelectorAll('.gp-suggest-item');
        for (var k = 0; k < els.length; k++) {
            if (k === i) els[k].classList.add('is-active');
            else els[k].classList.remove('is-active');
        }
        if (els[i] && els[i].scrollIntoView) els[i].scrollIntoView({ block: 'nearest' });
    }

    function showSuggest() { box.hidden = false; input.setAttribute('aria-expanded', 'true'); }
    function hideSuggest() { box.hidden = true; input.setAttribute('aria-expanded', 'false'); activeIndex = -1; }
})();
</script>
