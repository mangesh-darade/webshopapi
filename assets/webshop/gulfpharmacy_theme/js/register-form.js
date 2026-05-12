(function () {
    'use strict';
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(btn.dataset.target);
            if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
        });
    });

    var form = document.getElementById('registerForm');
    if (!form) return;

    function setErr(id, msg) {
        var el = document.getElementById('err-' + id);
        var inp = document.getElementById(id === 'confirm' ? 'passwd_confirm' : (id === 'terms' ? 'terms' : id));
        if (el) el.textContent = msg;
        if (inp) inp.classList.toggle('is-invalid', !!msg);
    }
    function clearErrors() {
        ['first', 'last', 'email', 'phone', 'passwd', 'confirm', 'terms'].forEach(function (k) { setErr(k, ''); });
    }

    form.addEventListener('submit', function (e) {
        clearErrors();
        var ok = true;

        var first = document.getElementById('first').value.trim();
        var last = document.getElementById('last').value.trim();
        var email = document.getElementById('email').value.trim();
        var phone = document.getElementById('phone').value.trim();
        var passwd = document.getElementById('passwd').value;
        var confirm = document.getElementById('passwd_confirm').value;
        var terms = document.getElementById('terms').checked;

        if (!first) { setErr('first', 'First name is required'); ok = false; }
        if (!last) { setErr('last', 'Last name is required'); ok = false; }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setErr('email', 'Valid email is required'); ok = false;
        }
        if (!phone || phone.replace(/\D/g, '').length < 7) {
            setErr('phone', 'Valid phone number is required'); ok = false;
        }
        if (!passwd || passwd.length < 6) {
            setErr('passwd', 'Password must be at least 6 characters'); ok = false;
        }
        if (passwd !== confirm) {
            setErr('confirm', 'Passwords do not match'); ok = false;
        }
        if (!terms) {
            setErr('terms', 'You must accept the terms'); ok = false;
        }

        if (!ok) { e.preventDefault(); return; }

        var btn = document.getElementById('regSubmitBtn');
        if (!btn) return;
        btn.disabled = true;
        var btnText = btn.querySelector('.btn-text');
        var btnSpin = btn.querySelector('.btn-spinner');
        if (btnText) btnText.style.display = 'none';
        if (btnSpin) btnSpin.style.display = 'inline';
    });
})();
