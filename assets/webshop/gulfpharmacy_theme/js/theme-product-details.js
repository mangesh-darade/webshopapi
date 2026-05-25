(function () {
    'use strict';
    var ctx = window.GP_PD_CTX || {};
    var pdNoImageSrc = typeof ctx.no_image_src === 'string' ? ctx.no_image_src : '';
    var endpoint = (typeof ctx.webshop_request_url === 'string' && ctx.webshop_request_url !== '')
        ? ctx.webshop_request_url
        : ((typeof window.baseUrl === 'string' ? window.baseUrl : (ctx.base_url || '')) + 'webshop_request');
    var isLoggedIn = !!ctx.is_logged_in;
    var loginUrl = typeof ctx.login_url === 'string' ? ctx.login_url : '';
    var checkoutUrl = typeof ctx.checkout_url === 'string' ? ctx.checkout_url : '';
    var productId = parseInt(ctx.product_id, 10) || 0;
    var overselling = !!ctx.overselling;
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
        var payload = {
            action: 'add_to_cart',
            product_id: parseInt(btn.getAttribute('product_id'), 10) || 0,
            product_price: Number(btn.getAttribute('product_price')) || 0,
            quantity: qty,
            tax_rate: Number(btn.getAttribute('tax_rate')) || 0,
            tax_method: Number(btn.getAttribute('tax_method')) || 0,
            price: Number(btn.getAttribute('price')) || 0,
            promotion_price: Number(btn.getAttribute('promotion_price')) || 0
        };
        var vid = parseInt(btn.getAttribute('data-variant-id'), 10) || 0;
        if (vid > 0) {
            payload.variant_id = vid;
            payload.variant_price = Number(btn.getAttribute('data-variant-price')) || 0;
            payload.variant_unit_quantity = Number(btn.getAttribute('data-variant-unit-quantity')) || 1;
        }
        return payload;
    }

    var addBtn = document.querySelector('.add-to-cart');
    var buyBtn = document.querySelector('.buy-now');
    var wishBtn = document.getElementById('pdWishlistBtn');
    var pdWrap = document.querySelector('.pd-wrap');
    var stockEl = document.getElementById('pdStock');
    var oosNote = document.getElementById('pdOosNote');
    var priceNow = document.getElementById('price-current');
    var priceMrp = document.getElementById('price-mrp');
    var priceOff = document.getElementById('price-off');
    var variantBtns = document.querySelectorAll('#pdVariants .pd-variant-btn');

    function setCartBlocked(blocked) {
        cartBlocked = !!blocked;
        if (pdWrap) {
            pdWrap.classList.toggle('pd-wrap--oos', blocked);
        }
        if (stockEl) {
            stockEl.textContent = blocked ? 'Out of Stock' : 'In Stock';
            stockEl.classList.toggle('ok', !blocked);
            stockEl.classList.toggle('no', blocked);
        }
        if (oosNote) {
            oosNote.style.display = blocked ? '' : 'none';
        }
        if (addBtn) { addBtn.disabled = blocked; }
        if (buyBtn) { buyBtn.disabled = blocked; }
        if (qInc) { qInc.disabled = blocked; }
        if (qDec) { qDec.disabled = blocked; }
        if (q) { q.disabled = blocked; }
    }

    function applyVariant(btn) {
        if (!btn) { return; }
        for (var vi = 0; vi < variantBtns.length; vi++) {
            variantBtns[vi].classList.remove('active');
            variantBtns[vi].setAttribute('aria-pressed', 'false');
        }
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');

        var inStock = btn.getAttribute('data-in-stock') === '1' || overselling;
        setCartBlocked(!inStock);

        if (priceNow) {
            priceNow.textContent = btn.getAttribute('data-formatted-price') || priceNow.textContent;
        }
        if (priceMrp) {
            var fm = btn.getAttribute('data-formatted-mrp') || '';
            priceMrp.textContent = fm;
            priceMrp.style.display = fm !== '' ? '' : 'none';
        }
        if (priceOff) {
            var dp = parseInt(btn.getAttribute('data-discount-percent'), 10) || 0;
            if (dp > 0) {
                priceOff.textContent = dp + '% OFF';
                priceOff.style.display = '';
            } else {
                priceOff.style.display = 'none';
            }
        }

        if (addBtn) {
            addBtn.setAttribute('data-variant-id', btn.getAttribute('data-variant-id') || '0');
            addBtn.setAttribute('data-variant-price', btn.getAttribute('data-variant-price') || '0');
            addBtn.setAttribute('data-variant-unit-quantity', btn.getAttribute('data-unit-quantity') || '1');
            var unitPrice = parseFloat(btn.getAttribute('data-unit-price')) || 0;
            var promoPrice = parseFloat(btn.getAttribute('data-promo-price')) || 0;
            var sell = unitPrice;
            if (promoPrice > 0 && (unitPrice <= 0 || promoPrice < unitPrice)) {
                sell = promoPrice;
            }
            addBtn.setAttribute('product_price', String(sell));
            addBtn.setAttribute('price', String(sell));
            addBtn.setAttribute('promotion_price', String(promoPrice));
        }

        if (q) {
            var vq = parseFloat(btn.getAttribute('data-quantity')) || 0;
            if (vq > 0 && !overselling) {
                var mx = Math.max(1, Math.floor(vq));
                q.setAttribute('max', String(mx));
                if (parseInt(q.value, 10) > mx) {
                    q.value = String(mx);
                }
            }
        }
    }

    for (var vj = 0; vj < variantBtns.length; vj++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                if (btn.classList.contains('pd-variant-btn--oos') && !overselling) {
                    return;
                }
                applyVariant(btn);
            });
        })(variantBtns[vj]);
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

    function parseResponse(resText) {
        try { return JSON.parse(resText); } catch (e) { return null; }
    }

    function toggleWishUi(inWishlist) {
        var wish = document.getElementById('pdWishlistBtn');
        if (!wish) { return; }
        wish.classList.toggle('active', inWishlist);
        wish.classList.toggle('is-saved', inWishlist);
        wish.setAttribute('data-in-wishlist', inWishlist ? '1' : '0');
        wish.setAttribute('aria-pressed', inWishlist ? 'true' : 'false');
        wish.setAttribute('aria-label', inWishlist ? 'Remove from favourites' : 'Save to favourites');
        var icon = wish.querySelector('.gp-fav-btn__icon');
        if (icon) {
            icon.textContent = inWishlist ? '\u2665' : '\u2661';
        }
        var label = wish.querySelector('.gp-fav-btn__label');
        if (label) {
            label.textContent = inWishlist ? 'Saved' : 'Save';
        }
    }

    function updateWishlistBadge(count) {
        if (typeof window.webshopUpdateWishlistBadge === 'function') {
            window.webshopUpdateWishlistBadge(count);
            return;
        }
        var badge = document.querySelector('.gp-wishlist-count');
        if (!badge) { return; }
        var n = parseInt(count, 10);
        if (isNaN(n) || n < 0) { n = 0; }
        badge.textContent = String(n);
        badge.style.display = n > 0 ? '' : 'none';
    }

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
                        if (data.count !== undefined) {
                            updateWishlistBadge(data.count);
                        }
                        return;
                    }
                    if (data && data.code === 'NOT_LOGGED_IN' && loginUrl) {
                        window.location.href = loginUrl + '?return_page=' + encodeURIComponent(window.location.href);
                        return;
                    }
                    alert(failMessage(data, 'Unable to update favourites.'));
                })
                .catch(function () {
                    alert('Unable to update favourites.');
                });
        });
    }
})();
