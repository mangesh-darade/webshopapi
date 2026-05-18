<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$shopName = isset($Settings->site_name) ? $Settings->site_name : 'Webshop';
$flash_msg = $this->session->flashdata('message');
$flash_err = $this->session->flashdata('error');
$forgot_mobile = $this->session->flashdata('forgot_mobile');
$otp_sent = (bool) $this->session->flashdata('otp_sent');
$error_field = (string) $this->session->flashdata('error_field');
$phone_code = isset($phone_code) ? preg_replace('/\D/', '', (string) $phone_code) : '';
if ($phone_code === '' && function_exists('webshop_settings_phone_dial_code')) {
    $phone_code = webshop_settings_phone_dial_code();
}
if ($phone_code === '') {
    $phone_code = '91';
}
$phone_local_digits = isset($phone_local_digits) ? (int) $phone_local_digits : 10;
if ($forgot_mobile && function_exists('webshop_phone_digit_variants')) {
    $fp_variants = webshop_phone_digit_variants($forgot_mobile, $phone_code, $phone_local_digits);
    if (!empty($fp_variants)) {
        $forgot_mobile = $fp_variants[0];
    }
}
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
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/common.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/header.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/forgot-password.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="gp-site-wrapper">
    <?php
    if (function_exists('webshop_plane_vanila_view_file') && is_file(webshop_plane_vanila_view_file('header'))) {
        require_once webshop_plane_vanila_view_file('header');
    } elseif (is_file(VIEWPATH . 'webshop/header.php')) {
        require_once VIEWPATH . 'webshop/header.php';
    }
    ?>

    <main class="fp-main">
        <div class="fp-card">
            <h1 class="fp-title">Reset Password</h1>
            <p class="fp-sub">Request a one-time code on your registered mobile. We send it via WhatsApp and email (SMS may also be used if configured in ElintOm).</p>

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

            <form action="<?= base_url('webshop/forgot_password') ?>" method="post" id="fp-step1" autocomplete="off" novalidate>
                <input type="hidden" name="send_otp" value="1">
                <div class="fp-group">
                    <label class="fp-label" for="fp_mobile">Mobile Number</label>
                    <div class="fp-input-row">
                        <span class="fp-input-prefix" id="fp_phone_prefix">+<?= html_escape($phone_code) ?></span>
                        <input class="fp-input fp-input--prefixed<?= $fp_field_err('mobile') ?>" type="tel" id="fp_mobile" name="mobile"
                               placeholder="<?= (int) $phone_code === 91 ? '10-digit mobile (without +91)' : 'Registered mobile number' ?>"
                               inputmode="numeric" pattern="[0-9]{<?= max(8, $phone_local_digits) ?>}"
                               maxlength="<?= max(8, $phone_local_digits) ?>" required
                               data-phone-dial="<?= html_escape($phone_code) ?>"
                               aria-describedby="fp_mobile_help fp_phone_prefix"
                               value="<?= html_escape($forgot_mobile ? $forgot_mobile : '') ?>">
                    </div>
                    <small id="fp_mobile_help" class="fp-help">Enter your mobile without the country code (+<?= html_escape($phone_code) ?> is already selected). OTP is sent via WhatsApp and email — valid 10 minutes.</small>
                </div>
                <button type="submit" name="send_otp" value="1" class="fp-btn fp-btn-step1" id="fp-send-otp-btn">
                    <?= $otp_sent ? 'Resend OTP' : 'Send OTP' ?>
                </button>
            </form>

            <div id="fp-step2" class="fp-step2" <?= $otp_sent ? '' : 'hidden' ?>>
                <form action="<?= base_url('webshop/forgot_password') ?>" method="post" id="fp-reset-form" autocomplete="off" novalidate>
                    <input type="hidden" name="reset_password" value="1">
                    <input type="hidden" name="mobile" value="<?= html_escape($forgot_mobile ? $forgot_mobile : '') ?>">
                    <div class="fp-group">
                        <label class="fp-label" for="fp_otp">OTP</label>
                        <input class="fp-input<?= $fp_field_err('otp') ?>" type="text" id="fp_otp" name="otp"
                               placeholder="6-digit code"
                               inputmode="numeric" pattern="\d{6}" maxlength="6"
                               autocomplete="one-time-code" required>
                    </div>
                    <div class="fp-group">
                        <label class="fp-label" for="fp_password">New Password</label>
                        <input class="fp-input<?= $fp_field_err('new_password') ?>" type="password" id="fp_password" name="new_password"
                               placeholder="Minimum 6 characters" required minlength="6">
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
        </div>
    </main>

    <?php
    if (function_exists('webshop_plane_vanila_view_file') && is_file(webshop_plane_vanila_view_file('footer'))) {
        require_once webshop_plane_vanila_view_file('footer');
    } elseif (is_file(VIEWPATH . 'webshop/footer.php')) {
        require_once VIEWPATH . 'webshop/footer.php';
    }
    ?>
</div>
<script defer src="<?= htmlspecialchars(webshop_theme_assets_url('js/forgot-password.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
