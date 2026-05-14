(function() {
    'use strict';
    
    // ── Drawer Management ────────────────────────────────────
    var backdrop = null;

    function getBackdrop() {
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'gp-drawer-backdrop';
            backdrop.hidden = true;
            document.body.appendChild(backdrop);
            backdrop.addEventListener('click', closeAllDrawers);
        }
        return backdrop;
    }

    function openDrawer(id) {
        var dr = document.getElementById(id);
        if (!dr) return;
        
        closeAllDrawers(); // Close others first
        
        dr.setAttribute('aria-hidden', 'false');
        getBackdrop().hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeAllDrawers() {
        var drawers = document.querySelectorAll('.gp-drawer');
        drawers.forEach(function(dr) {
            dr.setAttribute('aria-hidden', 'true');
        });
        if (backdrop) backdrop.hidden = true;
        document.body.style.overflow = '';
    }

    // ── Event Listeners ──────────────────────────────────────
    document.addEventListener('click', function(e) {
        // Toggle Sidebar (Hamburger)
        if (e.target.closest('#gp-hamburger')) {
            e.preventDefault();
            openDrawer('gp-sidebar-drawer');
        }
        
        // Toggle Minicart
        if (e.target.closest('.gp-cart-btn')) {
            // Only toggle on desktop or if it's meant to be a drawer
            // If the link is just a href to /cart, we might want to prevent default
            if (window.innerWidth > 0) { // Always for now as requested by "deep understand"
                e.preventDefault();
                openDrawer('gp-minicart-drawer');
                updateMinicart();
            }
        }
        
        // Close Buttons
        if (e.target.closest('.gp-drawer-close')) {
            closeAllDrawers();
        }
    });

    // ── Minicart Population ──────────────────────────────────
    function updateMinicart() {
        var body = document.querySelector('.gp-minicart-body');
        var foot = document.querySelector('.gp-minicart #gp-minicart-drawer .gp-drawer-foot');
        if (!body) return;

        body.innerHTML = '<div style="padding:40px;text-align:center;color:var(--gp-muted)">Loading cart...</div>';

        var ctx = window.GP_HEADER_CTX || {};
        var url = (ctx.webshop_url || '') + '/get_cart_json';

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renderMinicart(data);
            })
            .catch(function() {
                body.innerHTML = '<div style="padding:40px;text-align:center;color:#dc2626">Failed to load cart.</div>';
            });
    }

    function renderMinicart(data) {
        var body = document.querySelector('.gp-minicart-body');
        var foot = document.querySelector('#gp-minicart-drawer .gp-drawer-foot');
        if (!body) return;

        var items = data && data.items ? data.items : [];
        if (items.length === 0) {
            body.innerHTML = '<div style="padding:60px 20px;text-align:center;color:var(--gp-muted)">' +
                             '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:0.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>' +
                             '<p>Your cart is empty</p></div>';
            if (foot) foot.hidden = true;
            return;
        }

        var html = '';
        items.forEach(function(it) {
            html += '<div class="gp-cart-item">' +
                    '<img src="' + (it.image || '') + '" class="gp-cart-item-img" alt="">' +
                    '<div class="gp-cart-item-info">' +
                    '<a href="' + it.url + '" class="gp-cart-item-name">' + it.name + '</a>' +
                    '<div class="gp-cart-item-meta">Qty: ' + it.qty + '</div>' +
                    '<div class="gp-cart-item-price">' + it.price_formatted + '</div>' +
                    '</div>' +
                    '</div>';
        });
        body.innerHTML = html;

        if (foot) {
            foot.hidden = false;
            var subtotal = data.subtotal_formatted || '0.00';
            var webshop_url = window.GP_HEADER_CTX ? window.GP_HEADER_CTX.webshop_url : '';
            
            foot.innerHTML = '<div class="gp-cart-summary-row total">' +
                             '<span>Subtotal</span>' +
                             '<span>' + subtotal + '</span>' +
                             '</div>' +
                             '<div class="gp-cart-actions">' +
                             '<a href="' + webshop_url + '/cart" class="gp-btn gp-btn--outline">View Cart</a>' +
                             '<a href="' + webshop_url + '/checkout" class="gp-btn gp-btn--primary">Checkout</a>' +
                             '</div>';
        }
    }

})();
