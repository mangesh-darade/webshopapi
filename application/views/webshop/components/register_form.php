<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Reusable Premium Register Form Component
 * Field names must match Webshop::register() controller expectations:
 *   first, last, email, phone, passwd, submit_register
 */
$CI =& get_instance();
$toast_error  = $CI->session->flashdata('toast_error');
$toast_success = $CI->session->flashdata('toast_success');
$error_msg    = $CI->session->flashdata('error_msg');
$reg_first    = $CI->session->flashdata('reg_first');
$reg_last     = $CI->session->flashdata('reg_last');
$reg_email    = $CI->session->flashdata('reg_email');
$reg_phone    = $CI->session->flashdata('reg_phone');
?>

<div class="register-container">
    <div class="register-card">
        <div class="register-header">
            <h2>Create Account</h2>
            <p>Join us for a better shopping experience</p>
        </div>

        <?php if ($toast_error || $error_msg): ?>
        <div class="reg-alert reg-alert-error">
            <?= html_escape($toast_error ?: $error_msg) ?>
        </div>
        <?php endif; ?>

        <?php if ($toast_success): ?>
        <div class="reg-alert reg-alert-success">
            <?= html_escape($toast_success) ?>
        </div>
        <?php endif; ?>

        <form action="<?= base_url('webshop/register') ?>" method="post" class="register-form" id="registerForm" novalidate>
            <input type="hidden" name="submit_register" value="1">

            <div class="form-row">
                <div class="form-group">
                    <label for="first">First Name <span class="req">*</span></label>
                    <input type="text" name="first" id="first" required
                           placeholder="John"
                           value="<?= html_escape($reg_first ?: '') ?>">
                    <span class="field-error" id="err-first"></span>
                </div>
                <div class="form-group">
                    <label for="last">Last Name <span class="req">*</span></label>
                    <input type="text" name="last" id="last" required
                           placeholder="Doe"
                           value="<?= html_escape($reg_last ?: '') ?>">
                    <span class="field-error" id="err-last"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="req">*</span></label>
                <input type="email" name="email" id="email" required
                       placeholder="john@example.com"
                       value="<?= html_escape($reg_email ?: '') ?>">
                <span class="field-error" id="err-email"></span>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number <span class="req">*</span></label>
                <input type="tel" name="phone" id="phone" required
                       placeholder="9876543210"
                       value="<?= html_escape($reg_phone ?: '') ?>">
                <span class="field-error" id="err-phone"></span>
            </div>

            <div class="form-group">
                <label for="passwd">Password <span class="req">*</span></label>
                <div class="pw-wrapper">
                    <input type="password" name="passwd" id="passwd" required
                           placeholder="Min 6 characters" minlength="6" autocomplete="new-password">
                    <button type="button" class="pw-toggle" data-target="passwd" title="Show/hide">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <span class="field-error" id="err-passwd"></span>
            </div>

            <div class="form-group">
                <label for="passwd_confirm">Confirm Password <span class="req">*</span></label>
                <div class="pw-wrapper">
                    <input type="password" name="passwd_confirm" id="passwd_confirm" required
                           placeholder="Re-enter password" autocomplete="new-password">
                    <button type="button" class="pw-toggle" data-target="passwd_confirm" title="Show/hide">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <span class="field-error" id="err-confirm"></span>
            </div>

            <div class="form-group terms-group">
                <label class="checkbox-container">
                    <input type="checkbox" name="terms" id="terms" required>
                    <span class="checkmark"></span>
                    I agree to the <a href="#" target="_blank">Terms &amp; Conditions</a>
                </label>
                <span class="field-error" id="err-terms"></span>
            </div>

            <button type="submit" class="register-submit-btn" id="regSubmitBtn">
                <span class="btn-text">Create Account</span>
                <span class="btn-spinner" style="display:none">Creating&hellip;</span>
            </button>
        </form>

        <div class="register-footer">
            <p>Already have an account? <a href="<?= base_url('webshop/login') ?>">Sign In</a></p>
        </div>
    </div>
</div>

