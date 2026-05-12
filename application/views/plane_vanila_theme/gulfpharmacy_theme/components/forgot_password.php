<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme    = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
            ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';
$shopName = isset($Settings->site_name) ? $Settings->site_name : 'Webshop';
$flash_msg = $this->session->flashdata('message');
$flash_err = $this->session->flashdata('error');
$forgot_mobile = $this->session->flashdata('forgot_mobile');
$otp_sent = (bool) $this->session->flashdata('otp_sent');
$error_field = (string) $this->session->flashdata('error_field');
$fp_field_err = function ($field) use ($error_field) {
    return $error_field === $field ? ' fp-input-error' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | <?= html_escape($shopName) ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <style>
        .fp-main{min-height:70vh;display:flex;align-items:center;justify-content:center;padding:40px 16px}
        .fp-card{background:#fff;border-radius:18px;padding:40px 36px;box-shadow:0 8px 28px rgba(0,0,0,.08);width:100%;max-width:440px}
        .fp-title{font-size:24px;font-weight:800;color:#1f2937;margin:0 0 6px}
        .fp-sub{color:#6b7280;font-size:14px;margin:0 0 28px}
        .fp-group{margin-bottom:18px}
        .fp-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
        .fp-input{width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:9px;font-size:15px;outline:none;box-sizing:border-box;transition:border-color .2s,box-shadow .2s}
        .fp-input:focus{border-color:#0F4C81;box-shadow:0 0 0 3px rgba(15,76,129,.12)}
        .fp-input-error{border-color:#dc2626;background:#fef2f2}
        .fp-input-error:focus{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.15)}
        .fp-input:invalid:not(:placeholder-shown){border-color:#dc2626}
        .fp-help{font-size:12px;color:#6b7280;margin-top:4px;display:block}
        .fp-btn{width:100%;background:#0F4C81;color:#fff;border:none;border-radius:10px;padding:13px;font-size:16px;font-weight:700;cursor:pointer;margin-top:6px;transition:background .2s,opacity .2s;min-height:44px}
        .fp-btn:hover:not(:disabled){background:#0c3d69}
        .fp-btn:disabled{opacity:.7;cursor:wait}
        .fp-btn-spinner{display:inline-block;width:14px;height:14px;margin-right:8px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:fp-spin .8s linear infinite;vertical-align:-2px}
        @keyframes fp-spin{to{transform:rotate(360deg)}}
        .fp-back{display:block;text-align:center;margin-top:18px;font-size:14px;color:#0F4C81;text-decoration:none}
        .fp-back:hover{text-decoration:underline}
        .fp-alert{padding:11px 14px;border-radius:9px;margin-bottom:18px;font-size:14px;display:flex;gap:10px;align-items:flex-start;line-height:1.45}
        .fp-alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
        .fp-alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .fp-alert-icon{flex-shrink:0;width:18px;height:18px;margin-top:1px}
        @media(max-width:500px){.fp-card{padding:26px 18px}}
    </style>
</head>
<body>
<div class="gp-site-wrapper">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
    } elseif (is_file(VIEWPATH . 'webshop/header.php')) {
        require_once(VIEWPATH . 'webshop/header.php');
    }
    ?>

    <main class="fp-main">
        <div class="fp-card">
            <h1 class="fp-title">Reset Password</h1>
            <p class="fp-sub">Request OTP on your registered mobile number, then reset your password securely.</p>

            <?php if ($flash_msg): ?>
                <div class="fp-alert fp-alert-success" role="status">
                    <svg class="fp-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    <span><?= html_escape($flash_msg) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flash_err): ?>
                <div class="fp-alert fp-alert-error" role="alert" id="fp-error">
                    <svg class="fp-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><circle cx="12" cy="16" r=".5" fill="currentColor"/></svg>
                    <span><?= html_escape($flash_err) ?></span>
                </div>
            <?php endif; ?>

            <?php // Step 1: request the OTP. Standalone form so browser-required fields from step 2 cannot block this submit. ?>
            <form action="<?= base_url('webshop/forgot_password') ?>" method="post" id="fp-step1" autocomplete="off" novalidate>
                <div class="fp-group">
                    <label class="fp-label" for="fp_mobile">Mobile Number</label>
                    <input class="fp-input<?= $fp_field_err('mobile') ?>" type="tel" id="fp_mobile" name="mobile"
                           placeholder="Enter registered mobile number"
                           inputmode="numeric" pattern="[0-9+\s\-()]{10,18}"
                           maxlength="18" required
                           aria-describedby="fp_mobile_help"
                           value="<?= html_escape($forgot_mobile ? $forgot_mobile : '') ?>">
                    <small id="fp_mobile_help" class="fp-help">Enter 10-15 digits. Country code with leading + is allowed.</small>
                </div>
                <button type="submit" name="send_otp" value="1" class="fp-btn" id="fp-send-otp-btn" style="margin-top:0;">
                    <?= $otp_sent ? 'Resend OTP' : 'Send OTP' ?>
                </button>
            </form>

            <?php // Step 2: verify OTP and reset password. Visible only after an OTP has been delivered. ?>
            <div id="fp-step2" <?= $otp_sent ? '' : 'hidden' ?> style="margin-top:18px">
                <form action="<?= base_url('webshop/forgot_password') ?>" method="post" id="fp-reset-form" autocomplete="off" novalidate>
                    <input type="hidden" name="mobile" value="<?= html_escape($forgot_mobile ? $forgot_mobile : '') ?>">
                    <div class="fp-group">
                        <label class="fp-label" for="fp_otp">OTP</label>
                        <input class="fp-input<?= $fp_field_err('otp') ?>" type="text" id="fp_otp" name="otp"
                               placeholder="Enter 6-digit OTP"
                               inputmode="numeric" pattern="\d{6}" maxlength="6"
                               autocomplete="one-time-code" required>
                        <small class="fp-help">Sent via WhatsApp / SMS / Email. Valid for 10 minutes.</small>
                    </div>
                    <div class="fp-group">
                        <label class="fp-label" for="fp_password">New Password</label>
                        <input class="fp-input<?= $fp_field_err('new_password') ?>" type="password" id="fp_password" name="new_password"
                               placeholder="Enter new password (min 6 characters)" required minlength="6">
                    </div>
                    <div class="fp-group">
                        <label class="fp-label" for="fp_confirm_password">Confirm Password</label>
                        <input class="fp-input<?= $fp_field_err('confirm_password') ?>" type="password" id="fp_confirm_password" name="confirm_password"
                               placeholder="Re-enter new password" required minlength="6">
                    </div>
                    <button type="submit" name="reset_password" value="1" class="fp-btn" id="fp-reset-btn">
                        Verify OTP and Reset Password
                    </button>
                </form>
            </div>

            <a href="<?= base_url('webshop/login') ?>" class="fp-back">← Back to Sign In</a>

            <script>
            (function(){
                var m = document.getElementById('fp_mobile');
                if (m) m.addEventListener('input', function(){
                    var v = m.value.replace(/[^0-9+]/g, '');
                    if (v.indexOf('+') > 0) v = v.replace(/\+/g, '');
                    m.value = v;
                    m.classList.remove('fp-input-error');
                });
                var o = document.getElementById('fp_otp');
                if (o) o.addEventListener('input', function(){
                    o.value = o.value.replace(/\D/g, '').slice(0, 6);
                    o.classList.remove('fp-input-error');
                });
                var p1 = document.getElementById('fp_password');
                var p2 = document.getElementById('fp_confirm_password');
                if (p1 && p2) {
                    var check = function(){
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
                    form.addEventListener('submit', function(e){
                        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                            // Browser will show the native invalid hint; let it.
                            form.reportValidity && form.reportValidity();
                            e.preventDefault();
                            return;
                        }
                        btn.disabled = true;
                        btn.dataset.originalText = btn.textContent;
                        btn.innerHTML = '<span class="fp-btn-spinner"></span>' + busyLabel;
                        // Safety: re-enable after 15s in case the browser stalls.
                        setTimeout(function(){
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
            </script>
        </div>
    </main>

    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php');
    } elseif (is_file(VIEWPATH . 'webshop/footer.php')) {
        require_once(VIEWPATH . 'webshop/footer.php');
    }
    ?>
</div>
</body>
</html>
