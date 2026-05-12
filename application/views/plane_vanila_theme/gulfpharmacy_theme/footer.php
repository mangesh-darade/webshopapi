<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws        = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$shop_name = isset($Settings->site_name) && $Settings->site_name !== '' ? $Settings->site_name : (isset($ws->site_name) ? $ws->site_name : 'My Shop');
$yr        = date('Y');
?>

<footer class="gp-footer">
    <div class="gp-footer-wave" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 80" preserveAspectRatio="none"><path fill="#214548" d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z"/></svg>
    </div>
    <div class="gp-footer-body">
        <div class="container">
            <div class="gp-footer-bottom">
                <div class="gp-copy">&copy; <?= $yr ?> <?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</div>
                <a href="https://elintom.com" target="_blank" rel="noopener" class="gp-credit">Powered by ElintOm</a>
            </div>
        </div>
    </div>
</footer>
<link rel="stylesheet" href="<?= isset($assets) ? $assets : base_url('assets/webshop/') ?>gulfpharmacy_theme/css/components.css">
