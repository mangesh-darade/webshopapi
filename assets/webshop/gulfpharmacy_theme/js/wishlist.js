(function () {
    'use strict';

    var ctx = window.GP_WISHLIST_CTX || {};
    var endpoint = typeof ctx.request_url === 'string' ? ctx.request_url : '';

    function showToast(msg) {
        var el = document.getElementById('wlToast');
        if (!el) {
            return;
        }
        el.textContent = msg;
        el.classList.add('is-visible');
        clearTimeout(el._wlHide);
        el._wlHide = setTimeout(function () {
            el.classList.remove('is-visible');
        }, 2800);
    }

    function postAction(payload) {
        var body = new URLSearchParams(payload).toString();
        if (typeof window.webshopAppendCsrfParams === 'function') {
            body = window.webshopAppendCsrfParams(body);
        }
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body,
            credentials: 'same-origin'
        }).then(function (res) { return res.text(); });
    }

    function parseResponse(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function updateBadges(count) {
        if (typeof window.webshopUpdateWishlistBadge === 'function') {
            window.webshopUpdateWishlistBadge(count);
        }
        var pill = document.getElementById('wlCountPill');
        if (pill) {
            var n = parseInt(count, 10) || 0;
            if (n <= 0) {
                pill.style.display = 'none';
            } else {
                pill.style.display = '';
                pill.innerHTML = '<span class="wl-count-heart" aria-hidden="true">&#9829;</span> ' + n + (n === 1 ? ' item' : ' items') + ' saved';
            }
        }
    }

    function findWishlistCard(btn) {
        if (!btn) {
            return null;
        }
        return btn.closest('.wl-card');
    }

    function removeFromWishlist(btn) {
        if (!btn || !confirm('Remove this item from your wishlist?')) {
            return;
        }
        if (!endpoint) {
            showToast('Unable to remove item. Please try again.');
            return;
        }
        var productId = parseInt(btn.getAttribute('data-wishlist-remove'), 10) || 0;
        var variantId = parseInt(btn.getAttribute('data-variant-id'), 10) || 0;
        var card = findWishlistCard(btn);
        var payload = {
            action: 'remove_from_wishlist',
            product_id: productId
        };
        if (variantId > 0) {
            payload.variant_id = variantId;
        }
        postAction(payload)
            .then(function (text) {
                var data = parseResponse(text);
                if (data && data.status === 'SUCCESS') {
                    if (card) {
                        card.classList.add('is-removing');
                        setTimeout(function () {
                            card.remove();
                            var remaining = document.querySelectorAll('.wl-card').length;
                            if (data.count !== undefined) {
                                updateBadges(data.count);
                            } else {
                                updateBadges(remaining);
                            }
                            var grid = document.getElementById('wlGrid');
                            if (remaining === 0) {
                                window.location.reload();
                            } else if (grid && remaining === 1) {
                                grid.classList.add('wl-grid--single');
                            }
                        }, 280);
                    } else {
                        window.location.reload();
                    }
                    showToast('Removed from wishlist');
                    return;
                }
                if (data && data.code === 'NOT_LOGGED_IN' && ctx.login_url) {
                    window.location.href = ctx.login_url + '?return_page=' + encodeURIComponent(window.location.href);
                    return;
                }
                showToast((data && data.message) ? data.message : 'Could not remove item. Please try again.');
            })
            .catch(function () {
                showToast('Could not remove item. Please try again.');
            });
    }

    function addToCart(btn) {
        if (!endpoint || !btn) {
            return;
        }
        var productId = parseInt(btn.getAttribute('data-wishlist-add'), 10) || 0;
        var variantId = parseInt(btn.getAttribute('data-variant-id'), 10) || 0;
        var original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Adding…';
        var payload = {
            action: 'add_to_cart',
            product_id: productId,
            quantity: 1,
            product_price: Number(btn.getAttribute('data-product-price')) || 0,
            price: Number(btn.getAttribute('data-product-price')) || 0
        };
        if (variantId > 0) {
            payload.variant_id = variantId;
            payload.variant_price = Number(btn.getAttribute('data-variant-price')) || 0;
            payload.variant_unit_quantity = Number(btn.getAttribute('data-variant-unit-quantity')) || 1;
        }
        postAction(payload)
            .then(function (text) {
                var data = parseResponse(text);
                btn.disabled = false;
                btn.textContent = original;
                if (data && data.status === 'SUCCESS') {
                    var cartBadge = document.querySelector('.gp-cart-count, .cart-count');
                    if (cartBadge && data.cart_count !== undefined) {
                        cartBadge.textContent = data.cart_count;
                        cartBadge.style.display = data.cart_count > 0 ? '' : 'none';
                    }
                    showToast('Added to cart');
                    return;
                }
                var msg = (data && data.message) ? data.message : 'Unable to add to cart.';
                showToast(msg);
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = original;
                showToast('Unable to add to cart.');
            });
    }

    document.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-wishlist-remove]');
        if (rm) {
            e.preventDefault();
            removeFromWishlist(rm);
            return;
        }
        var ac = e.target.closest('[data-wishlist-add]');
        if (ac) {
            e.preventDefault();
            addToCart(ac);
        }
    });

    window.removeFromWishlist = removeFromWishlist;
})();
