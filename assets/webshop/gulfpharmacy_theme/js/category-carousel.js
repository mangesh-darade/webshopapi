// Category carousel scroll + edge-state observer.
(function () {
    'use strict';

    function scrollCarousel(id, dir) {
        var el = document.getElementById(id);
        if (!el) return;
        el.scrollBy({ left: dir * (window.innerWidth < 600 ? 234 : 266), behavior: 'smooth' });
    }

    function wireOne(car) {
        if (!car || car.__gp_cc_wired) return;
        car.__gp_cc_wired = true;
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
        var buttons = root.querySelectorAll('.gp-cc-btn[data-carousel-id][data-direction]');
        for (var i = 0; i < buttons.length; i++) {
            (function (btn) {
                if (btn.__gp_cc_btn_wired) return;
                btn.__gp_cc_btn_wired = true;
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-carousel-id');
                    var dir = parseInt(btn.getAttribute('data-direction'), 10);
                    if (!id || !dir) return;
                    scrollCarousel(id, dir);
                });
            })(buttons[i]);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var nodes = document.querySelectorAll('.gp-cc-carousel');
        for (var i = 0; i < nodes.length; i++) wireOne(nodes[i]);
        bindButtons(document);
    });
})();
