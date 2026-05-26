/**
 * Gulf Pharmacy — unified My Account (tabs, lazy panels, profile/address AJAX).
 */
(function () {
    'use strict';

    var ctx = window.GP_MA_CTX || {};
    var currency = ctx.currency || '$';
    var webshopUrl = (ctx.webshop_url || '').replace(/\/$/, '');
    var endpoints = ctx.endpoints || {};

    /** Always POST via webshop_request (form + CSRF). Never hit /manage_address_webshop directly. */
    function maAjaxUrl() {
        if (endpoints.ajax) {
            return endpoints.ajax;
        }
        return webshopUrl + '/webshop_request';
    }

    function maPostAction(action, fields) {
        var parts = ['action=' + encodeURIComponent(action)];
        if (fields && typeof fields === 'object') {
            Object.keys(fields).forEach(function (key) {
                var val = fields[key];
                if (val !== undefined && val !== null) {
                    parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(val)));
                }
            });
        }
        var body = parts.join('&');
        if (typeof window.webshopAppendCsrfParams === 'function') {
            body = window.webshopAppendCsrfParams(body);
        } else if (window.GP_CSRF && window.GP_CSRF.name && window.GP_CSRF.hash) {
            body += '&' + encodeURIComponent(window.GP_CSRF.name) + '=' + encodeURIComponent(window.GP_CSRF.hash);
        }
        return fetch(maAjaxUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: body
        }).then(function (res) {
            return res.json().then(function (data) {
                if (typeof window.webshopUpdateCsrfFromJson === 'function') {
                    window.webshopUpdateCsrfFromJson(data);
                }
                return { ok: res.ok, status: res.status, data: data };
            }).catch(function () {
                return { ok: res.ok, status: res.status, data: null };
            });
        });
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function fmtMoney(n) {
        var x = parseFloat(n);
        if (isNaN(x)) {
            x = 0;
        }
        return currency + ' ' + x.toFixed(2);
    }

    function postJson(url, bodyObj) {
        var body = JSON.stringify(bodyObj || {});
        if (typeof window.webshopAppendCsrfParams === 'function' && url.indexOf('webshop_request') !== -1) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: window.webshopAppendCsrfParams('action=account_panel_data')
            }).then(function (r) { return r.json(); });
        }
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body
        }).then(function (r) { return r.json(); });
    }

    function fetchAccountPanel() {
        var url = maAjaxUrl();
        var fd = 'action=account_panel_data';
        if (typeof window.webshopAppendCsrfParams === 'function') {
            fd = window.webshopAppendCsrfParams(fd);
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: fd
        }).then(function (res) {
            return res.json().then(function (data) {
                if (typeof window.webshopUpdateCsrfFromJson === 'function') {
                    window.webshopUpdateCsrfFromJson(data);
                }
                return data;
            });
        });
    }

    function activateTab(tabId, pushHash) {
        var tabs = document.querySelectorAll('.ma-tab');
        var links = document.querySelectorAll('.ma-side-link[data-tab]');
        tabs.forEach(function (pane) {
            var on = pane.getAttribute('data-tab') === tabId;
            pane.classList.toggle('is-active', on);
        });
        links.forEach(function (btn) {
            var on = btn.getAttribute('data-tab') === tabId;
            btn.classList.toggle('is-active', on);
            if (on) {
                btn.setAttribute('aria-current', 'page');
            } else {
                btn.removeAttribute('aria-current');
            }
        });
        if (pushHash !== false && tabId) {
            try {
                history.replaceState(null, '', '#' + tabId);
            } catch (ignore) {}
        }
    }

    function initTabs() {
        var hash = (location.hash || '').replace(/^#/, '');
        var allowed = ['profile', 'orders', 'tracking', 'addresses', 'change_password'];
        var initial = ctx.active_tab || 'profile';
        if (hash && allowed.indexOf(hash) !== -1) {
            initial = hash;
        }
        activateTab(initial, false);

        document.querySelectorAll('.ma-side-link[data-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activateTab(btn.getAttribute('data-tab'));
            });
        });
    }

    function renderOrders(orders) {
        var root = document.getElementById('ma-orders-panel-root');
        var sub = document.getElementById('ma-orders-sub');
        if (!root) {
            return;
        }
        orders = Array.isArray(orders) ? orders : [];
        if (sub) {
            sub.textContent = orders.length + ' order' + (orders.length === 1 ? '' : 's') + ' placed';
        }
        if (!orders.length) {
            root.innerHTML = '<div class="ma-orders-empty">'
                + '<svg class="ma-orders-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
                + '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>'
                + '</svg><p class="ma-orders-empty-title">No orders yet</p>'
                + '<p class="ma-orders-empty-text">When you place your first order, you\'ll see it here.</p>'
                + '<a class="ma-btn ma-btn-primary" href="' + esc(webshopUrl) + '">Start shopping</a></div>';
            return;
        }
        var rows = '';
        orders.forEach(function (o) {
            var ref = o.reference_no || (o.order_id ? '#' + o.order_id : '');
            var date = o.date ? new Date(o.date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
            var status = (o.sale_status || 'pending').toLowerCase();
            var grand = parseFloat(o.grand_total) || 0;
            var hash = o.track_hash || '';
            var track = hash ? '<a class="ma-orders-action" href="' + esc(webshopUrl + '/track_order/' + hash) + '">Track</a>' : '';
            var idCell = hash
                ? '<a class="ma-orders-id" href="' + esc(webshopUrl + '/track_order/' + hash) + '">' + esc(ref) + '</a>'
                : '<span class="ma-orders-id">' + esc(ref) + '</span>';
            rows += '<tr><td data-label="Order #">' + idCell + '</td>'
                + '<td data-label="Date">' + esc(date) + '</td>'
                + '<td data-label="Status"><span class="ma-status" data-status="' + esc(status) + '">' + esc(status.charAt(0).toUpperCase() + status.slice(1)) + '</span></td>'
                + '<td class="ma-orders-total" data-label="Total">' + esc(fmtMoney(grand)) + '</td>'
                + '<td><div class="ma-orders-actions">' + track + '</div></td></tr>';
        });
        root.innerHTML = '<div class="ma-orders-table-wrap"><table class="ma-orders-table"><thead><tr>'
            + '<th scope="col">Order #</th><th scope="col">Date</th><th scope="col">Status</th>'
            + '<th scope="col">Total</th><th scope="col" aria-label="Actions"></th></tr></thead><tbody>'
            + rows + '</tbody></table></div>';
    }

    function renderAddresses(addresses) {
        var root = document.getElementById('ma-addr-panel-root');
        if (!root) {
            return;
        }
        addresses = Array.isArray(addresses) ? addresses : [];
        if (!addresses.length) {
            root.innerHTML = '<div class="ma-addr-empty" id="ma-addr-empty"><svg class="ma-addr-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
                + '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>'
                + '</svg><p>You haven\'t added any addresses yet. Add one to speed up checkout.</p></div>';
            return;
        }
        var html = '<div class="ma-addr-grid" id="ma-addr-list">';
        addresses.forEach(function (a) {
            var def = parseInt(a.is_default, 10) === 1;
            var lines = [a.line1, a.line2, [a.city, a.state, a.postal_code].filter(Boolean).join(', '), a.country].filter(Boolean).join('\n');
            var jsonAttr = JSON.stringify(a).replace(/"/g, '&quot;');
            html += '<article class="ma-addr-card' + (def ? ' is-default' : '') + '">';
            if (def) {
                html += '<span class="ma-addr-badge">Default</span>';
            }
            html += '<p class="ma-addr-name">' + esc(a.address_name || 'Saved address') + '</p>';
            html += '<p class="ma-addr-body">' + esc(lines) + '</p>';
            if (a.phone) {
                html += '<p class="ma-addr-phone">' + esc(a.phone) + '</p>';
            }
            html += '<div class="ma-addr-actions">';
            html += '<button type="button" class="ma-addr-action" data-addr-edit="' + jsonAttr + '">Edit</button>';
            if (!def && a.id) {
                html += '<button type="button" class="ma-addr-action" data-addr-default="' + esc(a.id) + '">Set as default</button>';
            }
            if (a.id) {
                html += '<button type="button" class="ma-addr-action" data-addr-delete="' + esc(a.id) + '" data-danger="true">Delete</button>';
            }
            html += '</div></article>';
        });
        html += '</div>';
        root.innerHTML = html;
        bindAddressActions();
    }

    var geoCache = { states: [], countries: [] };

    function hydrateGeo(data) {
        geoCache.states = data.state_list || [];
        geoCache.countries = data.countries || [];
        var countrySel = document.getElementById('ma-addr-country');
        var stateSel = document.getElementById('ma-addr-state');
        if (countrySel && countrySel.options.length <= 1 && geoCache.countries.length) {
            geoCache.countries.forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = c.name;
                opt.textContent = c.name;
                opt.setAttribute('data-id', String(c.id || ''));
                countrySel.appendChild(opt);
            });
        }
        if (stateSel && stateSel.options.length <= 1 && geoCache.states.length) {
            rebuildStateOptions('');
        }
    }

    function rebuildStateOptions(countryId) {
        var stateSel = document.getElementById('ma-addr-state');
        if (!stateSel) {
            return;
        }
        var prev = stateSel.value;
        stateSel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = countryId ? 'Select state' : 'Select country first';
        stateSel.appendChild(ph);
        geoCache.states.forEach(function (s) {
            if (countryId && String(s.country_id) !== String(countryId)) {
                return;
            }
            var opt = document.createElement('option');
            opt.value = s.name;
            opt.textContent = s.name;
            opt.setAttribute('data-country-id', String(s.country_id || ''));
            stateSel.appendChild(opt);
        });
        if (prev) {
            stateSel.value = prev;
        }
    }

    function initLazyPanels() {
        if (!ctx.lazy_panel) {
            return;
        }
        fetchAccountPanel().then(function (data) {
            if (!data || data.status !== 'OK') {
                return;
            }
            hydrateGeo(data);
            if (document.getElementById('ma-orders-lazy')) {
                renderOrders(data.orders);
            }
            if (document.getElementById('ma-addr-lazy')) {
                renderAddresses(data.addresses);
            }
        }).catch(function () {});
    }

    function showBanner(form, msg, ok) {
        var b = form.querySelector('.ma-banner-inline');
        if (!b) {
            return;
        }
        b.hidden = false;
        b.textContent = msg;
        b.className = 'ma-banner ma-banner-inline ' + (ok ? 'ma-banner-success' : 'ma-banner-error');
    }

    function initProfileForm() {
        var form = document.getElementById('ma-profile-form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!form.checkValidity()) {
                form.classList.add('is-invalid');
                return;
            }
            form.classList.remove('is-invalid');
            var payload = {
                fName: (document.getElementById('ma-fName') || {}).value || '',
                email: (document.getElementById('ma-email') || {}).value || '',
                dob: (document.getElementById('ma-dob') || {}).value || ''
            };
            maPostAction('profile_update_webshop', payload).then(function (wrap) {
                var res = wrap && wrap.data ? wrap.data : wrap;
                var ok = res && res.statusMessage === 'success';
                showBanner(form, ok ? 'Profile updated successfully.' : 'Could not update profile. Please try again.', ok);
            }).catch(function () {
                showBanner(form, 'Network error. Please try again.', false);
            });
        });
    }

    var modalBackdrop = document.getElementById('ma-addr-modal-backdrop');
    var modal = document.getElementById('ma-addr-modal');
    var addrForm = document.getElementById('ma-addr-form');

    function openAddrModal(mode, data) {
        if (!modal || !modalBackdrop) {
            return;
        }
        data = data || {};
        document.getElementById('ma-addr-action').value = mode === 'edit' ? 'edit' : 'add';
        document.getElementById('ma-addr-id').value = data.id || '';
        document.getElementById('ma-addr-modal-title').textContent = mode === 'edit' ? 'Edit address' : 'Add new address';
        document.getElementById('ma-addr-name').value = data.address_name || '';
        document.getElementById('ma-addr-line1').value = data.line1 || '';
        document.getElementById('ma-addr-line2').value = data.line2 || '';
        document.getElementById('ma-addr-city').value = data.city || '';
        document.getElementById('ma-addr-postal').value = data.postal_code || '';
        document.getElementById('ma-addr-phone').value = data.phone || '';
        document.getElementById('ma-addr-email').value = data.email_id || '';
        document.getElementById('ma-addr-default').checked = parseInt(data.is_default, 10) === 1;
        var countrySel = document.getElementById('ma-addr-country');
        var stateSel = document.getElementById('ma-addr-state');
        if (countrySel && data.country) {
            countrySel.value = data.country;
            var opt = countrySel.options[countrySel.selectedIndex];
            var cid = opt ? opt.getAttribute('data-id') : '';
            rebuildStateOptions(cid || '');
        }
        if (stateSel && data.state) {
            stateSel.value = data.state;
        }
        modal.classList.add('is-open');
        modalBackdrop.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeAddrModal() {
        if (!modal || !modalBackdrop) {
            return;
        }
        modal.classList.remove('is-open');
        modalBackdrop.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function bindAddressActions() {
        document.querySelectorAll('[data-addr-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                try {
                    openAddrModal('edit', JSON.parse(btn.getAttribute('data-addr-edit')));
                } catch (e1) {}
            });
        });
        document.querySelectorAll('[data-addr-delete]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-addr-delete');
                if (!id || !confirm('Delete this address?')) {
                    return;
                }
                window.location.href = (endpoints.address_delete || (webshopUrl + '/address_delete')) + '/' + id;
            });
        });
        document.querySelectorAll('[data-addr-default]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-addr-default');
                var base = endpoints.set_default || (webshopUrl + '/address_set_default/' + (ctx.customer_id || 0));
                window.location.href = base + '/' + id;
            });
        });
    }

    function initAddressModal() {
        var addBtn = document.getElementById('ma-addr-add-btn');
        if (addBtn) {
            addBtn.addEventListener('click', function () { openAddrModal('add', {}); });
        }
        document.querySelectorAll('[data-ma-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', closeAddrModal);
        });
        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', closeAddrModal);
        }
        var countrySel = document.getElementById('ma-addr-country');
        if (countrySel) {
            countrySel.addEventListener('change', function () {
                var opt = countrySel.options[countrySel.selectedIndex];
                rebuildStateOptions(opt ? opt.getAttribute('data-id') : '');
            });
        }
        bindAddressActions();

        if (addrForm) {
            addrForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var action = document.getElementById('ma-addr-action').value;
                var stateVal = (document.getElementById('ma-addr-state') || {}).value || '';
                var payload = {
                    addressAction: action,
                    address_id: document.getElementById('ma-addr-id').value,
                    address_name: document.getElementById('ma-addr-name').value,
                    line1: document.getElementById('ma-addr-line1').value,
                    line2: document.getElementById('ma-addr-line2').value,
                    city: document.getElementById('ma-addr-city').value,
                    postal_code: document.getElementById('ma-addr-postal').value,
                    state: stateVal,
                    country: (document.getElementById('ma-addr-country') || {}).value,
                    phone: document.getElementById('ma-addr-phone').value,
                    email_id: document.getElementById('ma-addr-email').value,
                    is_default: document.getElementById('ma-addr-default').checked ? 1 : 0
                };
                maPostAction('manage_address_webshop', payload).then(function (wrap) {
                    var res = wrap && wrap.data ? wrap.data : wrap;
                    if (res && res.statusMessage === 'success') {
                        closeAddrModal();
                        location.reload();
                        return;
                    }
                    if (wrap && wrap.status === 403) {
                        showBanner(addrForm, 'Session expired. Please refresh the page and try again.', false);
                        return;
                    }
                    showBanner(addrForm, 'Could not save address.', false);
                }).catch(function () {
                    showBanner(addrForm, 'Network error.', false);
                });
            });
        }
    }

    function initTrackingForms() {
        document.querySelectorAll('.ma-track-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var input = form.querySelector('.ma-track-code');
                var code = input ? String(input.value || '').trim() : '';
                if (!code) {
                    return;
                }
                var base = form.getAttribute('data-tracking-base') || webshopUrl;
                window.location.href = base.replace(/\/$/, '') + '/track_order/' + encodeURIComponent(code);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTabs();
        initLazyPanels();
        initProfileForm();
        initAddressModal();
        initTrackingForms();
    });
})();
