/**
 * CSRF helpers for POST webshop/webshop_request (CodeIgniter elintom_csrf_*).
 */
(function (global) {
    'use strict';

    function getCsrf() {
        var c = global.GP_CSRF;
        if (c && c.name && c.hash) {
            return c;
        }
        return null;
    }

    global.webshopAppendCsrf = function (fd) {
        var c = getCsrf();
        if (c && fd && typeof fd.append === 'function') {
            fd.append(c.name, c.hash);
        }
        return fd;
    };

    global.webshopAppendCsrfParams = function (body) {
        var c = getCsrf();
        if (!c) {
            return body || '';
        }
        var part = encodeURIComponent(c.name) + '=' + encodeURIComponent(c.hash);
        if (!body) {
            return part;
        }
        return body + '&' + part;
    };

    global.webshopMergeCsrfIntoJson = function (obj) {
        var c = getCsrf();
        var out = obj && typeof obj === 'object' ? Object.assign({}, obj) : {};
        if (c) {
            out[c.name] = c.hash;
        }
        return out;
    };

    global.webshopPostAction = function (url, action, fields) {
        var params = new URLSearchParams();
        params.append('action', action);
        if (fields && typeof fields === 'object') {
            Object.keys(fields).forEach(function (key) {
                var val = fields[key];
                if (val !== undefined && val !== null) {
                    params.append(key, String(val));
                }
            });
        }
        var body = global.webshopAppendCsrfParams(params.toString());
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
                global.webshopUpdateCsrfFromJson(data);
                return { ok: res.ok, status: res.status, data: data };
            }).catch(function () {
                return { ok: res.ok, status: res.status, data: null };
            });
        });
    };

    global.webshopFetchJsonPost = function (url, payload) {
        var body = global.webshopMergeCsrfIntoJson(payload || {});
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().then(function (data) {
                if (typeof global.webshopUpdateCsrfFromJson === 'function') {
                    global.webshopUpdateCsrfFromJson(data);
                }
                return { ok: res.ok, status: res.status, data: data };
            }).catch(function () {
                return { ok: res.ok, status: res.status, data: null };
            });
        });
    };

    global.webshopUpdateCsrfFromJson = function (obj) {
        if (obj && obj.csrf_name && obj.csrf_hash) {
            global.GP_CSRF = { name: obj.csrf_name, hash: obj.csrf_hash };
        } else if (obj && obj.csrf_hash && global.GP_CSRF && global.GP_CSRF.name) {
            global.GP_CSRF.hash = obj.csrf_hash;
        }
    };

    function formHasCsrfField(form, tokenName) {
        if (!form || !tokenName) {
            return false;
        }
        var fields = form.querySelectorAll('input[type="hidden"][name]');
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].getAttribute('name') === tokenName) {
                return true;
            }
        }
        return false;
    }

    function appendCsrfToForm(form) {
        var c = getCsrf();
        if (!c || !form || formHasCsrfField(form, c.name)) {
            return;
        }
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = c.name;
        inp.value = c.hash;
        form.appendChild(inp);
    }

    function isWebshopPostForm(form) {
        if (!form || String(form.method || 'get').toLowerCase() !== 'post') {
            return false;
        }
        var action = String(form.getAttribute('action') || '');
        if (action === '') {
            return true;
        }
        return action.indexOf('webshop') !== -1;
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || form.tagName !== 'FORM' || !isWebshopPostForm(form)) {
            return;
        }
        appendCsrfToForm(form);
    }, true);

    if (typeof global.jQuery !== 'undefined') {
        global.jQuery(document).ajaxSend(function (ev, jqxhr, settings) {
            if (!settings || String(settings.type || '').toUpperCase() !== 'POST') {
                return;
            }
            var url = String(settings.url || '');
            if (url.indexOf('webshop_request') === -1
                && url.indexOf('manage_address_webshop') === -1
                && url.indexOf('profile_update_webshop') === -1) {
                return;
            }
            var c = getCsrf();
            if (!c) {
                return;
            }
            if (typeof settings.data === 'string') {
                settings.data = global.webshopAppendCsrfParams(settings.data);
            } else if (settings.data && typeof settings.data === 'object' && !(settings.data instanceof FormData)) {
                settings.data[c.name] = c.hash;
            }
        });
    }

    global.webshopUpdateWishlistBadge = function (count) {
        var badge = document.querySelector('.gp-wishlist-count');
        if (!badge) {
            return;
        }
        var n = parseInt(count, 10);
        if (isNaN(n) || n < 0) {
            n = 0;
        }
        badge.textContent = String(n);
        badge.style.display = n > 0 ? '' : 'none';
    };

    /* Product card hearts (GP_PLP_CTX must be set before this script). */
    function plpCtx() {
        return global.GP_PLP_CTX || {};
    }

    function plpSetBtnState(btn, inWishlist) {
        btn.classList.toggle('active', inWishlist);
        btn.classList.toggle('is-saved', inWishlist);
        btn.setAttribute('data-in-wishlist', inWishlist ? '1' : '0');
        btn.setAttribute('aria-pressed', inWishlist ? 'true' : 'false');
        btn.setAttribute('aria-label', inWishlist ? 'Remove from favourites' : 'Save to favourites');
        var icon = btn.querySelector('.gp-fav-btn__icon');
        if (icon) {
            icon.textContent = inWishlist ? '\u2665' : '\u2661';
        }
    }

    function plpLookupHas(pid) {
        var map = plpCtx().wishlist_lookup;
        if (!map || typeof map !== 'object') {
            return false;
        }
        var key = String(pid);
        var oids = map[key] || map[pid];
        return !!(oids && oids.length);
    }

    function plpPostWishlist(action, productId, variantId) {
        var url = plpCtx().request_url || '';
        if (!url) {
            return Promise.reject(new Error('no request url'));
        }
        var body = 'action=' + encodeURIComponent(action)
            + '&product_id=' + encodeURIComponent(String(productId));
        if (variantId > 0) {
            body += '&variant_id=' + encodeURIComponent(String(variantId));
        }
        body = global.webshopAppendCsrfParams(body);
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
                global.webshopUpdateCsrfFromJson(data);
                return { ok: res.ok, data: data };
            }).catch(function () {
                return { ok: res.ok, data: null };
            });
        });
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-wishlist-toggle]');
        if (!btn || btn.disabled) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        if (!plpCtx().is_logged_in) {
            var login = plpCtx().login_url || '';
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
        plpPostWishlist(action, productId, variantId)
            .then(function (result) {
                var data = result.data;
                if (data && data.status === 'SUCCESS') {
                    var nowIn = data.already_in_wishlist ? true : !inWishlist;
                    plpSetBtnState(btn, nowIn);
                    if (data.count !== undefined) {
                        global.webshopUpdateWishlistBadge(data.count);
                    }
                    document.querySelectorAll('[data-wishlist-toggle][data-product-id="' + productId + '"]').forEach(function (b) {
                        plpSetBtnState(b, nowIn);
                    });
                    return;
                }
                if (data && data.code === 'NOT_LOGGED_IN' && plpCtx().login_url) {
                    global.location.href = plpCtx().login_url + '?return_page=' + encodeURIComponent(global.location.href);
                    return;
                }
                alert((data && data.message) ? data.message : 'Unable to update favourites.');
            })
            .catch(function () {
                alert('Unable to update favourites.');
            })
            .then(function () {
                btn.disabled = false;
            });
    }, true);

    function plpInitWishlistButtons() {
        document.querySelectorAll('[data-wishlist-toggle]').forEach(function (b) {
            var pid = parseInt(b.getAttribute('data-product-id'), 10) || 0;
            if (pid > 0 && b.getAttribute('data-in-wishlist') !== '1' && plpLookupHas(pid)) {
                plpSetBtnState(b, true);
            }
        });
    }

    global.webshopInitWishlistCardButtons = plpInitWishlistButtons;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', plpInitWishlistButtons);
    } else {
        plpInitWishlistButtons();
    }
})(window);
