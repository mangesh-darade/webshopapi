(function () {
    'use strict';
    var ctx = window.GP_PRODUCT_DETAILS_CTX || {};
    // Maintain legacy globals used by other scripts on the page (cart helpers, etc.)
    if (typeof ctx.base_url === 'string') window.baseUrl = ctx.base_url;
    if (typeof ctx.currency_symbol === 'string') window.currencySymbol = ctx.currency_symbol;

    function initProductDetailsUi() {
        if (!window.jQuery) return;
        var jQuery = window.jQuery;

        if (jQuery.fn && jQuery.fn.slick) {
            var $slider = jQuery('.slider-product');
            if ($slider.length && !$slider.hasClass('slick-initialized')) {
                $slider.slick({ autoplay: false, dots: false, speed: 500, slidesToShow: 1, adaptiveHeight: true, arrows: false });
            }
            $slider.on('beforeChange', function (event, slick, currentSlide, nextSlide) {
                jQuery('.slider-product-contain .thumbs a').removeClass('selected').eq(nextSlide).addClass('selected');
            });
            jQuery('.slider-product-contain .thumbs a').off('click').on('click', function () {
                jQuery('.slider-product-contain .thumbs a').removeClass('selected');
                jQuery(this).addClass('selected');
                jQuery('.slider-product').slick('slickGoTo', jQuery(this).index());
            });
        }
        if (jQuery.fn && jQuery.fn.responsiveTabs && jQuery('#product-tabs').length) {
            jQuery('#product-tabs').responsiveTabs({ startCollapsed: false, scrollToAccordion: false, setHash: false });
        }

        // Qty +/- buttons. Delegated, namespaced so we don't double-bind on re-init.
        jQuery(document).off('click.pqtyinc').on('click.pqtyinc', '.btn-increase', function () {
            var $input = jQuery(this).siblings('.itemQty');
            var qty = parseInt($input.val(), 10) || 1;
            $input.val(qty + 1);
        });
        jQuery(document).off('click.pqtydec').on('click.pqtydec', '.btn-decrease', function () {
            var $input = jQuery(this).siblings('.itemQty');
            var qty = parseInt($input.val(), 10) || 1;
            if (qty > 1) { $input.val(qty - 1); }
        });
    }

    function whenReady() {
        if (window.jQuery) {
            window.jQuery(document).ready(initProductDetailsUi);
        } else {
            // Polled fallback in case product-details.js loads before jquery.min.js finishes.
            var tries = 0;
            var iv = setInterval(function () {
                if (window.jQuery) { clearInterval(iv); window.jQuery(document).ready(initProductDetailsUi); }
                else if (++tries > 50) { clearInterval(iv); }
            }, 100);
        }
    }
    whenReady();
})();
