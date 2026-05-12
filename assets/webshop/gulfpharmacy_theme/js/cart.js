(function () {
    'use strict';
    var ctx = window.GP_CART_CTX || {};
    var requestUrl = typeof ctx.request_url === 'string' ? ctx.request_url : '';

    function remove_cart_item(hash) {
        if (!confirm('Are you sure you want to remove this item?')) return;
        if (!requestUrl) { location.reload(); return; }
        var fd = new FormData();
        fd.append('action', 'remove_cart_item');
        fd.append('cart_item_key', hash);
        fd.append('action_source', 'cart_page');

        fetch(requestUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (response) {
            // Backend returns a partial view; the safest path is to reload so
            // every total / mini-cart count is recomputed server-side.
            return response.text();
        }).then(function () { location.reload(); })
        .catch(function (error) {
            console.error('Cart remove failed:', error);
            alert('Failed to remove item. Please try again.');
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-cart-remove]');
        if (btn) {
            e.preventDefault();
            remove_cart_item(btn.getAttribute('data-cart-remove'));
        }
    });

    window.remove_cart_item = remove_cart_item;
})();
