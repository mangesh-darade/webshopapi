(function () {
    'use strict';
    var ctx = window.GP_PD_CTX || {};
    var pdNoImageSrc = typeof ctx.no_image_src === 'string' ? ctx.no_image_src : '';
    var endpoint = (typeof window.baseUrl === 'string' ? window.baseUrl : (ctx.base_url || '')) + 'webshop_request';
    var isLoggedIn = !!ctx.is_logged_in;
    var loginUrl = typeof ctx.login_url === 'string' ? ctx.login_url : '';
    var checkoutUrl = typeof ctx.checkout_url === 'string' ? ctx.checkout_url : '';
    var productId = parseInt(ctx.product_id, 10) || 0;
    var cartBlocked = (ctx.in_stock === false);

    function failMessage(data, fallback) {
        if (data && typeof data.message === 'string' && data.message !== '') {
            return data.message;
        }
        if (data && typeof data.error === 'string' && data.error !== '') {
            return data.error;
        }
        return fallback;
    }

    var t = document.querySelectorAll('#pdThumbs .pd-thumb');
    var m = document.getElementById('pdMainImg');
    for (var i = 0; i < t.length; i++) {
        (function (ix) {
            t[ix].addEventListener('click', function () {
                for (var j = 0; j < t.length; j++) t[j].classList.remove('active');
                this.classList.add('active');
                if (!m) return;
                m.style.opacity = '0.25';
                var im = new Image();
                im.onload = function () { m.src = t[ix].getAttribute('data-full'); m.style.opacity = '1'; };
                im.onerror = function () { if (pdNoImageSrc) m.src = pdNoImageSrc; m.style.opacity = '1'; };
                im.src = t[ix].getAttribute('data-full');
            });
        })(i);
    }

    var q = document.getElementById('qVal');
    var qInc = document.getElementById('qInc');
    var qDec = document.getElementById('qDec');
    if (q && qInc) {
        qInc.onclick = function () {
            if (cartBlocked) { return; }
            q.value = parseInt(q.value || '1', 10) + 1;
            var mx = parseInt(q.getAttribute('max'), 10);
            if (mx > 0 && parseInt(q.value, 10) > mx) { q.value = mx; }
        };
    }
    if (q && qDec) {
        qDec.onclick = function () {
            if (cartBlocked) { return; }
            var v = parseInt(q.value || '1', 10);
            q.value = v > 1 ? v - 1 : 1;
        };
    }

    function tabs(id) {
        var n = document.getElementById(id); if (!n) return;
        var l = n.querySelectorAll('.pd-tab-link');
        for (var i = 0; i < l.length; i++) {
            l[i].onclick = function () {
                for (var j = 0; j < l.length; j++) l[j].classList.remove('active');
                this.classList.add('active');
                var all = document.querySelectorAll('.pd-tab');
                for (var k = 0; k < all.length; k++) all[k].classList.remove('active');
                var p = document.getElementById(this.getAttribute('data-tab'));
                if (p) p.classList.add('active');
            };
        }
    }
    tabs('pdTabNav');

    function buildCartPayload(btn) {
        var qty = parseInt((q || { value: '1' }).value, 10) || 1;
        if (qty < 1) qty = 1;
        return {
            action: 'add_to_cart',
            product_id: parseInt(btn.getAttribute('product_id'), 10) || 0,
            product_price: Number(btn.getAttribute('product_price')) || 0,
            quantity: qty,
            tax_rate: Number(btn.getAttribute('tax_rate')) || 0,
            tax_method: Number(btn.getAttribute('tax_method')) || 0,
            price: Number(btn.getAttribute('price')) || 0,
            promotion_price: Number(btn.getAttribute('promotion_price')) || 0
        };
    }

    function postAction(payload) {
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(payload).toString(),
            credentials: 'same-origin'
        }).then(function (res) { return res.text(); });
    }

    function parseResponse(resText) {
        try { return JSON.parse(resText); } catch (e) { return null; }
    }

    function toggleWishUi(inWishlist) {
        var wish = document.getElementById('pdWishlistBtn');
        if (!wish) { return; }
        wish.classList.toggle('active', inWishlist);
        wish.setAttribute('data-in-wishlist', inWishlist ? '1' : '0');
        wish.textContent = inWishlist ? '♥' : '♡';
    }

    var addBtn = document.querySelector('.add-to-cart');
    var buyBtn = document.querySelector('.buy-now');
    var wishBtn = document.getElementById('pdWishlistBtn');

    if (addBtn) {
        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (cartBlocked) {
                return;
            }
            var btn = this;
            var payload = buildCartPayload(btn);
            if (!payload.product_id) {
                alert('Invalid product.');
                return;
            }

            var original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Adding...';
            postAction(payload)
                .then(function (resText) {
                    var data = parseResponse(resText);
                    if (data && data.status === 'SUCCESS') {
                        btn.textContent = 'Added';
                        var badge = document.querySelector('.gp-cart-count, .cart-count');
                        if (badge && data.cart_count !== undefined) {
                            badge.textContent = data.cart_count;
                            badge.style.display = data.cart_count > 0 ? '' : 'none';
                        }
                        setTimeout(function () { btn.textContent = original; btn.disabled = false; }, 600);
                        return;
                    }
                    btn.disabled = false;
                    btn.textContent = original;
                    alert(failMessage(data, 'Unable to add item to cart. Please try again.'));
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = original;
                    alert('Unable to add item to cart. Please try again.');
                });
        });
    }

    if (buyBtn) {
        buyBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (cartBlocked) {
                return;
            }
            if (!addBtn) {
                alert('Product action unavailable.');
                return;
            }
            var payload = buildCartPayload(addBtn);
            payload.action = 'buy_now';
            if (!payload.product_id) {
                alert('Invalid product.');
                return;
            }
            postAction(payload)
                .then(function (resText) {
                    var data = parseResponse(resText);
                    if (data && data.status === 'SUCCESS') {
                        window.location.href = (data.checkout_url ? data.checkout_url : checkoutUrl);
                        return;
                    }
                    alert(failMessage(data, 'Unable to proceed to checkout. Please try again.'));
                })
                .catch(function () {
                    alert('Unable to proceed to checkout. Please try again.');
                });
        });
    }

    if (wishBtn) {
        wishBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!isLoggedIn) {
                window.location.href = loginUrl + '?return_page=' + encodeURIComponent(window.location.href);
                return;
            }
            var inWishlist = this.getAttribute('data-in-wishlist') === '1';
            var payload = {
                action: inWishlist ? 'remove_from_wishlist' : 'add_to_wishlist',
                product_id: productId
            };
            postAction(payload)
                .then(function (resText) {
                    var data = parseResponse(resText);
                    if (data && data.status === 'SUCCESS') {
                        toggleWishUi(!inWishlist);
                        return;
                    }
                    alert('Unable to update favourites.');
                })
                .catch(function () {
                    alert('Unable to update favourites.');
                });
        });
    }
})();