<style>
    .register-container {
        font-family: 'Outfit', sans-serif;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 70vh;
        padding: 40px 20px 60px;
        background: #f8fafc;
    }

    .register-card {
        background: #fff;
        width: 100%;
        max-width: 550px;
        padding: 40px;
        border-radius: 24px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }

    .register-header {
        text-align: center;
        margin-bottom: 28px;
    }

    .register-header h2 {
        font-size: 1.8rem;
        margin-bottom: 6px;
        color: #1a1a1a;
    }

    .register-header p {
        color: #718096;
        font-size: 0.95rem;
    }

    .reg-alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .reg-alert-error {
        background: #fff5f5;
        border: 1px solid #fed7d7;
        color: #c53030;
    }

    .reg-alert-success {
        background: #f0fff4;
        border: 1px solid #c6f6d5;
        color: #276749;
    }

    .form-row {
        display: flex;
        gap: 16px;
    }

    @media (max-width: 576px) {
        .form-row { flex-direction: column; gap: 0; }
    }

    .form-group {
        margin-bottom: 20px;
        flex: 1;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
        font-size: 0.88rem;
        color: #4a5568;
    }

    .req { color: #e53e3e; }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="tel"],
    .form-group input[type="password"] {
        width: 100%;
        padding: 12px 16px;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.95rem;
        transition: all 0.2s;
        background: #fdfdfd;
        box-sizing: border-box;
    }

    .form-group input:focus {
        outline: none;
        border-color: #fa8507;
        box-shadow: 0 0 0 3px rgba(250, 133, 7, 0.12);
        background: #fff;
    }

    .form-group input.is-invalid {
        border-color: #e53e3e;
    }

    .field-error {
        display: block;
        font-size: 0.8rem;
        color: #e53e3e;
        margin-top: 4px;
        min-height: 1em;
    }

    .pw-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .pw-wrapper input {
        flex: 1;
        padding-right: 44px !important;
    }

    .pw-toggle {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        cursor: pointer;
        color: #a0aec0;
        padding: 4px;
        line-height: 0;
    }

    .pw-toggle:hover { color: #4a5568; }

    .terms-group {
        margin: 4px 0 24px 0;
    }

    .checkbox-container {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
        font-size: 0.88rem;
        color: #4a5568;
        line-height: 1.4;
    }

    .checkbox-container input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-top: 2px;
        accent-color: #fa8507;
        flex-shrink: 0;
    }

    .checkbox-container a { color: #fa8507; font-weight: 600; text-decoration: none; }

    .register-submit-btn {
        width: 100%;
        background: #fa8507;
        color: #fff;
        border: none;
        padding: 15px;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        letter-spacing: 0.3px;
    }

    .register-submit-btn:hover {
        background: #e67700;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(250, 133, 7, 0.25);
    }

    .register-submit-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .register-footer {
        margin-top: 28px;
        text-align: center;
        font-size: 0.9rem;
        color: #718096;
        padding-top: 20px;
        border-top: 1px solid #f1f5f9;
    }

    .register-footer a {
        color: #fa8507;
        text-decoration: none;
        font-weight: 700;
    }
</style>

<script>
(function () {
    // password show/hide toggles
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(btn.dataset.target);
            if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
        });
    });

    // client-side validation before submit
    var form = document.getElementById('registerForm');
    if (!form) return;

    function setErr(id, msg) {
        var el = document.getElementById('err-' + id);
        var inp = document.getElementById(id === 'confirm' ? 'passwd_confirm' : (id === 'terms' ? 'terms' : id));
        if (el) el.textContent = msg;
        if (inp) inp.classList.toggle('is-invalid', !!msg);
    }
    function clearErrors() {
        ['first','last','email','phone','passwd','confirm','terms'].forEach(function(k){ setErr(k,''); });
    }

    form.addEventListener('submit', function (e) {
        clearErrors();
        var ok = true;

        var first = document.getElementById('first').value.trim();
        var last  = document.getElementById('last').value.trim();
        var email = document.getElementById('email').value.trim();
        var phone = document.getElementById('phone').value.trim();
        var passwd = document.getElementById('passwd').value;
        var confirm = document.getElementById('passwd_confirm').value;
        var terms = document.getElementById('terms').checked;

        if (!first)  { setErr('first', 'First name is required'); ok = false; }
        if (!last)   { setErr('last',  'Last name is required');  ok = false; }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setErr('email', 'Valid email is required'); ok = false;
        }
        if (!phone || phone.replace(/\D/g,'').length < 7) {
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
        btn.disabled = true;
        btn.querySelector('.btn-text').style.display = 'none';
        btn.querySelector('.btn-spinner').style.display = 'inline';
    });
})();
</script>
