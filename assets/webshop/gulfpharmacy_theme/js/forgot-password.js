(function () {
    'use strict';
    var m = document.getElementById('fp_mobile');
    if (m) {
        var dial = (m.getAttribute('data-phone-dial') || '').replace(/\D/g, '');
        var maxLen = parseInt(m.getAttribute('maxlength'), 10) || 15;
        var stripDialPrefix = function (v) {
            if (!dial || v.indexOf(dial) !== 0) return v;
            if (v.length > maxLen) return v.slice(dial.length);
            return v;
        };
        m.addEventListener('input', function () {
            var v = m.value.replace(/\D/g, '');
            v = stripDialPrefix(v);
            if (v.length > maxLen) v = v.slice(0, maxLen);
            m.value = v;
            m.classList.remove('fp-input-error');
        });
    }
    var o = document.getElementById('fp_otp');
    if (o) o.addEventListener('input', function () {
        o.value = o.value.replace(/\D/g, '').slice(0, 6);
        o.classList.remove('fp-input-error');
    });
    var p1 = document.getElementById('fp_password');
    var p2 = document.getElementById('fp_confirm_password');
    if (p1 && p2) {
        var check = function () {
            p1.classList.remove('fp-input-error');
            p2.classList.remove('fp-input-error');
            if (p2.value === '') { p2.setCustomValidity(''); return; }
            p2.setCustomValidity(p1.value === p2.value ? '' : 'Passwords do not match.');
        };
        p1.addEventListener('input', check);
        p2.addEventListener('input', check);
    }

    // Lock the submit button while a request is in flight so users
    // don't double-submit (which would burn OTP attempts).
    function lockSubmit(form, btn, busyLabel) {
        if (!form || !btn) return;
        form.addEventListener('submit', function (e) {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                if (typeof form.reportValidity === 'function') form.reportValidity();
                e.preventDefault();
                return;
            }
            btn.disabled = true;
            btn.dataset.originalText = btn.textContent;
            btn.innerHTML = '<span class="fp-btn-spinner"></span>' + busyLabel;
            // Safety: re-enable after 15s in case the browser stalls.
            setTimeout(function () {
                if (btn.disabled) {
                    btn.disabled = false;
                    btn.textContent = btn.dataset.originalText || busyLabel;
                }
            }, 15000);
        });
    }
    lockSubmit(document.getElementById('fp-step1'), document.getElementById('fp-send-otp-btn'), 'Sending OTP…');
    lockSubmit(document.getElementById('fp-reset-form'), document.getElementById('fp-reset-btn'), 'Resetting…');

    // After a server-side error: scroll the alert into view and focus the offending field.
    var errBanner = document.getElementById('fp-error');
    if (errBanner) {
        errBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var firstBad = document.querySelector('.fp-input-error');
        if (firstBad) { try { firstBad.focus({ preventScroll: true }); } catch (e) { firstBad.focus(); } }
    }
})();
