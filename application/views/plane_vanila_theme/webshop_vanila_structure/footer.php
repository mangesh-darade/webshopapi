<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws        = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$S         = isset($Settings) && is_object($Settings) ? $Settings : new stdClass();
$shop_name = webshop_store_display_name($S, $ws);
if ($shop_name === '') {
    $shop_name = 'Shop';
}
$yr = date('Y');

$_gp_footer_layout = function_exists('webshop_footer_gather_display_rows')
    ? webshop_footer_gather_display_rows()
    : array('content' => array(), 'social' => array());
$_gp_footer_content = isset($_gp_footer_layout['content']) ? $_gp_footer_layout['content'] : array();
$_gp_footer_social  = isset($_gp_footer_layout['social']) ? $_gp_footer_layout['social'] : array();
$has_main = !empty($_gp_footer_content) || !empty($_gp_footer_social);

$_gp_uploads_base = '';
if (isset($uploads) && (string) $uploads !== '') {
    $_gp_uploads_base = rtrim((string) $uploads, '/') . '/';
} elseif (isset($this->data['uploads']) && (string) $this->data['uploads'] !== '') {
    $_gp_uploads_base = rtrim((string) $this->data['uploads'], '/') . '/';
}

$_gp_footer_logo = function_exists('webshop_resolve_storefront_logo_image_url')
    ? webshop_resolve_storefront_logo_image_url($_gp_uploads_base)
    : '';

$_gp_assets = isset($assets) ? $assets : base_url('assets/webshop/');
$_gp_footer_styles_in_head = !empty($gp_footer_styles_in_head);
?>
<?php if (!$_gp_footer_styles_in_head): ?>
<?php if (function_exists('webshop_async_stylesheet_tag')): ?>
<?= webshop_async_stylesheet_tag($_gp_assets . 'css/techmarket-font-awesome.css') ?>

<?= webshop_async_stylesheet_tag(webshop_theme_assets_url('css/components.css')) ?>

<?php else: ?>
<link rel="stylesheet" href="<?= htmlspecialchars($_gp_assets, ENT_QUOTES, 'UTF-8') ?>css/techmarket-font-awesome.css">
<link rel="stylesheet" href="<?= htmlspecialchars(webshop_theme_assets_url('css/components.css'), ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php endif; ?>
<footer class="gp-footer">
    <div class="gp-footer-wave" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 80" preserveAspectRatio="none"><path fill="#214548" d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z"/></svg>
    </div>
    <div class="gp-footer-body">
        <div class="container">
            <?php if ($has_main) : ?>
            <div class="gp-footer-main">
                <div class="gp-footer-grid">
                    <?php if ($_gp_footer_logo !== '' || !empty($_gp_footer_content)) : ?>
                    <div class="gp-footer-col gp-footer-col--brand">
                        <a href="<?= base_url('webshop') ?>" class="gp-footer-brand-link">
                            <h3 class="gp-footer-heading gp-footer-brand-text"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($_gp_footer_content as $item) :
                        $fk = (string) $item['field_key'];
                        $raw_val = (string) $item['value'];
                        $icon_db = isset($item['icons']) ? trim((string) $item['icons']) : '';
                        $icon_cls = function_exists('webshop_footer_row_display_icon_class')
                            ? webshop_footer_row_display_icon_class($fk, $icon_db)
                            : $icon_db;
                        $lab = trim((string) $item['label']);
                        $heading = $lab !== '' ? $lab : ucwords(str_replace('_', ' ', $fk));
                        $body_html = function_exists('webshop_footer_row_body_html')
                            ? webshop_footer_row_body_html($fk, $raw_val, $_gp_uploads_base, '')
                            : nl2br(htmlspecialchars($raw_val, ENT_QUOTES, 'UTF-8'));
                        if (trim(strip_tags($body_html)) === '' && trim($raw_val) === '') {
                            $body_html = '<span class="gp-footer-empty">&mdash;</span>';
                        }
                    ?>
                    <div class="gp-footer-col">
                        <h3 class="gp-footer-heading"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="gp-footer-content-list">
                            <div class="gp-footer-text">
                                <?php if ($icon_cls !== '') : ?>
                                <span class="gp-footer-slot-icon"><i class="<?= htmlspecialchars($icon_cls, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                <?php endif; ?>
                                <span class="gp-footer-slot-body"><?= $body_html ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($_gp_footer_content) && $_gp_footer_logo === '' && empty($_gp_footer_social)) : ?>
                    <div class="gp-footer-col">
                        <h3 class="gp-footer-heading"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($_gp_footer_social)) : ?>
                    <div class="gp-footer-col gp-footer-col--social">
                        <h3 class="gp-footer-heading">Follow Us</h3>
                        <div class="gp-footer-social">
                            <?php foreach ($_gp_footer_social as $si) :
                                $fk = (string) $si['field_key'];
                                $val = (string) $si['value'];
                                $href = function_exists('webshop_footer_row_link_href')
                                    ? webshop_footer_row_link_href($fk, $val)
                                    : $val;
                                $icon = function_exists('webshop_footer_social_icon_class')
                                    ? webshop_footer_social_icon_class($fk)
                                    : 'fa fa-link';
                                $title = isset($si['label']) && (string) $si['label'] !== ''
                                    ? (string) $si['label']
                                    : ucwords(str_replace('_', ' ', preg_replace('/^media_|_link$/', '', $fk)));
                                $disabled = ($href === '');
                                if ($disabled) {
                                    $href = '#';
                                }
                            ?>
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                               class="gp-social-btn<?= $disabled ? ' gp-social-btn--disabled' : '' ?>"
                               <?= $disabled ? ' aria-disabled="true" tabindex="-1"' : ' target="_blank" rel="noopener noreferrer"' ?>
                               title="<?= htmlspecialchars($title . ($disabled ? ' (add URL in ElintOm Storefront)' : ''), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="gp-footer-bottom">
                <div class="gp-copy">&copy; <?= $yr ?> <?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</div>
                <a href="https://elintom.io" target="_blank" rel="noopener" class="gp-credit">Powered by ElintOm</a>
            </div>
        </div>
    </div>
</footer>
<?php
$_gp_csrf = function_exists('webshop_csrf_pair') ? webshop_csrf_pair() : array('name' => '', 'hash' => '');
?>
<?php
$_gp_wl_lookup = function_exists('webshop_view_wishlist_lookup')
    ? webshop_view_wishlist_lookup(isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : null)
    : (isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : array());
$_gp_logged_in = function_exists('webshop_is_customer_logged_in')
    ? webshop_is_customer_logged_in()
    : !empty($webshop_is_logged_in);
?>
<script>window.GP_CSRF=<?= json_encode($_gp_csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>window.GP_PLP_CTX=Object.assign(window.GP_PLP_CTX||{},<?= json_encode(array(
    'request_url'     => base_url('webshop/webshop_request'),
    'login_url'       => base_url('webshop/login'),
    'is_logged_in'    => (bool) $_gp_logged_in,
    'wishlist_lookup' => $_gp_wl_lookup,
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);</script>
<script defer src="<?= htmlspecialchars(webshop_theme_assets_url('js/webshop-csrf.js?ver=20260526c'), ENT_QUOTES, 'UTF-8') ?>"></script>
