<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws        = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$shop_name = isset($Settings->site_name) && $Settings->site_name !== '' ? $Settings->site_name : (isset($ws->site_name) ? $ws->site_name : 'My Shop');
$cms_nav   = isset($cms_nav_pages) && is_array($cms_nav_pages) ? $cms_nav_pages : array();
$footer_pg = isset($footer_theme_pages) && is_array($footer_theme_pages) ? $footer_theme_pages : array();
$yr        = date('Y');
$webshop_url = base_url('webshop');
$phone     = isset($ws->phone) && $ws->phone !== '' ? $ws->phone : (isset($ws->mobile) ? $ws->mobile : '');
$email_c   = isset($ws->email) && $ws->email !== '' ? $ws->email : '';
$addr      = isset($ws->address) && $ws->address !== '' ? $ws->address : '';
?>

<footer class="gp-footer">
    <div class="gp-footer-wave">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 80" preserveAspectRatio="none"><path fill="#214548" d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z"/></svg>
    </div>
    <div class="gp-footer-body">
        <div class="container">
            <div class="gp-footer-grid">
                <div class="gp-footer-col">
                    <h3>About Us</h3>
                    <p style="color:#94a3b8;line-height:1.6;font-size:15px;"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?> is your trusted healthcare partner, providing quality pharmaceutical products and professional care to our community.</p>
                </div>
                <div class="gp-footer-col">
                    <h3>Quick Links</h3>
                    <ul class="gp-footer-links">
                        <li><a href="<?= $webshop_url ?>">Home</a></li>
                        <li><a href="<?= $webshop_url ?>/search_products">All Products</a></li>
                        <?php foreach ($cms_nav as $p): ?>
                        <li><a href="<?= $webshop_url . '/' . (isset($p['slug']) ? $p['slug'] : '') ?>"><?= htmlspecialchars(isset($p['title']) ? $p['title'] : '', ENT_QUOTES, 'UTF-8') ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="gp-footer-col">
                    <h3>Customer Service</h3>
                    <ul class="gp-footer-links">
                        <li><a href="<?= $webshop_url ?>/contact">Contact Us</a></li>
                        <li><a href="<?= $webshop_url ?>/terms">Terms & Conditions</a></li>
                        <li><a href="<?= $webshop_url ?>/privacy">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="gp-footer-col">
                    <h3>Contact</h3>
                    <p style="color:#94a3b8;line-height:1.6;font-size:15px;">
                        <?php if ($email_c): ?>Email: <?= htmlspecialchars($email_c, ENT_QUOTES, 'UTF-8') ?><br><?php endif; ?>
                        <?php if ($phone): ?>Phone: <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="gp-footer-bottom">
                <div class="gp-copy">&copy; <?= $yr ?> <?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</div>
                <a href="https://elintom.com" target="_blank" class="gp-credit">Powered by ElintOm</a>
            </div>
        </div>
    </div>
</footer>

<style>
    .gp-footer { background: transparent; margin-top: auto; }
    .gp-footer-wave { line-height: 0; }
    .gp-footer-wave svg { width: 100%; height: 60px; }
    .gp-footer-body { background: #214548; color: #cbd5e1; padding: 40px 0 30px; }
    .gp-footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; margin-bottom: 40px; }
    .gp-footer-col h3 { font-size: 18px; font-weight: 700; margin-bottom: 24px; color: #fff; letter-spacing: -0.2px; }
    .gp-footer-links { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
    .gp-footer-links a { color: #94a3b8; text-decoration: none; font-size: 15px; transition: all .2s; display: inline-block; }
    .gp-footer-links a:hover { color: #4caf89; transform: translateX(5px); }
    .gp-footer-bottom { padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
    .gp-copy { color: #64748b; font-size: 14px; }
    .gp-credit { color: #64748b; font-size: 13px; text-decoration: none; transition: color .2s; }
    .gp-credit:hover { color: #fff; }
    @media (max-width: 768px) {
        .gp-footer-grid { grid-template-columns: 1fr; gap: 30px; }
        .gp-footer-bottom { flex-direction: column; text-align: center; }
    }
</style>
