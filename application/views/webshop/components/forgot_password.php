<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme    = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
            ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';
$shopName = isset($Settings->site_name) ? $Settings->site_name : 'Webshop';
$flash_msg = $this->session->flashdata('message');
$flash_err = $this->session->flashdata('error');
$forgot_mobile = $this->session->flashdata('forgot_mobile');
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
        .fp-input{width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:9px;font-size:15px;outline:none;box-sizing:border-box;transition:border-color .2s}
        .fp-input:focus{border-color:#0F4C81}
        .fp-btn{width:100%;background:#0F4C81;color:#fff;border:none;border-radius:10px;padding:13px;font-size:16px;font-weight:700;cursor:pointer;margin-top:6px;transition:background .2s}
        .fp-btn:hover{background:#0c3d69}
        .fp-back{display:block;text-align:center;margin-top:18px;font-size:14px;color:#0F4C81;text-decoration:none}
        .fp-back:hover{text-decoration:underline}
        .fp-alert{padding:11px 14px;border-radius:9px;margin-bottom:18px;font-size:14px}
        .fp-alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
        .fp-alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
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
                <div class="fp-alert fp-alert-success"><?= html_escape($flash_msg) ?></div>
            <?php endif; ?>
            <?php if ($flash_err): ?>
                <div class="fp-alert fp-alert-error"><?= html_escape($flash_err) ?></div>
            <?php endif; ?>

            <form action="<?= base_url('webshop/forgot_password') ?>" method="post">
                <div class="fp-group">
                    <label class="fp-label" for="fp_mobile">Mobile Number</label>
                    <input class="fp-input" type="tel" id="fp_mobile" name="mobile"
                           placeholder="Enter registered mobile number" required
                           value="<?= html_escape($forgot_mobile ? $forgot_mobile : '') ?>">
                </div>
                <div class="fp-group">
                    <button type="submit" name="send_otp" value="1" class="fp-btn" style="margin-top:0;">
                        Send OTP
                    </button>
                </div>
                <div class="fp-group">
                    <label class="fp-label" for="fp_otp">OTP</label>
                    <input class="fp-input" type="text" id="fp_otp" name="otp"
                           placeholder="Enter 6-digit OTP" maxlength="6" required>
                </div>
                <div class="fp-group">
                    <label class="fp-label" for="fp_password">New Password</label>
                    <input class="fp-input" type="password" id="fp_password" name="new_password"
                           placeholder="Enter new password" required minlength="6">
                </div>
                <div class="fp-group">
                    <label class="fp-label" for="fp_confirm_password">Confirm Password</label>
                    <input class="fp-input" type="password" id="fp_confirm_password" name="confirm_password"
                           placeholder="Enter new password" required minlength="6">
                </div>
                <button type="submit" name="reset_password" value="1" class="fp-btn">
                    Verify OTP and Reset Password
                </button>
            </form>

            <a href="<?= base_url('webshop/login') ?>" class="fp-back">← Back to Sign In</a>
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
