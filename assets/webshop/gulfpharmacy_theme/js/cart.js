(function () {
    'use strict';

    var ctx = window.GP_CART_CTX || {};
    var requestUrl = typeof ctx.request_url === 'string' ? ctx.request_url : '';
    var currency = typeof ctx.currency === 'string' ? ctx.currency : '$';

    function postCartAction(formData) {
        if (!requestUrl) {
            return Promise.reject(new Error('Cart request URL missing'));
        }
        return fetch(requestUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
    }

    function remove_cart_item(hash) {
        if (!confirm('Are you sure you want to remove this item?')) {
            return;
        }
        if (!requestUrl) {
            location.reload();
            return;
        }
        var fd = new FormData();
        fd.append('action', 'remove_cart_item');
        fd.append('cart_item_key', hash);
        fd.append('action_source', 'cart_page');

        postCartAction(fd)
            .then(function () { location.reload(); })
            .catch(function (error) {
                console.error('Cart remove failed:', error);
                alert('Failed to remove item. Please try again.');
            });
    }

    function update_cart_qty(hash, qty) {
        qty = parseInt(qty, 10);
        if (!hash || qty < 1) {
            return;
        }
        if (!requestUrl) {
            location.reload();
            return;
        }

        var card = document.querySelector('[data-cart-line="' + hash + '"]');
        if (card) {
            card.classList.add('cart-item-card--updating');
        }

        var fd = new FormData();
        fd.append('action', 'update_cart');
        fd.append('itemKey', hash);
        fd.append('itemQty', String(qty));

        postCartAction(fd)
            .then(function (response) { return response.text(); })
            .then(function (text) {
                if (String(text).trim() === 'SUCCESS') {
                    location.reload();
                    return;
                }
                if (card) {
                    card.classList.remove('cart-item-card--updating');
                }
                alert('Could not update quantity. The item may be out of stock.');
            })
            .catch(function (error) {
                console.error('Cart update failed:', error);
                if (card) {
                    card.classList.remove('cart-item-card--updating');
                }
                alert('Failed to update quantity. Please try again.');
            });
    }

    document.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('[data-cart-remove]');
        if (removeBtn) {
            e.preventDefault();
            remove_cart_item(removeBtn.getAttribute('data-cart-remove'));
            return;
        }

        var qtyBtn = e.target.closest('[data-qty-change]');
        if (!qtyBtn) {
            return;
        }
        e.preventDefault();

        var hash = qtyBtn.getAttribute('data-hash');
        var input = document.querySelector('.qty-input[data-hash="' + hash + '"]');
        if (!input) {
            return;
        }

        var current = parseInt(input.value, 10) || 1;
        var next = current;
        if (qtyBtn.getAttribute('data-qty-change') === 'plus') {
            next = current + 1;
        } else {
            next = Math.max(1, current - 1);
        }

        if (next === current) {
            return;
        }

        input.value = String(next);
        update_cart_qty(hash, next);
    });

    window.remove_cart_item = remove_cart_item;
    window.update_cart_qty = update_cart_qty;
})();
