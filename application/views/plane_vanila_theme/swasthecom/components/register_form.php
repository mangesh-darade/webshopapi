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

<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/register-form.css') ?>">
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
            <?= function_exists('webshop_csrf_hidden_input') ? webshop_csrf_hidden_input() : '' ?>

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
<script defer src="<?= webshop_theme_assets_url('js/register-form.js') ?>"></script>
