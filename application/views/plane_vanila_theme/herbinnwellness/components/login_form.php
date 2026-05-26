<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php
/**
 * Reusable Premium Login Component
 */
$Settings = isset($Settings) ? $Settings : (object) array('site_name' => 'Webshop');
$CI =& get_instance();
$flash_msg = $CI->session->flashdata('message');
$flash_err = $CI->session->flashdata('error');
$toast_err = $CI->session->flashdata('toast_error');
?>

<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/login-form.css') ?>">
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <h2>Welcome Back</h2>
            <p>Please enter your details to sign in</p>
        </div>
        <?php if ($flash_msg): ?>
            <div class="login-alert login-alert-success"><?= html_escape($flash_msg) ?></div>
        <?php endif; ?>
        <?php if ($flash_err || $toast_err): ?>
            <div class="login-alert login-alert-error"><?= html_escape($flash_err ? $flash_err : $toast_err) ?></div>
        <?php endif; ?>

        <?php
        $_login_return = '';
        if (isset($return_page) && trim((string) $return_page) !== '') {
            $_login_return = trim((string) $return_page);
        } elseif (isset($_GET['return_page']) && trim((string) $_GET['return_page']) !== '') {
            $_login_return = trim((string) $_GET['return_page']);
        }
        ?>
        <form action="<?= base_url('webshop/login') ?>" method="post" class="login-form">
            <?= function_exists('webshop_csrf_hidden_input') ? webshop_csrf_hidden_input() : '' ?>
            <?php if ($_login_return !== ''): ?>
            <input type="hidden" name="return_page" value="<?= htmlspecialchars($_login_return, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="identity">Email or Phone</label>
                <div class="input-wrapper">
                    <span class="input-icon">📧</span>
                    <input type="text" name="identity" id="identity" required autocomplete="username" placeholder="email@example.com or 10-digit mobile">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" value="1">
                    <span class="checkmark"></span>
                    Remember me
                </label>
                <a href="<?= base_url('webshop/forgot_password') ?>" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" name="submit" class="login-submit-btn">Sign In</button>
        </form>

        <div class="login-footer">
            <p>Don't have an account? <a href="<?= base_url('webshop/register') ?>">Create one here</a></p>
        </div>
    </div>
</div>
