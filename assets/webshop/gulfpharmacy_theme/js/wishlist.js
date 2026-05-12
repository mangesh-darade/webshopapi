(function () {
    'use strict';
    var ctx = window.GP_WISHLIST_CTX || {};
    var removeBase = typeof ctx.remove_url === 'string' ? ctx.remove_url : '';
    var addBase = typeof ctx.add_to_cart_url === 'string' ? ctx.add_to_cart_url : '';

    function removeFromWishlist(productId) {
        if (!confirm('Are you sure you want to remove this item?')) return;
        if (!removeBase) return;
        fetch(removeBase + productId, { method: 'POST', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.status === 'SUCCESS') {
                    var el = document.getElementById('wishlist-item-' + productId);
                    if (el) {
                        el.style.opacity = '0';
                        setTimeout(function () {
                            el.remove();
                            if (document.querySelectorAll('.wishlist-card').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                }
            });
    }

    function addToCart(productId) {
        if (!addBase) return;
        window.location.href = addBase + productId;
    }

    // Wire data-action buttons (preferred) and keep legacy globals for inline onclick.
    document.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-wishlist-remove]');
        if (rm) {
            e.preventDefault();
            removeFromWishlist(rm.getAttribute('data-wishlist-remove'));
            return;
        }
        var ac = e.target.closest('[data-wishlist-add]');
        if (ac) {
            e.preventDefault();
            addToCart(ac.getAttribute('data-wishlist-add'));
        }
    });

    window.removeFromWishlist = removeFromWishlist;
    window.addToCart = addToCart;
})();
