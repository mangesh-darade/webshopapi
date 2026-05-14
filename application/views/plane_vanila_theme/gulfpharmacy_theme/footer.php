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
                    <?php
                    $sections = array();
                    foreach ($footer_rows as $fr) {
                        $sk = isset($fr['section']) ? (string) $fr['section'] : 'footer';
                        if (!isset($sections[$sk])) {
                            $sections[$sk] = array('rows' => array(), 'label' => '');
                        }
                        $sections[$sk]['rows'][] = $fr;
                    }

                    foreach ($sections as $sk => $sec) :
                        $rows = $sec['rows'];
                        $is_social_sec = false;
                        $social_items = array();
                        $other_items = array();

                        foreach ($rows as $r) {
                            $fk = isset($r['field_key']) ? (string) $r['field_key'] : '';
                            $is_social = (bool) preg_match('/^media_[a-z0-9_]+_link$/i', $fk)
                                || (bool) preg_match('/^(facebook|fb|instagram|ig|twitter|x_twitter|linkedin|youtube|tiktok)[a-z0-9_]*$/i', $fk);
                            
                            if ($is_social) {
                                $social_items[] = $r;
                            } else {
                                $other_items[] = $r;
                            }
                        }

                        if (!empty($social_items) && empty($other_items)) {
                            $is_social_sec = true;
                        }

                        if ($is_social_sec) : ?>
                            <div class="gp-footer-col">
                                <h3 class="gp-footer-heading">Follow Us</h3>
                                <div class="gp-footer-social">
                                    <?php foreach ($social_items as $si) : 
                                        $fk = (string) $si['field_key'];
                                        $val = (string) $si['value'];
                                        $href = function_exists('webshop_footer_row_link_href') ? webshop_footer_row_link_href($fk, $val) : $val;
                                        if ($href === '') continue;
                                        
                                        $icon = 'fa fa-link';
                                        if (strpos($fk, 'facebook') !== false) $icon = 'fa fa-facebook-f';
                                        elseif (strpos($fk, 'instagram') !== false) $icon = 'fa fa-instagram';
                                        elseif (strpos($fk, 'twitter') !== false || strpos($fk, '_x_') !== false) $icon = 'fa fa-twitter';
                                        elseif (strpos($fk, 'youtube') !== false) $icon = 'fa fa-youtube-play';
                                        elseif (strpos($fk, 'linkedin') !== false) $icon = 'fa fa-linkedin';
                                        ?>
                                        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="gp-social-btn" title="<?= htmlspecialchars($si['label'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="<?= $icon ?>"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else : 
                            // Render as content column(s)
                            // If the section has multiple rows, we group them in one column
                            // If it has only one row, we use its label as heading
                            $heading = ucwords(str_replace('_', ' ', $sk));
                            if (count($other_items) === 1) {
                                $item = $other_items[0];
                                $heading = !empty($item['label']) ? $item['label'] : $heading;
                            }
                            ?>
                            <div class="gp-footer-col">
                                <h3 class="gp-footer-heading"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="gp-footer-content-list">
                                    <?php foreach ($other_items as $item) : 
                                        $fk = (string) $item['field_key'];
                                        $raw_val = (string) $item['value'];
                                        $icon_cls = isset($item['icons']) ? trim((string) $item['icons']) : '';
                                        $lab = (string) $item['label'];
                                        $body_html = function_exists('webshop_footer_row_body_html')
                                            ? webshop_footer_row_body_html($fk, $raw_val, $_gp_uploads_base, $lab)
                                            : nl2br(htmlspecialchars($raw_val, ENT_QUOTES, 'UTF-8'));
                                        
                                        if (trim($body_html) === '' && trim($raw_val) === '') continue;
                                        ?>
                                        <div class="gp-footer-text">
                                            <?php if ($icon_cls !== '') : ?>
                                                <span class="gp-footer-slot-icon"><i class="<?= htmlspecialchars($icon_cls, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                            <?php endif; ?>
                                            <span class="gp-footer-slot-body"><?= $body_html ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (!empty($social_items)) : ?>
                                        <div class="gp-footer-social gp-footer-social--inline">
                                            <?php foreach ($social_items as $si) : 
                                                $fk = (string) $si['field_key'];
                                                $val = (string) $si['value'];
                                                $href = function_exists('webshop_footer_row_link_href') ? webshop_footer_row_link_href($fk, $val) : $val;
                                                if ($href === '') continue;
                                                
                                                $icon = 'fa fa-link';
                                                if (strpos($fk, 'facebook') !== false) $icon = 'fa fa-facebook-f';
                                                elseif (strpos($fk, 'instagram') !== false) $icon = 'fa fa-instagram';
                                                elseif (strpos($fk, 'twitter') !== false || strpos($fk, '_x_') !== false) $icon = 'fa fa-twitter';
                                                elseif (strpos($fk, 'youtube') !== false) $icon = 'fa fa-youtube-play';
                                                elseif (strpos($fk, 'linkedin') !== false) $icon = 'fa fa-linkedin';
                                                ?>
                                                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="gp-social-btn" title="<?= htmlspecialchars($si['label'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="<?= $icon ?>"></i>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
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
