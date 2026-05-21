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
})(window);
