<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$S  = isset($Settings) && is_object($Settings) ? $Settings : new stdClass();
$shop_name = webshop_store_display_name($S, $ws);
if ($shop_name === '') {
    $shop_name = 'Shop';
}
$uploadsBase = isset($uploads) ? (string) $uploads : '';
$logo_url = function_exists('webshop_resolve_header_logo_url')
    ? webshop_resolve_header_logo_url($uploadsBase, $S, $ws, '')
    : '';
if ($logo_url === '' && function_exists('webshop_resolve_storefront_logo_image_url')) {
    $_gp_uploads_base = $uploadsBase !== '' ? rtrim($uploadsBase, '/') . '/' : '';
    $logo_url = webshop_resolve_storefront_logo_image_url($_gp_uploads_base);
}
$logo_src = $logo_url;
$webshop_url = base_url('webshop');
$ws_base = rtrim($webshop_url, '/');

$hb_footer = function_exists('webshop_herbinn_footer_layout_from_storefront')
    ? webshop_herbinn_footer_layout_from_storefront()
    : array('has_data' => false);

$tagline = isset($hb_footer['tagline']) ? (string) $hb_footer['tagline'] : '';
$copyright = function_exists('webshop_herbinn_footer_copyright_line')
    ? webshop_herbinn_footer_copyright_line(isset($hb_footer['copyright']) ? $hb_footer['copyright'] : '')
    : '';
$headings = isset($hb_footer['headings']) && is_array($hb_footer['headings']) ? $hb_footer['headings'] : array();
$company_heading = isset($headings['company']) ? (string) $headings['company'] : '';
$legal_heading = isset($headings['legal']) ? (string) $headings['legal'] : '';
$certs_heading = isset($headings['certifications']) ? (string) $headings['certifications'] : '';
$office_heading = isset($headings['office']) ? (string) $headings['office'] : '';
$office_address = isset($hb_footer['office_address']) ? (string) $hb_footer['office_address'] : '';
$company_links = isset($hb_footer['company_links']) && is_array($hb_footer['company_links']) ? $hb_footer['company_links'] : array();
$legal_links = isset($hb_footer['legal_links']) && is_array($hb_footer['legal_links']) ? $hb_footer['legal_links'] : array();
$extra_links = isset($hb_footer['extra_links']) && is_array($hb_footer['extra_links']) ? $hb_footer['extra_links'] : array();
$misc_lines = isset($hb_footer['misc_lines']) && is_array($hb_footer['misc_lines']) ? $hb_footer['misc_lines'] : array();
$certifications = isset($hb_footer['certifications']) && is_array($hb_footer['certifications']) ? $hb_footer['certifications'] : array();
$certs_html = isset($hb_footer['certifications_html']) ? trim((string) $hb_footer['certifications_html']) : '';

$_hb_cert_items = array();
foreach ($certifications as $_hb_cert) {
    $_hb_parts = preg_split('/\s*\|\s*|[,\r\n]+/', (string) $_hb_cert);
    foreach ($_hb_parts as $_hb_part) {
        $_hb_part = trim((string) $_hb_part);
        if ($_hb_part !== '') {
            $_hb_cert_items[] = $_hb_part;
        }
    }
}
$certifications = $_hb_cert_items;

$show_footer = $logo_src !== ''
    || !empty($hb_footer['has_data'])
    || $tagline !== ''
    || $copyright !== ''
    || !empty($company_links)
    || !empty($legal_links)
    || !empty($extra_links)
    || !empty($misc_lines)
    || !empty($certifications)
    || $certs_html !== ''
    || $office_address !== '';

$_gp_footer_styles_in_head = !empty($gp_footer_styles_in_head);
$_hb_footer_css = (!$_gp_footer_styles_in_head && function_exists('webshop_theme_has_stylesheet') && webshop_theme_has_stylesheet('css/herbinn-footer.css'))
    ? webshop_theme_assets_url('css/herbinn-footer.css?ver=20260601b')
    : '';
