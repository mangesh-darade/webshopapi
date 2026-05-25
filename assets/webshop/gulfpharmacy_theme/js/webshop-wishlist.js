/**
 * Wishlist toggle on product cards (category PLP, CMS product grid/carousel).
 * Requires GP_CSRF + webshop-csrf.js and GP_PLP_CTX.
 */
(function (global) {
    'use strict';

    function ctx() {
        return global.GP_PLP_CTX || global.GP_WISHLIST_PAGE_CTX || {};
    }

    function requestUrl() {
        var c = ctx();
        return c.request_url || c.webshop_request_url || '';
    }

    function loginUrl() {
        var c = ctx();
        return c.login_url || '';
    }

    function isLoggedIn() {
        var c = ctx();
        return !!c.is_logged_in;
    }

    function lookupHas(pid) {
        var c = ctx();
        var map = c.wishlist_lookup;
        if (!map || typeof map !== 'object') {
            return false;
        }
        var key = String(pid);
        var oids = map[key] || map[pid];
        return !!(oids && oids.length);
    }

    function syncLookup(pid, vid, add) {
        var c = ctx();
        if (!c.wishlist_lookup || typeof c.wishlist_lookup !== 'object') {
            c.wishlist_lookup = {};
        }
        var key = String(pid);
        vid = parseInt(vid, 10) || 0;
        if (add) {
            c.wishlist_lookup[key] = [vid > 0 ? vid : 0];
        } else {
            delete c.wishlist_lookup[key];
            delete c.wishlist_lookup[pid];
        }
    }

    function syncProductHearts(productId, inWishlist) {
        var buttons = document.querySelectorAll('[data-wishlist-toggle][data-product-id="' + productId + '"]');
        for (var i = 0; i < buttons.length; i++) {
            setBtnState(buttons[i], inWishlist);
        }
    }

    function setBtnState(btn, inWishlist) {
        btn.classList.toggle('active', inWishlist);
        btn.classList.toggle('is-saved', inWishlist);
        btn.setAttribute('data-in-wishlist', inWishlist ? '1' : '0');
        btn.setAttribute('aria-pressed', inWishlist ? 'true' : 'false');
        btn.setAttribute('aria-label', inWishlist ? 'Remove from favourites' : 'Save to favourites');
        var icon = btn.querySelector('.gp-fav-btn__icon');
        if (icon) {
            icon.textContent = inWishlist ? '\u2665' : '\u2661';
        } else {
            btn.textContent = inWishlist ? '\u2665' : '\u2661';
        }
        var label = btn.querySelector('.gp-fav-btn__label');
        if (label) {
            label.textContent = inWishlist ? 'Saved' : 'Save';
        }
    }

    function failMessage(data, fallback) {
        if (data && data.message) {
            return String(data.message);
        }
        if (data && data.error) {
            return String(data.error);
        }
        return fallback;
    }

    function postWishlist(action, productId, variantId) {
        var url = requestUrl();
        if (!url) {
            return Promise.reject(new Error('no request url'));
        }
        var body = 'action=' + encodeURIComponent(action)
            + '&product_id=' + encodeURIComponent(String(productId));
        if (variantId > 0) {
            body += '&variant_id=' + encodeURIComponent(String(variantId));
        }
        if (typeof global.webshopAppendCsrfParams === 'function') {
            body = global.webshopAppendCsrfParams(body);
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: body
        }).then(function (res) {
            return res.json().then(function (data) {
                if (typeof global.webshopUpdateCsrfFromJson === 'function') {
                    global.webshopUpdateCsrfFromJson(data);
                }
                return { ok: res.ok, data: data };
            }).catch(function () {
                return { ok: res.ok, data: null };
            });
        });
    }

    function onToggleClick(ev) {
        var btn = ev.target.closest('[data-wishlist-toggle]');
        if (!btn || btn.disabled) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();

        if (!isLoggedIn()) {
            var login = loginUrl();
            if (login) {
                global.location.href = login + '?return_page=' + encodeURIComponent(global.location.href);
            }
            return;
        }

        var productId = parseInt(btn.getAttribute('data-product-id'), 10) || 0;
        if (productId < 1) {
            return;
        }
        var variantId = parseInt(btn.getAttribute('data-variant-id'), 10) || 0;
        var inWishlist = btn.getAttribute('data-in-wishlist') === '1';
        var action = inWishlist ? 'remove_from_wishlist' : 'add_to_wishlist';

        btn.disabled = true;
        postWishlist(action, productId, variantId)
            .then(function (result) {
                var data = result.data;
                if (data && data.status === 'SUCCESS') {
                    var nowIn = data.already_in_wishlist ? true : !inWishlist;
                    setBtnState(btn, nowIn);
                    syncLookup(productId, variantId, nowIn);
                    syncProductHearts(productId, nowIn);
                    if (data.count !== undefined && typeof global.webshopUpdateWishlistBadge === 'function') {
                        global.webshopUpdateWishlistBadge(data.count);
                    }
                    return;
                }
                if (data && data.code === 'NOT_LOGGED_IN' && loginUrl()) {
                    global.location.href = loginUrl() + '?return_page=' + encodeURIComponent(global.location.href);
                    return;
                }
                alert(failMessage(data, 'Unable to update favourites.'));
            })
            .catch(function () {
                alert('Unable to update favourites.');
            })
            .then(function () {
                btn.disabled = false;
            });
    }

    function initButtons(root) {
        root = root || document;
        var buttons = root.querySelectorAll('[data-wishlist-toggle]');
        for (var i = 0; i < buttons.length; i++) {
            var b = buttons[i];
            var pid = parseInt(b.getAttribute('data-product-id'), 10) || 0;
            if (b.getAttribute('data-in-wishlist') === null || b.getAttribute('data-in-wishlist') === '') {
                setBtnState(b, lookupHas(pid));
            }
        }
    }

    document.addEventListener('click', onToggleClick, true);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initButtons();
        });
    } else {
        initButtons();
    }

    global.webshopInitWishlistCardButtons = initButtons;
})(window);
