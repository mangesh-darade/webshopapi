<?php
/**
 * CLI check: why HEADER rows show or not on the storefront.
 * Run: php application/tests/header_slot_logic_test.php
 */

function webshop_header_slot_kind_for_field_key($field_key) {
    $fk = strtolower(trim((string) $field_key));
    if ($fk === 'announcement_bar' || preg_match('/^(header_)?announcement|promo_bar|top_notice$/', $fk)) {
        return 'announcement';
    }
    if ($fk === 'top_bar_html' || preg_match('/^(header|top)_(html|notice|message)$/', $fk)) {
        return 'top_html';
    }
    if ($fk === 'phone_strip' || preg_match('/^(header|top)_(phone|tel|hotline)$/', $fk)) {
        return 'phone';
    }
    return '';
}

function webshop_header_slot_kind_resolve($field_key, $value = '') {
    $kind = webshop_header_slot_kind_for_field_key($field_key);
    if ($kind !== '') {
        return $kind;
    }
    if (trim((string) $value) !== '') {
        return 'top_html';
    }
    return '';
}

function row_would_render($field_key, $value, $is_active = 1) {
    if ((int) $is_active !== 1) {
        return array('render' => false, 'reason' => 'inactive (is_active must be 1)');
    }
    $fk = strtolower(trim($field_key));
    if ($fk === '') {
        return array('render' => false, 'reason' => 'empty field_key');
    }
    $structural = in_array($fk, array('logo_image', 'banner_image', 'favicon'), true);
    if ($structural) {
        return array('render' => false, 'reason' => 'structural key (logo/banner) — not a text strip');
    }
    $kind = webshop_header_slot_kind_resolve($fk, $value);
    if ($kind === '') {
        return array('render' => false, 'reason' => 'unknown field_key AND empty value');
    }
    if (trim($value) === '' && $kind !== 'phone') {
        return array('render' => false, 'reason' => 'empty value (add text/HTML in admin VALUE)');
    }
    return array('render' => true, 'reason' => 'shows in header strip: ' . $kind);
}

echo "=== Header slot render simulation ===\n\n";

$cases = array(
    array('label' => 'Your CMS row (screenshot)', 'field_key' => 'testq', 'value' => '', 'is_active' => 1),
    array('label' => 'testq with value', 'field_key' => 'testq', 'value' => 'Free delivery today', 'is_active' => 1),
    array('label' => 'Preset announcement', 'field_key' => 'announcement_bar', 'value' => 'Sale 50%', 'is_active' => 1),
    array('label' => 'Preset top bar', 'field_key' => 'top_bar_html', 'value' => '<b>Hello</b>', 'is_active' => 1),
    array('label' => 'Inactive', 'field_key' => 'top_bar_html', 'value' => 'Hi', 'is_active' => 0),
);

foreach ($cases as $c) {
    $r = row_would_render($c['field_key'], $c['value'], $c['is_active']);
    $status = $r['render'] ? 'SHOW' : 'HIDE';
    echo sprintf("[%s] %s | key=%s | value=%s | active=%d => %s — %s\n",
        $status,
        $c['label'],
        $c['field_key'],
        $c['value'] === '' ? '(empty)' : $c['value'],
        $c['is_active'],
        $status,
        $r['reason']
    );
}

echo "\nDone.\n";
