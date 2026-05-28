/**
 * Gulf Pharmacy checkout component — billing/shipping sync + coupon (apply/remove).
 * Posts to Webshop::webshop_request (action=apply_coupon). No jQuery required.
 */
(function () {
    'use strict';

    function fmtMoney(symbol, n) {
        var x = parseFloat(n);
        if (isNaN(x)) {
            x = 0;
        }
        return symbol + ' ' + x.toFixed(2);
    }

    function parseNum(v) {
        var x = parseFloat(v);
        return isNaN(x) ? 0 : x;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var sameCheckElement = document.getElementById('billtocopy');
        var shippingDiv = document.getElementById('usershipping');
        var sameAddressCheckInput = document.getElementById('billing_and_shipping_address_is_same');

        function toggleShippingInputs(enabled) {
            if (!shippingDiv) {
                return;
            }
            var shippingInputs = shippingDiv.querySelectorAll('input, select, textarea, button');
            for (var i = 0; i < shippingInputs.length; i++) {
                if (enabled) {
                    shippingInputs[i].removeAttribute('disabled');
                } else {
                    shippingInputs[i].setAttribute('disabled', 'disabled');
                }
            }
        }

        function syncSameAsBillingUi() {
            if (!sameCheckElement || !shippingDiv || !sameAddressCheckInput) {
                return;
            }
            if (sameCheckElement.checked) {
                shippingDiv.hidden = true;
                shippingDiv.style.display = 'none';
                toggleShippingInputs(false);
                sameAddressCheckInput.value = '1';
                return;
            }
            shippingDiv.hidden = false;
            shippingDiv.style.display = 'block';
            toggleShippingInputs(true);
            sameAddressCheckInput.value = '0';
        }

        if (sameCheckElement && shippingDiv && sameAddressCheckInput) {
            syncSameAsBillingUi();
            sameCheckElement.addEventListener('change', syncSameAsBillingUi);
        }

        var billingCountry = document.getElementById('billing_country');
        var shippingCountry = document.getElementById('shipping_country');
        if (billingCountry && shippingCountry) {
            shippingCountry.value = billingCountry.value;
            billingCountry.addEventListener('change', function () {
                shippingCountry.value = billingCountry.value;
            });
        }

        var form = document.getElementById('checkoutForm');
        if (!form) {
            return;
        }

        var checkoutTerms = document.getElementById('checkoutTerms');
        var termsError = document.getElementById('termsError');
        var termsGroup = document.getElementById('termsGroup');

        function syncCheckoutCsrfFields() {
            var c = window.GP_CSRF;
            if (!c || !c.name || !c.hash) {
                return;
            }
            var existing = form.querySelector('input[name="' + c.name + '"]');
            if (existing) {
                existing.value = c.hash;
            } else {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = c.name;
                inp.value = c.hash;
                form.appendChild(inp);
            }
        }

        function showCheckoutFlash(message) {
            if (!message) {
                return;
            }
            var host = document.getElementById('checkoutFlashHost');
            if (!host) {
                return;
            }
            var box = document.getElementById('checkoutFlashError');
            if (!box) {
                box = document.createElement('div');
                box.className = 'checkout-flash-error';
                box.id = 'checkoutFlashError';
                box.setAttribute('role', 'alert');
                host.appendChild(box);
            }
            box.textContent = message;
            box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        form.addEventListener('submit', function (e) {
            if (checkoutTerms && !checkoutTerms.checked) {
                e.preventDefault();
                if (termsError) {
                    termsError.removeAttribute('hidden');
                    termsError.textContent = 'Please agree to the terms and conditions to place your order.';
                }
                if (termsGroup) {
                    termsGroup.classList.add('terms-group--error');
                }
                showCheckoutFlash('Please agree to the terms and conditions before placing your order.');
                checkoutTerms.focus();
                return false;
            }
            if (termsError) {
                termsError.setAttribute('hidden', 'hidden');
                termsError.textContent = '';
            }
            if (termsGroup) {
                termsGroup.classList.remove('terms-group--error');
            }
            syncCheckoutCsrfFields();
            var placeBtn = document.getElementById('placeOrder');
            if (placeBtn) {
                placeBtn.disabled = true;
                placeBtn.textContent = 'Placing order…';
            }
        });

        if (checkoutTerms) {
            checkoutTerms.addEventListener('change', function () {
                if (!checkoutTerms.checked) {
                    return;
                }
                if (termsError) {
                    termsError.setAttribute('hidden', 'hidden');
                    termsError.textContent = '';
                }
                if (termsGroup) {
                    termsGroup.classList.remove('terms-group--error');
                }
            });
        }

        var actionUrl = form.getAttribute('data-action-url');
        if (!actionUrl) {
            actionUrl = '';
        }

        var couponInput = document.getElementById('couponInput');
        var couponApply = document.getElementById('couponApply');
        var couponMsg = document.getElementById('couponMsg');
        var couponApplied = document.getElementById('couponApplied');
        var couponRemove = document.getElementById('couponRemove');
        var couponTag = document.getElementById('couponTag');
        var couponSavedAmt = document.getElementById('couponSavedAmt');

        var summaryTotals = document.getElementById('summaryTotals');
        var cartSubtotalAmt = document.getElementById('cart_subtotal_amt');
        var cartTotalHidden = document.getElementById('cart_total');
        var shippingCharges = document.getElementById('shipping_charges');

        var hidCouponId = document.getElementById('coupon_code_id');
        var hidCouponVal = document.getElementById('coupon_code_value');
        var hidCouponCode = document.getElementById('coupon_code');
        var hidDiscRate = document.getElementById('coupon_discount_rate');
        var hidDiscAmt = document.getElementById('coupon_discount_amount');

        var sumSubtotal = document.getElementById('sumSubtotal');
        var sumShipping = document.getElementById('sumShipping');
        var sumDiscountRow = document.getElementById('sumDiscountRow');
        var sumDiscount = document.getElementById('sumDiscount');
        var sumTotal = document.getElementById('sumTotal');

        function getSymbol() {
            if (summaryTotals && summaryTotals.getAttribute('data-symbol')) {
                return summaryTotals.getAttribute('data-symbol');
            }
            return '$';
        }

        function getMerchSubtotal() {
            if (summaryTotals && summaryTotals.getAttribute('data-subtotal')) {
                return parseNum(summaryTotals.getAttribute('data-subtotal'));
            }
            if (cartSubtotalAmt && cartSubtotalAmt.value) {
                return parseNum(cartSubtotalAmt.value);
            }
            return 0;
        }

        function getShipping() {
            return shippingCharges ? parseNum(shippingCharges.value) : 0;
        }

        function setMsg(text, extraClass) {
            if (!couponMsg) {
                return;
            }
            couponMsg.textContent = text || '';
            couponMsg.className = 'coupon-msg' + (extraClass ? ' ' + extraClass : '');
        }

        function clearCouponHiddens() {
            if (hidCouponId) {
                hidCouponId.value = '';
            }
            if (hidCouponVal) {
                hidCouponVal.value = '';
            }
            if (hidCouponCode) {
                hidCouponCode.value = '';
            }
            if (hidDiscRate) {
                hidDiscRate.value = '';
            }
            if (hidDiscAmt) {
                hidDiscAmt.value = '';
            }
        }

        function resetTotalsToOriginal() {
            var sym = getSymbol();
            var merch = getMerchSubtotal();
            var ship = getShipping();
            if (sumSubtotal) {
                sumSubtotal.textContent = fmtMoney(sym, merch);
            }
            if (sumDiscountRow) {
                sumDiscountRow.setAttribute('hidden', 'hidden');
            }
            if (sumTotal) {
                sumTotal.textContent = fmtMoney(sym, merch + ship);
            }
            if (cartSubtotalAmt) {
                cartSubtotalAmt.value = merch.toFixed(4);
            }
            if (cartTotalHidden) {
                cartTotalHidden.value = (merch + ship).toFixed(4);
            }
        }

        function discountFromPayload(cd) {
            if (!cd) {
                return 0;
            }
            if (typeof cd.aplied_discount_amount !== 'undefined' && cd.aplied_discount_amount !== null && cd.aplied_discount_amount !== '') {
                return parseNum(cd.aplied_discount_amount);
            }
            if (typeof cd.applied_discount_amount !== 'undefined' && cd.applied_discount_amount !== null && cd.applied_discount_amount !== '') {
                return parseNum(cd.applied_discount_amount);
            }
            if (typeof cd.calculated_discount !== 'undefined' && cd.calculated_discount !== null && cd.calculated_discount !== '') {
                return parseNum(cd.calculated_discount);
            }
            return 0;
        }

        function applyCouponSuccess(code, cd) {
            var sym = getSymbol();
            var merch = getMerchSubtotal();
            var ship = getShipping();
            var disc = discountFromPayload(cd);
            if (disc < 0) {
                disc = 0;
            }
            if (disc > merch) {
                setMsg('Discount cannot exceed subtotal.', 'error-coupon-msg');
                return;
            }
            var newPay = merch - disc + ship;
            if (hidCouponId && typeof cd.id !== 'undefined') {
                hidCouponId.value = String(cd.id);
            }
            if (hidCouponVal) {
                hidCouponVal.value = code;
            }
            if (hidCouponCode) {
                hidCouponCode.value = code;
            }
            if (hidDiscRate) {
                hidDiscRate.value = (typeof cd.discount_rate !== 'undefined' && cd.discount_rate !== null) ? String(cd.discount_rate) : '';
            }
            if (hidDiscAmt) {
                hidDiscAmt.value = disc.toFixed(4);
            }
            // Keep cart_subtotal_amt as merchandise subtotal; only cart_total reflects payable amount.
            if (cartTotalHidden) {
                cartTotalHidden.value = newPay.toFixed(4);
            }
            if (sumDiscountRow && sumDiscount) {
                sumDiscount.textContent = '\u2212 ' + fmtMoney(sym, disc);
                sumDiscountRow.removeAttribute('hidden');
            }
            if (sumTotal) {
                sumTotal.textContent = fmtMoney(sym, newPay);
            }
            if (couponApplied) {
                couponApplied.removeAttribute('hidden');
            }
            if (couponTag) {
                couponTag.textContent = code.toUpperCase();
            }
            if (couponSavedAmt) {
                couponSavedAmt.textContent = fmtMoney(sym, disc);
            }
            if (couponInput) {
                couponInput.value = '';
            }
            setMsg('Coupon applied.', 'success-coupon-msg');
        }

        function postApplyCoupon(code, cartAmount, done) {
            if (!actionUrl) {
                done(0, '{"status":"failed","msg":"Missing request URL"}');
                return;
            }
            var body = 'action=apply_coupon&coupon_code=' + encodeURIComponent(code)
                + '&cart_amount=' + encodeURIComponent(String(cartAmount));
            if (typeof window.webshopAppendCsrfParams === 'function') {
                body = window.webshopAppendCsrfParams(body);
            }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', actionUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }
                var resp = xhr.responseText || '';
                if (xhr.status === 200 && typeof window.webshopUpdateCsrfFromJson === 'function') {
                    try {
                        window.webshopUpdateCsrfFromJson(JSON.parse(resp));
                    } catch (ignore) {}
                }
                done(xhr.status, resp);
            };
            xhr.send(body);
        }

        if (couponApply && couponInput) {
            couponApply.addEventListener('click', function () {
                var code = (couponInput.value || '').trim();
                if (!code) {
                    setMsg('Please enter a coupon code.', 'error-coupon-msg');
                    return;
                }
                if (hidCouponId && hidCouponId.value) {
                    setMsg('A coupon is already applied. Remove it first.', 'error-coupon-msg');
                    return;
                }
                var cartAmt = getMerchSubtotal();
                if (cartAmt <= 0) {
                    setMsg('Cart subtotal is empty.', 'error-coupon-msg');
                    return;
                }
                setMsg('Checking…', '');
                couponApply.disabled = true;
                postApplyCoupon(code, cartAmt, function (status, text) {
                    couponApply.disabled = false;
                    var obj = null;
                    try {
                        obj = JSON.parse(text);
                    } catch (e1) {
                        obj = null;
                    }
                    if (!obj) {
                        setMsg('Invalid response from server.', 'error-coupon-msg');
                        return;
                    }
                    if (obj.status === 'success' && obj.coupon_data) {
                        applyCouponSuccess(code, obj.coupon_data);
                        return;
                    }
                    var msg = (obj.msg || obj.error || 'Could not apply coupon.');
                    setMsg(String(msg), 'error-coupon-msg');
                });
            });
        }

        if (couponRemove) {
            couponRemove.addEventListener('click', function () {
                clearCouponHiddens();
                if (couponApplied) {
                    couponApplied.setAttribute('hidden', 'hidden');
                }
                resetTotalsToOriginal();
                setMsg('', '');
            });
        }

        if (hidCouponId && hidCouponId.value && couponApplied) {
            couponApplied.removeAttribute('hidden');
        }
    });
})();