?>
<?php if ($show_footer) : ?>
<?php if (!$_gp_footer_styles_in_head && $_hb_footer_css !== '') : ?>
<link rel="stylesheet" href="<?= htmlspecialchars($_hb_footer_css, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<footer class="bg-white hb-site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="brand-col">
                <a href="<?= htmlspecialchars($webshop_url, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($logo_src !== '') : ?>
                    <img src="<?= htmlspecialchars($logo_src, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>">
                    <?php else : ?>
                    <span class="navbar-brand-text"><?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($tagline !== '') : ?>
                <p><?= htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (!empty($misc_lines)) : ?>
                <div class="hb-footer-misc">
                    <?php foreach ($misc_lines as $misc_line) :
                        $misc_line = trim((string) $misc_line);
                        if ($misc_line === '') {
                            continue;
                        }
                    ?>
                    <p class="hb-footer-misc-line"><?= htmlspecialchars($misc_line, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($company_heading !== '' || !empty($company_links) || !empty($extra_links)) : ?>
            <div class="footer-col footer-col--company">
                <?php if ($company_heading !== '') : ?>
                <h6><?= htmlspecialchars($company_heading, ENT_QUOTES, 'UTF-8') ?></h6>
                <?php endif; ?>
                <?php if (!empty($company_links) || !empty($extra_links)) : ?>
                <ul>
                    <?php foreach ($company_links as $lnk) :
                        $href = webshop_resolve_cms_path_href((string) $lnk['href'], $ws_base);
                        $title = (string) $lnk['title'];
                        if ($title === '' || $href === '') {
                            continue;
                        }
                    ?>
                    <li><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                    <?php foreach ($extra_links as $lnk) :
                        $href = function_exists('webshop_resolve_cms_path_href')
                            ? webshop_resolve_cms_path_href((string) $lnk['href'], $ws_base)
                            : (string) $lnk['href'];
                        $title = (string) $lnk['title'];
                        if ($title === '' || $href === '') {
                            continue;
                        }
                    ?>
                    <li><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($legal_heading !== '' || !empty($legal_links)) : ?>
            <div class="footer-col footer-col--legal">
                <?php if ($legal_heading !== '') : ?>
                <h6><?= htmlspecialchars($legal_heading, ENT_QUOTES, 'UTF-8') ?></h6>
                <?php endif; ?>
                <?php if (!empty($legal_links)) : ?>
                <ul>
                    <?php foreach ($legal_links as $lnk) :
                        $href = webshop_resolve_cms_path_href((string) $lnk['href'], $ws_base);
                        $title = (string) $lnk['title'];
                        if ($title === '' || $href === '') {
                            continue;
                        }
                    ?>
                    <li><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($certs_heading !== '' || $certs_html !== '' || !empty($certifications) || $office_heading !== '' || $office_address !== '') : ?>
            <div class="footer-col footer-col--certs">
                <?php if ($certs_heading !== '') : ?>
                <h6><?= htmlspecialchars($certs_heading, ENT_QUOTES, 'UTF-8') ?></h6>
                <?php endif; ?>
                <?php if ($certs_html !== '') : ?>
                <?php if (strpos($certs_html, '<') === false) : ?>
                <div class="footer-certs">
                    <?php foreach (preg_split('/\s*\|\s*|[,\r\n]+/', $certs_html) as $_hb_cert_html_part) :
                        $_hb_cert_html_part = trim((string) $_hb_cert_html_part);
                        if ($_hb_cert_html_part === '') {
                            continue;
                        }
                    ?>
                    <span class="badge badge-border"><?= htmlspecialchars($_hb_cert_html_part, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <div class="footer-certs"><?= function_exists('webshop_normalize_html_media_urls') ? webshop_normalize_html_media_urls($certs_html, $uploadsBase) : $certs_html ?></div>
                <?php endif; ?>
                <?php elseif (!empty($certifications)) : ?>
                <div class="footer-certs">
                    <?php foreach ($certifications as $cert) :
                        $cert = trim((string) $cert);
                        if ($cert === '') {
                            continue;
                        }
                    ?>
                    <span class="badge badge-border"><?= htmlspecialchars($cert, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($office_heading !== '' || $office_address !== '') : ?>
                <div class="hb-footer-office">
                    <?php if ($office_heading !== '') : ?>
                    <h6 class="hb-footer-office-title"><?= htmlspecialchars($office_heading, ENT_QUOTES, 'UTF-8') ?></h6>
                    <?php endif; ?>
                    <?php if ($office_address !== '') : ?>
                    <p class="hb-footer-office-address"><?= nl2br(htmlspecialchars($office_address, ENT_QUOTES, 'UTF-8'), false) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($copyright !== '') : ?>
        <div class="footer-bottom">
            <p><?= htmlspecialchars($copyright, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>
    </div>
</footer>
<?php endif; ?>
<?php
$_gp_csrf = function_exists('webshop_csrf_pair') ? webshop_csrf_pair() : array('name' => '', 'hash' => '');
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
<script defer src="<?= htmlspecialchars(webshop_theme_assets_url('js/webshop-csrf.js?ver=20260526h'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(webshop_theme_assets_url('js/webshop-wishlist.js?ver=20260526h'), ENT_QUOTES, 'UTF-8') ?>"></script>
