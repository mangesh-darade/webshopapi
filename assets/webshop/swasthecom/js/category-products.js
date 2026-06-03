// Category products interactions: image frame state + add-to-cart action.
(function () {
    'use strict';

    function bindImageFrames(root) {
        var scope = root || document;
        var frames = scope.querySelectorAll('.pc-img-frame');
        for (var f = 0; f < frames.length; f++) {
            var frame = frames[f];
            if (frame.querySelector('.pc-no-image')) {
                frame.classList.remove('is-loading');
            }
        }
        var images = scope.querySelectorAll('.pc-product-img');
        for (var i = 0; i < images.length; i++) {
            (function (img) {
                if (img.__pc_img_wired) return;
                img.__pc_img_wired = true;
                var clearLoading = function () {
                    var frame = img.closest ? img.closest('.pc-img-frame') : null;
                    if (frame) frame.classList.remove('is-loading');
                };
                img.addEventListener('load', clearLoading);
                img.addEventListener('error', clearLoading);
                if (img.complete) {
                    clearLoading();
                }
            })(images[i]);
        }
    }

    function addToCart(btn) {
        if (!btn || btn.disabled || btn.getAttribute('aria-disabled') === 'true') return;
        var itemId = parseInt(btn.getAttribute('data-item-id'), 10) || 0;
        if (!itemId) return;

        var requestUrl = window.GP_PLP_CTX && window.GP_PLP_CTX.request_url ? window.GP_PLP_CTX.request_url : '';
        if (!requestUrl) return;

        var originalLabel = btn.getAttribute('data-label-default') || (btn.textContent || '').trim();
        btn.disabled = true;
        btn.textContent = 'Adding...';

        var listPrice = btn.getAttribute('data-product-price') || '';
        var variantId = parseInt(btn.getAttribute('data-variant-id'), 10) || 0;
        var variantPrice = btn.getAttribute('data-variant-price') || '0';
        var variantUq = btn.getAttribute('data-variant-unit-quantity') || '1';

        var body = 'action=add_to_cart&product_id=' + encodeURIComponent(itemId) + '&quantity=1&variant_id=' + encodeURIComponent(variantId);
        if (listPrice !== '' && parseFloat(listPrice) > 0) {
            body += '&product_price=' + encodeURIComponent(listPrice) + '&price=' + encodeURIComponent(listPrice);
        }
        if (variantId > 0) {
            body += '&variant_price=' + encodeURIComponent(variantPrice) + '&variant_unit_quantity=' + encodeURIComponent(variantUq);
        }
        if (typeof window.webshopAppendCsrfParams === 'function') {
            body = window.webshopAppendCsrfParams(body);
        }

        fetch(requestUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body,
            credentials: 'same-origin'
        })
        .then(function (r) {
            if (r.status === 403) {
                return { status: 'FAIL', error: 'csrf', message: 'Session expired. Please refresh the page and try again.' };
            }
            return r.json();
        })
        .then(function (d) {
            if (typeof window.webshopUpdateCsrfFromJson === 'function') {
                window.webshopUpdateCsrfFromJson(d);
            }
            if (d && d.error === 'csrf') {
                btn.textContent = 'Refresh page';
                btn.disabled = true;
                return;
            }
            if (d && (d.status === 'SUCCESS' || d.success)) {
                btn.textContent = 'Added';
                btn.classList.add('is-added');
                setTimeout(function () {
                    btn.textContent = originalLabel;
                    btn.classList.remove('is-added');
                    btn.disabled = false;
                }, 1800);
                var badge = document.querySelector('.gp-cart-count, .cart-count, [data-cart-count]');
                if (badge && d.cart_count !== undefined) {
                    badge.textContent = d.cart_count;
                    badge.style.display = d.cart_count > 0 ? '' : 'none';
                }
                return;
            }
            if (d && (d.error === 'out_of_stock' || (d.message && /out of stock/i.test(d.message)))) {
                btn.textContent = 'Out of stock';
                btn.classList.add('is-disabled');
                btn.setAttribute('aria-disabled', 'true');
                setTimeout(function () { btn.textContent = originalLabel; }, 2500);
                return;
            }
            btn.textContent = 'Try again';
            setTimeout(function () { btn.textContent = originalLabel; btn.disabled = false; }, 2000);
        })
        .catch(function () {
            btn.textContent = originalLabel;
            btn.disabled = false;
        });
    }

    function bindAddToCart(root) {
        var scope = root || document;
        var buttons = scope.querySelectorAll('.pc-btn-cart[data-item-id]');
        for (var i = 0; i < buttons.length; i++) {
            (function (btn) {
                if (btn.__pc_cart_wired) return;
                btn.__pc_cart_wired = true;
                btn.addEventListener('click', function () { addToCart(btn); });
            })(buttons[i]);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindImageFrames(document);
        bindAddToCart(document);
    });
})();
