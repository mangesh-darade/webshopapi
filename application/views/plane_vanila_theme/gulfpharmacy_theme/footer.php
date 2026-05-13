<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$ws        = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$S         = isset($Settings) && is_object($Settings) ? $Settings : new stdClass();
$shop_name = webshop_store_display_name($S, $ws);
if ($shop_name === '') {
    $shop_name = 'Shop';
}
$yr = date('Y');

$footer_rows = function_exists('webshop_footer_identity_rows') ? webshop_footer_identity_rows() : array();
$has_main    = !empty($footer_rows);
$_gp_uploads_base = '';
if (isset($uploads) && (string) $uploads !== '') {
    $_gp_uploads_base = rtrim((string) $uploads, '/') . '/';
} elseif (isset($this->data['uploads']) && (string) $this->data['uploads'] !== '') {
    $_gp_uploads_base = rtrim((string) $this->data['uploads'], '/') . '/';
}
?>
<footer class="gp-footer">
    <div class="gp-footer-wave" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 80" preserveAspectRatio="none"><path fill="#214548" d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z"/></svg>
    </div>
    <div class="gp-footer-body">
        <div class="container">
            <?php if ($has_main) : ?>
            <div class="gp-footer-main">
                <div class="gp-footer-grid">
                    <?php foreach ($footer_rows as $fr) :
                        $fk = isset($fr['field_key']) ? (string) $fr['field_key'] : '';
                        if ($fk === '') {
                            continue;
                        }
                        $lab = isset($fr['label']) ? trim((string) $fr['label']) : '';
                        $icon_cls = isset($fr['icons']) ? trim((string) $fr['icons']) : '';
                        $raw_val = isset($fr['value']) ? (string) $fr['value'] : '';

                        $href = function_exists('webshop_footer_row_link_href')
                            ? webshop_footer_row_link_href($fk, $raw_val)
                            : '';
                        $link_extra = ($href !== '' && strpos($href, 'tel:') !== 0)
                            ? ' target="_blank" rel="noopener noreferrer"'
                            : '';

                        /* Heading: optional when icon is set; otherwise humanize field_key if no admin label. */
                        $show_heading = false;
                        $heading_text = '';
                        if ($lab !== '') {
                            $show_heading = true;
                            $heading_text = $lab;
                        } elseif ($icon_cls === '') {
                            $show_heading = true;
                            $heading_text = ucwords(str_replace('_', ' ', $fk));
                        }

                        $suppress_body = ($href !== '' && ($icon_cls !== '' || $lab !== '')
                            && function_exists('webshop_footer_value_is_link_only')
                            && webshop_footer_value_is_link_only($fk, $raw_val));

                        $body_html = '';
                        if (!$suppress_body && function_exists('webshop_footer_row_body_html')) {
                            $body_html = webshop_footer_row_body_html($fk, $raw_val, $_gp_uploads_base);
                        }
                        if ($body_html === '' && !$suppress_body && trim($raw_val) === '') {
                            $body_html = '<span class="gp-footer-empty text-muted">&mdash;</span>';
                        }

                        $aria_slot = $lab !== '' ? $lab : ($heading_text !== '' ? $heading_text : ucwords(str_replace('_', ' ', $fk)));
                        ?>
                    <div class="gp-footer-col gp-footer-col--slot" data-field-key="<?= htmlspecialchars($fk, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if ($show_heading && $heading_text !== '') : ?>
                        <h3 class="gp-footer-heading">
                            <?php if ($href !== '' && $lab !== '') : ?>
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="gp-footer-heading-link"<?= $link_extra ?>><?= htmlspecialchars($heading_text, ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php else : ?>
                            <?= htmlspecialchars($heading_text, ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </h3>
                        <?php endif; ?>

                        <div class="gp-footer-text<?= ($icon_cls !== '' && $href !== '') ? ' gp-footer-text--with-link' : ''; ?>">
                            <?php if ($icon_cls !== '') : ?>
                                <?php if ($href !== '') : ?>
                            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="gp-footer-slot-icon-link" aria-label="<?= htmlspecialchars($aria_slot, ENT_QUOTES, 'UTF-8'); ?>"<?= $link_extra ?>>
                                <span class="gp-footer-slot-icon" aria-hidden="true"><i class="<?= htmlspecialchars($icon_cls, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                            </a>
                                <?php else : ?>
                            <span class="gp-footer-slot-icon" aria-hidden="true"><i class="<?= htmlspecialchars($icon_cls, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($body_html !== '') : ?>
                            <span class="gp-footer-slot-body"><?= $body_html ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="gp-footer-bottom">
                <div class="gp-copy">&copy; <?= $yr ?> <?= htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</div>
                <a href="https://elintom.com" target="_blank" rel="noopener" class="gp-credit">Powered by ElintOm</a>
            </div>
        </div>
    </div>
</footer>
<link rel="stylesheet" href="<?= isset($assets) ? $assets : base_url('assets/webshop/') ?>gulfpharmacy_theme/css/components.css">
