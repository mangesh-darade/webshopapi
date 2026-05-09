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

        <form action="<?= base_url('webshop/login') ?>" method="post" class="login-form">
            <div class="form-group">
                <label for="identity">Email or Phone</label>
                <div class="input-wrapper">
                    <span class="input-icon">📧</span>
                    <input type="text" name="identity" id="identity" required placeholder="email@example.com">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" id="password" required placeholder="••••••••">
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

<style>
    .login-container {
        font-family: 'Outfit', sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 60vh;
        padding: 40px 20px;
        background: #f8fafc;
    }

    .login-card {
        background: #fff;
        width: 100%;
        max-width: 450px;
        padding: 40px;
        border-radius: 24px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }

    .login-header {
        text-align: center;
        margin-bottom: 32px;
    }

    .login-header h2 {
        font-size: 1.8rem;
        margin-bottom: 8px;
        color: #1a1a1a;
    }

    .login-header p {
        color: #718096;
        font-size: 0.95rem;
    }
    .login-alert {
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 16px;
        font-size: 0.9rem;
    }
    .login-alert-success {
        background: #f0fff4;
        border: 1px solid #c6f6d5;
        color: #276749;
    }
    .login-alert-error {
        background: #fff5f5;
        border: 1px solid #fed7d7;
        color: #c53030;
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.9rem;
        color: #4a5568;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon {
        position: absolute;
        left: 16px;
        font-size: 1.1rem;
    }

    .input-wrapper input {
        width: 100%;
        padding: 14px 16px 14px 48px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.2s;
        background: #fdfdfd;
    }

    .input-wrapper input:focus {
        outline: none;
        border-color: #fa8507;
        box-shadow: 0 0 0 4px rgba(250, 133, 7, 0.1);
        background: #fff;
    }

    .form-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
        font-size: 0.9rem;
    }

    .remember-me {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        color: #4a5568;
    }

    .forgot-password {
        color: #fa8507;
        text-decoration: none;
        font-weight: 600;
    }

    .login-submit-btn {
        width: 100%;
        background: #fa8507;
        color: #fff;
        border: none;
        padding: 16px;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
    }

    .login-submit-btn:hover {
        background: #e67700;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(250, 133, 7, 0.2);
    }

    .login-submit-btn:active {
        transform: translateY(0);
    }

    .login-footer {
        margin-top: 32px;
        text-align: center;
        font-size: 0.9rem;
        color: #718096;
        padding-top: 24px;
        border-top: 1px solid #f1f5f9;
    }

    .login-footer a {
        color: #fa8507;
        text-decoration: none;
        font-weight: 700;
    }
</style>
