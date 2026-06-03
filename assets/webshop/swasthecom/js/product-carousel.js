// Shared carousel scroll helper + edge-state observer used by .gp-carousel
// (product_carousel.php and any similar CMS carousels).
(function () {
    'use strict';
    if (typeof window.gpc_scroll !== 'function') {
        window.gpc_scroll = function (id, dir) {
            var el = document.getElementById(id);
            if (el) el.scrollBy({ left: dir * (window.innerWidth < 600 ? 200 : 260), behavior: 'smooth' });
        };
    }

    function wireOne(car) {
        if (!car || car.__gp_carousel_wired) return;
        car.__gp_carousel_wired = true;
        var wrap = car.parentElement;
        if (!wrap) return;
        function updateEdges() {
            var atStart = car.scrollLeft <= 4;
            var atEnd = car.scrollLeft + car.clientWidth >= car.scrollWidth - 4;
            wrap.classList.toggle('is-at-start', atStart);
            wrap.classList.toggle('is-at-end', atEnd);
        }
        car.addEventListener('scroll', updateEdges, { passive: true });
        window.addEventListener('resize', updateEdges);
        setTimeout(updateEdges, 50);
    }

    function bindButtons(scope) {
        var root = scope || document;
        var buttons = root.querySelectorAll('.gp-carousel-btn[data-carousel-id][data-direction]');
        for (var i = 0; i < buttons.length; i++) {
            (function (btn) {
                if (btn.__gp_btn_wired) return;
                btn.__gp_btn_wired = true;
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-carousel-id');
                    var dir = parseInt(btn.getAttribute('data-direction'), 10);
                    if (!id || !dir) return;
                    window.gpc_scroll(id, dir);
                });
            })(buttons[i]);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var nodes = document.querySelectorAll('.gp-carousel');
        for (var i = 0; i < nodes.length; i++) wireOne(nodes[i]);
        bindButtons(document);
    });
})();
