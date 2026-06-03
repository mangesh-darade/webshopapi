// Reusable image fallback handler for card components.
(function () {
    'use strict';

    function showPlaceholder(img) {
        if (!img || !img.parentNode) return;
        var fallbackSrc = img.getAttribute('data-fallback-src');
        if (fallbackSrc) {
            img.src = fallbackSrc;
            img.removeAttribute('data-fallback-src');
            return;
        }
        var selector = img.getAttribute('data-fallback-target');
        if (!selector) return;
        var placeholder = img.parentNode.querySelector(selector);
        if (!placeholder) return;
        img.style.display = 'none';
        placeholder.style.display = 'flex';
    }

    function bindImageFallback(root) {
        var scope = root || document;
        var images = scope.querySelectorAll('img[data-fallback-target]');
        for (var i = 0; i < images.length; i++) {
            (function (img) {
                if (img.__gp_fallback_wired) return;
                img.__gp_fallback_wired = true;
                img.addEventListener('error', function () { showPlaceholder(img); });
            })(images[i]);
        }
    }

    document.addEventListener('DOMContentLoaded', function () { bindImageFallback(document); });
    window.gpBindImageFallback = bindImageFallback;
})();
