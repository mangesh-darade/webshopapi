<?php

/**
 * CMS banners on ElintOm live under uploads/webshop/cms_pages/ (not uploads/webshop/ alone).
 *
 * @param string $path_part Relative path after mdata/uploads/ prefix is removed
 * @return string
 */
function webshop_fixup_cms_media_relative_path($path_part) {
    $path_part = ltrim(str_replace('\\', '/', (string) $path_part), '/');
    if ($path_part === '') {
        return $path_part;
    }
    if (preg_match('#^webshop/cms_pages/#i', $path_part)) {
        return $path_part;
    }
    if (preg_match('#^webshop/(?!cms_pages/)(.+)$#i', $path_part, $m)) {
        return 'webshop/cms_pages/' . $m[1];
    }
    if (preg_match('#^cms_pages/(.+)$#i', $path_part, $m)) {
        return 'webshop/cms_pages/' . $m[1];
    }
    if (preg_match('#^uploads/webshop/cms_pages/#i', $path_part)) {
        return preg_replace('#^uploads/#i', '', $path_part);
    }
    return $path_part;
}

/**
 * Build image URL: POS paths are relative to uploads base; some payloads return absolute URLs.
 * ElintOm/CMS may still emit legacy …/assets/uploads/… — those are mapped onto the mdata uploads
 * root ($uploads_base), i.e. …/assets/mdata/{host}/uploads/…
 *
 * @param string $uploads_base From Webshop_api_model::get_media_uploads_base() / view $uploads
 * @param string $path         Relative path or http(s) URL
 */
function webshop_media_src($uploads_base, $path) {
    if ($path === null || $path === '') {
        return '';
    }
    $s = trim((string) $path);
    if ($s === '') {
        return '';
    }

    // Must come from Webshop_api_model::get_media_uploads_base() (dynamic mdata host root).
    $base = rtrim(str_replace('\\', '/', (string) $uploads_base), '/') . '/';
    $base_path = (string) parse_url($base, PHP_URL_PATH);
    $base_mdata_folder = '';
    if ($base_path !== '' && preg_match('#/assets/mdata/([^/]+)/uploads/?$#i', str_replace('\\', '/', $base_path), $bm) && isset($bm[1])) {
        $base_mdata_folder = (string) $bm[1];
    }

    $query = '';
    $path_part = $s;
    if (preg_match('#^https?://#i', $s)) {
        $parsed = @parse_url($s);
        $path_part = isset($parsed['path']) ? (string) $parsed['path'] : '';
        $query = !empty($parsed['query']) ? '?' . (string) $parsed['query'] : '';
    }

    $path_part = str_replace('\\', '/', (string) $path_part);
    if ($path_part === '') {
        return '';
    }

    // Absolute path variants (with or without /ElintOm/ app folder in URL).
    if (preg_match('#/assets/mdata/[^/]+/uploads/(.+)$#i', $path_part, $m) && isset($m[1]) && trim($m[1]) !== '') {
        $path_part = $m[1];
    } elseif (preg_match('#/ElintOm/assets/mdata/[^/]+/uploads/(.+)$#i', $path_part, $m) && isset($m[1]) && trim($m[1]) !== '') {
        $path_part = $m[1];
    } elseif (preg_match('#/assets/uploads/(.+)$#i', $path_part, $m) && isset($m[1]) && trim($m[1]) !== '') {
        $path_part = $m[1];
    } else {
        // Relative path variants.
        $path_part = preg_replace('#^/?assets/mdata/[^/]+/uploads/#i', '', $path_part);
        $path_part = preg_replace('#^/?assets/uploads/#i', '', $path_part);
        $path_part = preg_replace('#^/?uploads/#i', '', $path_part);
        // Some records store "{hostOrTenant}/uploads/file.jpg".
        if ($base_mdata_folder !== '') {
            $quoted = preg_quote($base_mdata_folder, '#');
            $path_part = preg_replace('#^/?' . $quoted . '/uploads/#i', '', $path_part);
        }
        // Do not strip "webshop/uploads/…" — that removes the webshop/ folder and breaks cms_pages paths.
    }
    $path_part = webshop_fixup_cms_media_relative_path($path_part);
    return $base . ltrim($path_part, '/') . $query;
}

if (!function_exists('webshop_external_origin_preconnect_tag')) {
    /**
     * Preconnect to an external image/asset host (speeds remote logo/product media).
     *
     * @param string $url
     * @return string Safe HTML fragment or empty string
     */
    function webshop_external_origin_preconnect_tag($url)
    {
        if (!preg_match('#^https?://#i', (string) $url)) {
            return '';
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return '';
        }
        $scheme = (string) parse_url($url, PHP_URL_SCHEME);
        if ($scheme === '') {
            $scheme = 'https';
        }
        $origin = $scheme . '://' . $host;

        return '<link rel="preconnect" href="' . htmlspecialchars($origin, ENT_QUOTES, 'UTF-8') . '" crossorigin>';
    }
}

if (!function_exists('webshop_async_stylesheet_tag')) {
    /**
     * Non-render-blocking stylesheet (footer icons, component polish).
     *
     * @param string $href Absolute or root-relative URL
     * @return string
     */
    function webshop_async_stylesheet_tag($href)
    {
        $href = trim((string) $href);
        if ($href === '') {
            return '';
        }
        $safe = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

        return '<link rel="stylesheet" href="' . $safe . '" media="print" onload="this.media=\'all\'">'
            . '<noscript><link rel="stylesheet" href="' . $safe . '"></noscript>';
    }
}

/**
 * Map HTML attribute URLs that start with /assets/... to the app base_url (fixes 404 when the storefront
 * runs under a subfolder like /ElintOm/ and CMS meta/body uses root-relative paths).
 * runs under a subfolder like /ElintOm/ and CMS meta/body uses root-relative paths).
 *
 * @param string $html
 * @return string
 */
function webshop_rewrite_root_relative_asset_urls($html)
{
    if (!is_string($html) || trim($html) === '') {
        return (string) $html;
    }
    $appBase = '';
    if (function_exists('get_instance')) {
        $CI = @get_instance();
        if ($CI && isset($CI->config)) {
            $appBase = rtrim((string) $CI->config->item('base_url'), '/') . '/';
        }
    }
    if ($appBase === '') {
        return $html;
    }
    $abs = $appBase . 'assets/';
    $html = str_replace(array('"/assets/', "'/assets/"), array('"' . $abs, "'" . $abs), $html);
    // href="assets/... from CMS/meta breaks when the page URL contains index.php (→ index.php/assets/...).
    return preg_replace(
        '#(\b(?:href|src|content)\s*=\s*["\'])assets/#i',
        '$1' . $abs,
        $html
    );
}

/**
 * Fix common CMS typos: querySelector(#id) is invalid JS (# starts a private field); must be querySelector('#id').
 * Only runs inside &lt;script&gt; tags.
 *
 * @param string $html
 * @return string
 */
function webshop_fix_cms_script_hash_selectors($html)
{
    if (!is_string($html) || trim($html) === '') {
        return (string) $html;
    }
    return preg_replace_callback(
        '#<script\b[^>]*>[\s\S]*?</script>#i',
        function ($m) {
            $s = $m[0];
            $s = preg_replace_callback(
                '/\b(querySelector(?:All)?)\s*\(\s*#([a-zA-Z_][\w-]*)\s*\)/',
                function ($x) {
                    return $x[1] . "('#" . $x[2] . "')";
                },
                $s
            );
            $s = preg_replace_callback(
                '/\$\s*\(\s*#([a-zA-Z_][\w-]*)\s*\)/',
                function ($x) {
                    return "$('#" . $x[1] . "')";
                },
                $s
            );
            return $s;
        },
        $html
    );
}

/**
 * Normalize CMS HTML media links to the current uploads base.
 * Useful when API/CMS body contains hardcoded /assets/uploads/... URLs.
 *
 * @param string $html
 * @param string $uploads_base
 * @return string
 */
function webshop_normalize_html_media_urls($html, $uploads_base) {
        
    if (!is_string($html) || trim($html) === '') {
        return (string) $html;
    }
    $base = rtrim(str_replace('\\', '/', (string) $uploads_base), '/') . '/';
    $map_tail = function ($tail) use ($base) {
        $tail = function_exists('webshop_fixup_cms_media_relative_path')
            ? webshop_fixup_cms_media_relative_path($tail)
            : ltrim(str_replace('\\', '/', (string) $tail), '/');
        return $base . ltrim(str_replace('\\', '/', (string) $tail), '/');
    };

    $out = $html;
    $out = preg_replace_callback(
        '#https?://[^"\'\s)]+/assets/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );
    $out = preg_replace_callback(
        '#https?://[^"\'\s)]+/assets/mdata/[^/]+/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );
    // CMS rows saved with full ElintOm app path (e.g. http://localhost/ElintOm/assets/mdata/localhost/uploads/…).
    $out = preg_replace_callback(
        '#https?://[^"\'\s)]+/ElintOm/assets/mdata/[^/]+/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );
    $out = preg_replace_callback(
        '#(?<=["\'\(=])/?assets/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );
    $out = preg_replace_callback(
        '#(?<=["\'\(=])/?assets/mdata/[^/]+/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );

    // Add a safe fallback for broken CMS image URLs (missing files on disk).
    $fallback_src = htmlspecialchars(
        webshop_no_image_src($uploads_base, rtrim((string) $uploads_base, '/') . '/thumbs/'),
        ENT_QUOTES,
        'UTF-8'
    );
    $out = preg_replace_callback(
        '#<img\b[^>]*>#i',
        function ($m) use ($fallback_src) {
            $tag = $m[0];
            if (preg_match('/\bonerror\s*=/i', $tag)) {
                return $tag;
            }
            return preg_replace(
                '#\s*/?>$#',
                ' onerror="this.onerror=null;this.src=\'' . $fallback_src . '\';"$0',
                $tag
            );
        },
        $out
    );
    $out = webshop_rewrite_root_relative_asset_urls($out);
    return $out;
}

/**
 * True when API page body is the same markup already produced by render_components().
 *
 * @param string $pageBodyHtml
 * @param string $localBodyHtml
 * @return bool
 */
function webshop_cms_page_body_duplicates_section_render($pageBodyHtml, $localBodyHtml)
{
    $pageBodyHtml = trim((string) $pageBodyHtml);
    $localBodyHtml = trim((string) $localBodyHtml);
    if ($pageBodyHtml === '' || $localBodyHtml === '') {
        return false;
    }
    if ($pageBodyHtml === $localBodyHtml) {
        return true;
    }
    $flags = defined('ENT_HTML5') ? (ENT_QUOTES | ENT_HTML5) : ENT_QUOTES;
    $plainPage = trim(html_entity_decode(strip_tags($pageBodyHtml), $flags, 'UTF-8'));
    $plainLocal = trim(html_entity_decode(strip_tags($localBodyHtml), $flags, 'UTF-8'));
    if ($plainPage === '' || $plainLocal === '') {
        return false;
    }
    if ($plainPage === $plainLocal) {
        return true;
    }
    if (strpos($plainLocal, $plainPage) !== false) {
        return true;
    }
    return strpos($localBodyHtml, $pageBodyHtml) !== false;
}

/**
 * Merge optional page-level HTML with rendered CMS sections (no duplicate aggregate body).
 *
 * @param string $pageBodyHtml
 * @param string $localBodyHtml
 * @param array  $sections
 * @return string
 */
function webshop_compose_cms_body_html($pageBodyHtml, $localBodyHtml, $sections = array())
{
    $pageBodyHtml = trim((string) $pageBodyHtml);
    $localBodyHtml = trim((string) $localBodyHtml);
    if ($localBodyHtml !== '' && !empty($sections) && is_array($sections)
        && function_exists('webshop_cms_page_body_duplicates_section_render')
        && webshop_cms_page_body_duplicates_section_render($pageBodyHtml, $localBodyHtml)) {
        $pageBodyHtml = '';
    }
    $out = '';
    if ($pageBodyHtml !== '') {
        $out = $pageBodyHtml;
    }
    if ($localBodyHtml !== '') {
        $out .= ($out !== '' ? "\n" : '') . $localBodyHtml;
    }
    return $out;
}

function webshop_cms_html_content_redundant_with_title($content, $title)
{
    $title = trim((string) $title);
    if ($title === '') {
        return false;
    }
    $plain = trim(html_entity_decode(strip_tags((string) $content), ENT_QUOTES, 'UTF-8'));
    if ($plain === '') {
        return false;
    }
    return strcasecmp($plain, $title) === 0;
}

/**
 * Decode entity-encoded CMS HTML (common when JSON/API stores escaped tags) then normalize media URLs.
 *
 * @param string $html
 * @param string $uploads_base
 * @return string
 */
function webshop_prepare_cms_html_for_output($html, $uploads_base)
{
    if (!is_string($html) || trim($html) === '') {
        return '';
    }
    $s = $html;
    $flags = defined('ENT_HTML5') ? (ENT_QUOTES | ENT_HTML5) : (ENT_QUOTES | ENT_HTML401);
    for ($i = 0; $i < 4; $i++) {
        $next = html_entity_decode($s, $flags, 'UTF-8');
        if ($next === $s) {
            break;
        }
        $s = $next;
    }
    $s = webshop_normalize_html_media_urls($s, $uploads_base);
    return webshop_fix_cms_script_hash_selectors($s);
}

/**
 * Pull embedded &lt;style&gt; and stylesheet &lt;link&gt; tags out of CMS HTML so they can be placed in &lt;head&gt;
 * (or printed before the fragment). Also strips accidental full-document wrappers when admins paste HTML exports.
 *
 * @param string $html
 * @return array html, style_blocks, link_tags keys
 */
function webshop_extract_cms_embedded_assets($html)
{
    if (!is_string($html) || trim($html) === '') {
        return array(
            'html' => '',
            'style_blocks' => '',
            'link_tags' => '',
        );
    }
    $out = $html;
    $style_blocks = array();
    $link_tags = array();

    $out = preg_replace('#</head>\s*#i', '', $out);
    $out = preg_replace('#<head\b[^>]*>\s*#i', '', $out);
    $out = preg_replace('#<body\b[^>]*>\s*#i', '', $out);
    $out = preg_replace('#</body>\s*#i', '', $out);
    $out = preg_replace('#</html>\s*#i', '', $out);

    $out = preg_replace_callback('#<style\b[^>]*>[\s\S]*?</style>#i', function ($m) use (&$style_blocks) {
        $style_blocks[] = $m[0];
        return '';
    }, $out);

    $out = preg_replace_callback('#<link\b[^>]*>#i', function ($m) use (&$link_tags) {
        $tag = $m[0];
        if (preg_match('/\brel\s*=\s*["\']stylesheet["\']/i', $tag)) {
            $link_tags[] = $tag;
            return '';
        }
        return $tag;
    }, $out);

    return array(
        'html' => trim($out),
        'style_blocks' => implode("\n", $style_blocks),
        'link_tags' => implode("\n", $link_tags),
    );
}

/**
 * Currency symbol from POS/API settings ($this->data['Settings']).
 *
 * @param object|null $Settings
 * @return string
 */
function webshop_currency_symbol($Settings) {
    if (is_object($Settings) && isset($Settings->symbol) && trim((string) $Settings->symbol) !== '') {
        return (string) $Settings->symbol;
    }
    return '$';
}

/**
 * Format a positive storefront price using the same rules as Sma::formatMoney (symbol position,
 * Indian/SAC grouping when enabled, separators). Returns empty string when amount is not positive.
 *
 * @param float|int|string|null $amount
 * @param object|null           $Settings Passed through for fallback only; live requests use CI + sma.
 * @return string
 */
function webshop_price_display($amount, $Settings) {
    $n = isset($amount) ? (float) $amount : 0;
    if ($n <= 0) {
        return '';
    }
    $CI = function_exists('get_instance') ? get_instance() : null;
    if ($CI && isset($CI->sma) && is_object($CI->sma) && method_exists($CI->sma, 'formatMoney')) {
        return $CI->sma->formatMoney($n);
    }
    return webshop_price_display_fallback($n, $Settings);
}

/**
 * Mirrors Sma::formatMoney when the Sma library is unavailable (symbol prefix/suffix via display_symbol).
 *
 * @param float              $n
 * @param object|null        $Settings
 * @return string
 */
function webshop_price_display_fallback($n, $Settings) {
    $S = is_object($Settings) ? $Settings : new stdClass();
    $decimals = isset($S->decimals) ? max(0, min(8, (int) $S->decimals)) : 2;
    $ds = isset($S->decimals_sep) ? (string) $S->decimals_sep : '.';
    $ts_raw = isset($S->thousands_sep) ? (string) $S->thousands_sep : ',';
    $ts = ($ts_raw === '0') ? ' ' : $ts_raw;
    $sym = isset($S->symbol) && trim((string) $S->symbol) !== '' ? (string) $S->symbol : '$';
    $disp = isset($S->display_symbol) ? (int) $S->display_symbol : 1;
    $formatted = number_format((float) $n, $decimals, $ds, $ts);
    if ($disp === 0) {
        return $formatted;
    }
    if ($disp === 2) {
        return $formatted . $sym;
    }
    return $sym . $formatted;
}

/**
 * Pick catalogue unit price for checkout when eshop_price is empty/zero (use cart/MRP).
 */
function webshop_checkout_resolve_product_price(array $product, $cart_unit_price = 0, $cart_item_price = 0) {
    $price = isset($product['price']) ? (float) $product['price'] : 0;
    if ($price <= 0) {
        $price = (float) $cart_item_price;
    }
    if ($price <= 0) {
        $price = (float) $cart_unit_price;
    }
    if ($price <= 0) {
        foreach (array('eshop_price', 'unit_price', 'sale_price', 'mrp', 'regular_price') as $k) {
            if (!empty($product[$k]) && (float) $product[$k] > 0) {
                $price = (float) $product[$k];
                break;
            }
        }
    }
    if ($price > 0 && !empty($product['promo_price']) && (float) $product['promo_price'] > 0
        && (float) $product['promo_price'] < $price) {
        $price = (float) $product['promo_price'];
    } elseif ($price <= 0 && !empty($product['promo_price']) && (float) $product['promo_price'] > 0) {
        $price = (float) $product['promo_price'];
    }
    return $price;
}

/**
 * Normalize variant row keys from CMS / ElintOm API (absolute eshop price fields).
 *
 * @param array $variant
 * @return array
 */
function webshop_normalize_variant_row(array $variant) {
    $v = is_array($variant) ? $variant : (array) $variant;
    foreach (array(
        'eshop_price_including_tax', 'variant_eshop_price_including_tax',
        'eshop_price', 'variant_eshop_price',
    ) as $k) {
        if (isset($v[$k]) && $v[$k] !== '' && is_numeric($v[$k]) && (float) $v[$k] > 0) {
            if (!isset($v['eshop_price']) || (float) $v['eshop_price'] <= 0) {
                $v['eshop_price'] = (float) $v[$k];
            }
        }
    }
    foreach (array('variant_eshop_mrp', 'variant_mrp') as $k) {
        if (isset($v[$k]) && $v[$k] !== '' && is_numeric($v[$k]) && (float) $v[$k] > 0) {
            if (!isset($v['mrp']) || (float) $v['mrp'] <= 0) {
                $v['mrp'] = (float) $v[$k];
            }
        }
    }
    return $v;
}

/**
 * Decode API variant payloads (array, stdClass map, or JSON string).
 *
 * @param mixed $value
 * @return array
 */
function webshop_coerce_to_array($value) {
    if (is_array($value)) {
        return $value;
    }
    if (is_object($value)) {
        $decoded = json_decode(json_encode($value), true);
        return is_array($decoded) ? $decoded : array();
    }
    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return array();
}

/**
 * Numeric option / variant id from one variant row.
 *
 * @param array|object $variant
 * @return int
 */
function webshop_variant_row_id($variant, $map_key = '') {
    $v = is_array($variant) ? $variant : (array) $variant;
    foreach (array('id', 'option_id', 'variant_id', 'product_option_id', 'optionId') as $k) {
        if (isset($v[$k]) && $v[$k] !== '' && is_numeric($v[$k]) && (int) $v[$k] > 0) {
            return (int) $v[$k];
        }
    }
    if ($map_key !== '' && is_numeric($map_key) && (int) $map_key > 0) {
        return (int) $map_key;
    }
    return 0;
}

/**
 * Display label for a variant row (map keys such as "50Gram" count as name).
 *
 * @param array|object $variant
 * @param string       $map_key Associative key from variants map when present
 * @return string
 */
function webshop_variant_row_display_name($variant, $map_key = '') {
    $v = is_array($variant) ? $variant : (array) $variant;
    foreach (array('name', 'variant_name', 'option_name', 'title', 'label', 'value') as $k) {
        if (!empty($v[$k]) && trim((string) $v[$k]) !== '') {
            return trim((string) $v[$k]);
        }
    }
    if (is_string($map_key) && trim($map_key) !== '' && !is_numeric($map_key)) {
        return trim($map_key);
    }
    return '';
}

/**
 * Normalized list of variant rows for PLP/cart (handles stdClass maps from ElintOm API).
 *
 * @param array $product
 * @return array<int,array>
 */
function webshop_product_variants_from_row(array $product) {
    foreach (array('variants', 'product_variants', 'options', 'product_options') as $vk) {
        if (empty($product[$vk])) {
            continue;
        }
        $raw = webshop_coerce_to_array($product[$vk]);
        if ($raw === array()) {
            continue;
        }
        $list = array();
        foreach ($raw as $key => $row) {
            if (!is_array($row) && !is_object($row)) {
                continue;
            }
            $a = webshop_normalize_variant_row(is_array($row) ? $row : (array) $row);
            $vid = webshop_variant_row_id($a, is_string($key) || is_int($key) ? (string) $key : '');
            if ($vid < 1 && is_numeric($key) && (int) $key > 0) {
                $a['id'] = (int) $key;
                $vid = (int) $key;
            }
            $name = webshop_variant_row_display_name($a, is_string($key) ? $key : '');
            if ($name !== '' && (!isset($a['name']) || trim((string) $a['name']) === '')) {
                $a['name'] = $name;
            }
            if ($vid < 1) {
                continue;
            }
            if ($name === '') {
                $a['name'] = 'Variant #' . $vid;
            }
            $list[] = $a;
        }
        if (!empty($list)) {
            return $list;
        }
    }
    return array();
}

/**
 * Resolve order_items.option_id (product_variants.id) for checkout / ERP.
 *
 * @param array $product  Catalog row (ideally merged with resolve_product_row_by_id)
 * @param int   $option_id Known id from POST or session (0 if unknown)
 * @param array $hints     variant_id, variant_name, variant_price
 * @return int
 */
function webshop_resolve_line_option_id(array $product, $option_id = 0, array $hints = array()) {
    $oid = (int) $option_id;
    foreach (array('variant_id', 'option_id', 'product_option_id') as $k) {
        if ($oid > 0) {
            break;
        }
        if (!empty($hints[$k]) && (int) $hints[$k] > 0) {
            $oid = (int) $hints[$k];
        }
    }
    if ($oid > 0) {
        return $oid;
    }

    $variants = webshop_product_variants_from_row($product);
    if (empty($variants)) {
        return 0;
    }

    $nameHint = isset($hints['variant_name']) ? trim((string) $hints['variant_name']) : '';
    if ($nameHint !== '') {
        foreach ($variants as $v) {
            $vn = webshop_variant_row_display_name($v, '');
            if ($vn !== '' && strcasecmp($vn, $nameHint) === 0) {
                $vid = webshop_variant_row_id($v);
                if ($vid > 0) {
                    return $vid;
                }
            }
        }
    }

    $priceHint = isset($hints['variant_price']) ? (float) $hints['variant_price'] : 0.0;
    if ($priceHint > 0) {
        foreach ($variants as $v) {
            $priced = webshop_variant_pricing_for_line($product, $v, null);
            $vp = (float) $priced['variant_price'];
            $up = (float) $priced['unit_price'];
            if (abs($vp - $priceHint) < 0.02 || abs($up - $priceHint) < 0.02) {
                $vid = webshop_variant_row_id($v);
                if ($vid > 0) {
                    return $vid;
                }
            }
        }
    }

    if (count($variants) === 1) {
        $vid = webshop_variant_row_id($variants[0]);
        if ($vid > 0) {
            return $vid;
        }
    }

    $parentPrice = function_exists('webshop_product_effective_base_price')
        ? webshop_product_effective_base_price($product, $variants)
        : (isset($product['price']) ? (float) $product['price'] : 0.0);
    if ($parentPrice <= 0) {
        $vid = webshop_variant_row_id($variants[0]);
        if ($vid > 0) {
            return $vid;
        }
    }

    return 0;
}

/**
 * Default variant row when the client did not send variant_id (single-SKU / parent price 0).
 *
 * @param array $product
 * @param array $hints
 * @return array|null{id:int,name:string,variant_price:float,unit_quantity:float}
 */
function webshop_pick_default_variant_from_product(array $product, array $hints = array()) {
    $oid = webshop_resolve_line_option_id($product, 0, $hints);
    if ($oid < 1) {
        return null;
    }
    foreach (webshop_product_variants_from_row($product) as $v) {
        if (webshop_variant_row_id($v) !== $oid) {
            continue;
        }
        $priced = webshop_variant_pricing_for_line($product, $v, null);
        $uq = isset($v['unit_quantity']) ? (float) $v['unit_quantity'] : 1.0;
        if ($uq < 1) {
            $uq = 1.0;
        }
        return array(
            'id'            => $oid,
            'name'          => webshop_variant_row_display_name($v, ''),
            'variant_price' => (float) $priced['variant_price'],
            'unit_quantity' => $uq,
        );
    }
    return array(
        'id'            => $oid,
        'name'          => '',
        'variant_price' => isset($hints['variant_price']) ? (float) $hints['variant_price'] : 0.0,
        'unit_quantity' => 1.0,
    );
}

/**
 * Base product price for variant math (0 when CMS stores prices only on variants).
 *
 * @param array      $product
 * @param array|null $variants
 * @return float
 */
function webshop_product_effective_base_price(array $product, $variants = null) {
    $product = is_array($product) ? $product : (array) $product;
    if ($variants === null) {
        $variants = webshop_product_variants_from_row($product);
    }
    if (empty($variants) || !is_array($variants)) {
        return webshop_checkout_resolve_product_price($product, 0, 0);
    }
    if (isset($product['eshop_price']) && $product['eshop_price'] !== '' && is_numeric($product['eshop_price']) && (float) $product['eshop_price'] <= 0) {
        return 0.0;
    }
    foreach ($variants as $v) {
        $v = webshop_normalize_variant_row(is_array($v) ? $v : (array) $v);
        if (webshop_variant_row_absolute_eshop_price($v) !== null) {
            return 0.0;
        }
    }
    return webshop_checkout_resolve_product_price($product, 0, 0);
}

/**
 * @param array $variant
 * @return float
 */
function webshop_variant_row_price_delta(array $variant) {
    $v = webshop_normalize_variant_row(is_array($variant) ? $variant : (array) $variant);
    foreach (array('price', 'variant_price', 'option_price') as $k) {
        if (isset($v[$k]) && $v[$k] !== '' && is_numeric($v[$k])) {
            return (float) $v[$k];
        }
    }
    return 0.0;
}

/**
 * Absolute variant eshop sell price when CMS stores full price on the option row.
 *
 * @param array $variant
 * @return float|null
 */
function webshop_variant_row_absolute_eshop_price(array $variant) {
    $v = webshop_normalize_variant_row(is_array($variant) ? $variant : (array) $variant);
    foreach (array(
        'eshop_price_including_tax', 'variant_eshop_price_including_tax',
        'eshop_price', 'variant_eshop_price',
        'unit_price', 'sale_price', 'selling_price', 'final_price',
    ) as $k) {
        if (isset($v[$k]) && (float) $v[$k] > 0) {
            return (float) $v[$k];
        }
    }
    return null;
}

/**
 * @param array $variant
 * @param array $product
 * @return float
 */
function webshop_variant_row_mrp(array $variant, array $product = array()) {
    $v = webshop_normalize_variant_row(is_array($variant) ? $variant : (array) $variant);
    foreach (array('mrp', 'variant_mrp', 'variant_eshop_mrp') as $k) {
        if (isset($v[$k]) && (float) $v[$k] > 0) {
            return (float) $v[$k];
        }
    }
    return isset($product['mrp']) ? (float) $product['mrp'] : 0.0;
}

/**
 * Resolve sell price for one variant (CMS absolute eshop vs legacy base + delta).
 *
 * @param array       $product
 * @param array       $variant
 * @param object|null $Settings unused; reserved
 * @return array{unit_price:float,promo_price:float,net_unit_price:float,variant_price:float,display_mrp:float,discount_percent:int}
 */
function webshop_variant_pricing_for_line(array $product, array $variant, $Settings = null) {
    $product = is_array($product) ? $product : (array) $product;
    $variant = webshop_normalize_variant_row(is_array($variant) ? $variant : (array) $variant);
    $base = webshop_product_effective_base_price($product, webshop_product_variants_from_row($product));
    $delta = webshop_variant_row_price_delta($variant);
    $absolute = webshop_variant_row_absolute_eshop_price($variant);
    $combined = $base + $delta;
    $variantMrp = webshop_variant_row_mrp($variant, $product);

    $useAbsolute = false;
    if ($absolute !== null && $absolute > 0) {
        if ($base <= 0 || abs($absolute - $combined) > 0.009) {
            $useAbsolute = true;
        }
    } elseif ($base <= 0 && $delta > 0) {
        $useAbsolute = true;
        $absolute = $delta;
        $delta = 0.0;
    } elseif ($delta > 0 && $variantMrp > 0 && abs($delta - $variantMrp) < 0.01) {
        // CMS Products Price: variant.price equals variant MRP (full eshop price, not a delta).
        $useAbsolute = true;
        $absolute = $delta;
        $delta = 0.0;
    }

    if ($useAbsolute && $absolute !== null && $absolute > 0) {
        $work = $product;
        $work['price'] = $absolute;
        if ($variantMrp > 0) {
            $work['mrp'] = $variantMrp;
        }
        $priceData = product_sale_price($work, array(1 => 0.0));
        $unitPrice = isset($priceData['unit_price']) ? (float) $priceData['unit_price'] : (float) $absolute;
        $cartDelta = ($base <= 0) ? (float) $absolute : max(0.0, $unitPrice - $base);
        $displayMrp = $variantMrp;
        $discountPct = ($displayMrp > $unitPrice && $unitPrice > 0)
            ? (int) round((($displayMrp - $unitPrice) / $displayMrp) * 100)
            : 0;
        return array(
            'unit_price'         => $unitPrice,
            'promo_price'        => isset($priceData['promo_price']) ? (float) $priceData['promo_price'] : 0.0,
            'net_unit_price'     => isset($priceData['net_unit_price']) ? (float) $priceData['net_unit_price'] : $unitPrice,
            'variant_price'      => $cartDelta,
            'display_mrp'        => $displayMrp,
            'discount_percent'   => $discountPct,
        );
    }

    $work = $product;
    $work['price'] = $base;
    $priceData = product_sale_price($work, array(1 => $delta));
    $unitPrice = isset($priceData['unit_price']) ? (float) $priceData['unit_price'] : $combined;
    $baseMrp = isset($product['mrp']) ? (float) $product['mrp'] : 0.0;
    $displayMrp = ($variantMrp > 0) ? $variantMrp : (($baseMrp > 0) ? ($baseMrp + $delta) : 0.0);
    if ($displayMrp > 0 && $displayMrp <= $unitPrice) {
        $displayMrp = 0.0;
    }
    $discountPct = ($displayMrp > $unitPrice && $unitPrice > 0)
        ? (int) round((($displayMrp - $unitPrice) / $displayMrp) * 100)
        : 0;
    return array(
        'unit_price'         => $unitPrice,
        'promo_price'        => isset($priceData['promo_price']) ? (float) $priceData['promo_price'] : 0.0,
        'net_unit_price'     => isset($priceData['net_unit_price']) ? (float) $priceData['net_unit_price'] : $unitPrice,
        'variant_price'      => $delta,
        'display_mrp'        => $displayMrp,
        'discount_percent'   => $discountPct,
    );
}

/**
 * Variant price delta from a product row (base + delta = selling price).
 *
 * @param array $product
 * @param int   $variant_id
 * @return float|null null when variant not found on the row
 */
function webshop_variant_delta_from_product(array $product, $variant_id) {
    $vid = (int) $variant_id;
    if ($vid < 1) {
        return null;
    }
    foreach (array('variants', 'product_variants', 'options', 'product_options') as $vk) {
        if (empty($product[$vk]) || !is_array($product[$vk])) {
            continue;
        }
        foreach ($product[$vk] as $idx => $v) {
            $r = is_array($v) ? $v : (array) $v;
            $id = 0;
            foreach (array('id', 'variant_id', 'option_id', 'product_option_id') as $ok) {
                if (!empty($r[$ok]) && is_numeric($r[$ok])) {
                    $id = (int) $r[$ok];
                    break;
                }
            }
            if ($id < 1 && is_numeric($idx)) {
                $id = (int) $idx;
            }
            if ($id !== $vid) {
                continue;
            }
            if (function_exists('webshop_variant_pricing_for_line')) {
                $priced = webshop_variant_pricing_for_line($product, $r);
                return isset($priced['variant_price']) ? (float) $priced['variant_price'] : 0.0;
            }
            return webshop_variant_row_price_delta($r);
        }
    }
    return null;
}

/**
 * Authoritative unit price for a cart/checkout line (variant-aware).
 *
 * @param array    $product
 * @param int      $variant_id
 * @param float|null $fallback_delta Session/posted variant delta when catalogue row lacks variants
 * @param float    $cart_unit_price
 * @param float    $cart_item_price
 * @return array{unit_price:float,variant_price:float,promo_price:float,net_unit_price:float}
 */
function webshop_resolve_variant_line_price(array $product, $variant_id = 0, $fallback_delta = null, $cart_unit_price = 0, $cart_item_price = 0) {
    $vid = (int) $variant_id;
    $out = array(
        'unit_price'      => 0.0,
        'variant_price'   => 0.0,
        'promo_price'     => 0.0,
        'net_unit_price'  => 0.0,
    );
    if ($vid > 0 && !empty($product)) {
        foreach (array('variants', 'product_variants', 'options', 'product_options') as $vk) {
            if (empty($product[$vk]) || !is_array($product[$vk])) {
                continue;
            }
            foreach ($product[$vk] as $v) {
                $r = is_array($v) ? $v : (array) $v;
                $id = isset($r['id']) ? (int) $r['id'] : 0;
                if ($id !== $vid) {
                    continue;
                }
                $priced = webshop_variant_pricing_for_line($product, $r);
                $out['unit_price'] = (float) $priced['unit_price'];
                $out['net_unit_price'] = (float) $priced['net_unit_price'];
                $out['promo_price'] = (float) $priced['promo_price'];
                $out['variant_price'] = (float) $priced['variant_price'];
                if ($out['unit_price'] > 0) {
                    return $out;
                }
            }
        }
        $delta = webshop_variant_delta_from_product($product, $vid);
        if ($delta === null && $fallback_delta !== null) {
            $delta = (float) $fallback_delta;
        }
        if ($delta !== null) {
            $base = webshop_product_effective_base_price($product);
            $work = $product;
            $work['price'] = $base;
            $priceData = product_sale_price($work, array(1 => (float) $delta));
            $unit = isset($priceData['unit_price']) ? (float) $priceData['unit_price'] : 0.0;
            if ($unit > 0) {
                $out['unit_price'] = $unit;
                $out['net_unit_price'] = isset($priceData['net_unit_price']) ? (float) $priceData['net_unit_price'] : $unit;
                $out['promo_price'] = isset($priceData['promo_price']) ? (float) $priceData['promo_price'] : 0.0;
                $out['variant_price'] = (float) $delta;
                return $out;
            }
        }
    }
    if (!empty($product)) {
        $unit = webshop_checkout_resolve_product_price($product, $cart_unit_price, $cart_item_price);
        $out['unit_price'] = $unit;
        $out['net_unit_price'] = $unit;
        return $out;
    }
    if ((float) $cart_unit_price > 0) {
        $out['unit_price'] = (float) $cart_unit_price;
        $out['net_unit_price'] = (float) $cart_unit_price;
    } elseif ((float) $cart_item_price > 0) {
        $out['unit_price'] = (float) $cart_item_price;
        $out['net_unit_price'] = (float) $cart_item_price;
    }
    return $out;
}

/**
 * Refresh session cart line prices from catalogue (keeps variant deltas correct).
 *
 * @param array|null $products_map product_id => row from get_cart_data()
 * @return array|null Updated products map
 */
function webshop_enrich_cart_session_prices($products_map = null) {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || $_SESSION['cart'] === array()) {
        return is_array($products_map) ? $products_map : array();
    }
    $products_map = is_array($products_map) ? $products_map : array();
    $CI = function_exists('get_instance') ? get_instance() : null;
    $can_resolve = ($CI && isset($CI->webshop_model) && is_object($CI->webshop_model)
        && method_exists($CI->webshop_model, 'resolve_product_row_by_id'));

    foreach ($_SESSION['cart'] as $key => $line) {
        if (!is_array($line)) {
            continue;
        }
        $pid = isset($line['product_id']) ? (int) $line['product_id'] : 0;
        if ($pid < 1) {
            continue;
        }
        $vid = isset($line['variant_id']) ? (int) $line['variant_id'] : 0;
        $product = (isset($products_map[$pid]) && is_array($products_map[$pid])) ? $products_map[$pid] : array();

        if ($vid > 0) {
            $has_variants = false;
            foreach (array('variants', 'product_variants', 'options', 'product_options') as $vk) {
                if (!empty($product[$vk]) && is_array($product[$vk])) {
                    $has_variants = true;
                    break;
                }
            }
            if (!$has_variants && $can_resolve) {
                $full = $CI->webshop_model->resolve_product_row_by_id($pid);
                if (is_array($full) && !empty($full)) {
                    $product = array_merge($product, $full);
                    $products_map[$pid] = isset($products_map[$pid]) && is_array($products_map[$pid])
                        ? array_merge($products_map[$pid], $full)
                        : $full;
                }
            }
        }

        $fallback_delta = array_key_exists('variant_price', $line) ? (float) $line['variant_price'] : null;
        $cart_unit = isset($line['product_price']) ? (float) $line['product_price'] : 0.0;
        $cart_price = isset($line['price']) ? (float) $line['price'] : 0.0;
        $resolved = webshop_resolve_variant_line_price($product, $vid, $fallback_delta, $cart_unit, $cart_price);

        if ($resolved['unit_price'] > 0) {
            $_SESSION['cart'][$key]['product_price'] = $resolved['unit_price'];
            $_SESSION['cart'][$key]['price'] = $resolved['unit_price'];
            if ($vid > 0) {
                $_SESSION['cart'][$key]['variant_price'] = $resolved['variant_price'];
            }
            if ($resolved['promo_price'] > 0) {
                $_SESSION['cart'][$key]['promotion_price'] = $resolved['promo_price'];
            }
        }
    }

    return $products_map;
}

/**
 * Wishlist rows from API → product_id => list of option/variant ids.
 *
 * @param mixed $wishlist_rows array or list of objects from get_wishlist()
 * @return array<int,int[]>
 */
function webshop_build_wishlist_lookup($wishlist_rows) {
    $norm = webshop_wishlist_normalize_rows($wishlist_rows);
    return $norm['lookup'];
}

/**
 * One wishlist row per product_id (API may return duplicates).
 *
 * @param mixed $wishlist_rows
 * @return array{lines:array<int,array{product_id:int,option_id:int}>,lookup:array<int,int[]>,count:int,duplicates:array<int,array{product_id:int,option_id:int}>}
 */
function webshop_wishlist_normalize_rows($wishlist_rows) {
    $lines = array();
    $lookup = array();
    $duplicates = array();
    if (!is_array($wishlist_rows)) {
        return array(
            'lines'        => $lines,
            'lookup'       => $lookup,
            'count'        => 0,
            'duplicates'   => $duplicates,
        );
    }
    $seen_pid = array();
    foreach ($wishlist_rows as $row) {
        $a = is_object($row) ? (array) $row : (is_array($row) ? $row : array());
        $pid = isset($a['product_id']) ? (int) $a['product_id'] : 0;
        if ($pid < 1 && isset($a['id'])) {
            $pid = (int) $a['id'];
        }
        if ($pid < 1) {
            continue;
        }
        $oid = isset($a['option_id']) ? (int) $a['option_id'] : 0;
        if (isset($seen_pid[$pid])) {
            $duplicates[] = array(
                'product_id' => $pid,
                'option_id'  => $oid,
            );
            continue;
        }
        $seen_pid[$pid] = true;
        $lines[] = array(
            'product_id' => $pid,
            'option_id'  => $oid,
        );
        $lookup[$pid] = array($oid);
    }
    return array(
        'lines'        => $lines,
        'lookup'       => $lookup,
        'count'        => count($lines),
        'duplicates'   => $duplicates,
    );
}

/**
 * Whether product_id is already in the normalized wishlist lookup.
 *
 * @param array<int,int[]> $lookup
 * @param int              $product_id
 * @param int              $variant_id unused; product-level match for UI hearts
 * @return bool
 */
function webshop_wishlist_product_is_saved(array $lookup, $product_id, $variant_id = 0) {
    $pid = (int) $product_id;
    return $pid > 0 && isset($lookup[$pid]) && is_array($lookup[$pid]) && count($lookup[$pid]) > 0;
}

/**
 * Whether a product (and optional variant) is in the user's wishlist lookup.
 *
 * @param array<int,int[]> $lookup from webshop_build_wishlist_lookup()
 * @param int              $product_id
 * @param int              $variant_id 0 = any saved row for this product
 * @return bool
 */
function webshop_product_in_wishlist_lookup(array $lookup, $product_id, $variant_id = 0) {
    return webshop_wishlist_product_is_saved($lookup, $product_id, $variant_id);
}

/**
 * Numeric product id from a list/card API row.
 *
 * @param array|object $row
 * @return int
 */
function webshop_product_list_item_id($row) {
    $row = is_array($row) ? $row : (array) $row;
    foreach (array('id', 'product_id', 'item_id') as $key) {
        if (isset($row[$key]) && (int) $row[$key] > 0) {
            return (int) $row[$key];
        }
    }
    return 0;
}

/**
 * Whether the current storefront visitor has a valid customer session.
 *
 * @return bool
 */
function webshop_is_customer_logged_in() {
    $CI =& get_instance();
    if (!empty($CI->data['webshop_is_logged_in'])) {
        return true;
    }
    $ws = $CI->session->userdata('webshop');
    if (!$ws) {
        return false;
    }
    $uid = is_object($ws)
        ? (int) (isset($ws->user_id) ? $ws->user_id : 0)
        : (int) (isset($ws['user_id']) ? $ws['user_id'] : 0);
    if ($uid < 1) {
        return false;
    }
    if (is_object($ws)) {
        return !isset($ws->is_login) || !empty($ws->is_login);
    }
    return !isset($ws['is_login']) || !empty($ws['is_login']);
}

/**
 * Wishlist lookup map for PLP/PDP views (controller data or explicit override).
 *
 * @param array|null $from_view Optional array passed from load->view()
 * @return array<int,int[]>
 */
function webshop_view_wishlist_lookup($from_view = null) {
    if (is_array($from_view)) {
        return $from_view;
    }
    $CI =& get_instance();
    if (isset($CI->data['wishlist_lookup']) && is_array($CI->data['wishlist_lookup'])) {
        return $CI->data['wishlist_lookup'];
    }
    return array();
}

/**
 * Pricing + labels for one wishlist row (saved option_id + product catalogue row).
 *
 * @param array|object $product
 * @param int          $saved_variant_id option_id from wishlist API
 * @param object|null  $Settings
 * @return array{price:float,mrp:float,discount_percent:int,variant_id:int,variant_price:float,variant_unit_quantity:float,variant_name:string,price_from:bool}
 */
function webshop_wishlist_item_display($product, $saved_variant_id = 0, $Settings = null) {
    $product = is_array($product) ? $product : (array) $product;
    $vid = (int) $saved_variant_id;
    $out = array(
        'price'                 => 0.0,
        'mrp'                   => 0.0,
        'discount_percent'      => 0,
        'variant_id'            => $vid,
        'variant_price'         => 0.0,
        'variant_unit_quantity' => 1.0,
        'variant_name'          => '',
        'price_from'            => false,
        'price_min'             => 0.0,
        'price_max'             => 0.0,
    );

    if ($vid > 0) {
        foreach (webshop_product_variants_from_row($product) as $v) {
            if (webshop_variant_row_id($v) !== $vid) {
                continue;
            }
            $priced = webshop_variant_pricing_for_line($product, $v, $Settings);
            $out['price'] = (float) $priced['unit_price'];
            $out['mrp'] = (float) $priced['display_mrp'];
            $out['variant_price'] = (float) $priced['variant_price'];
            $out['variant_unit_quantity'] = isset($v['unit_quantity']) ? (float) $v['unit_quantity'] : 1.0;
            if ($out['variant_unit_quantity'] < 1) {
                $out['variant_unit_quantity'] = 1.0;
            }
            $out['variant_name'] = webshop_variant_row_display_name($v);
            $out['discount_percent'] = (int) $priced['discount_percent'];
            if ($out['price'] > 0) {
                return $out;
            }
        }
    }

    if (function_exists('webshop_product_list_card_pricing')) {
        $card = webshop_product_list_card_pricing($product, $Settings);
        $out['price'] = (float) $card['price'];
        $out['mrp'] = (float) $card['mrp'];
        $out['discount_percent'] = (int) $card['discount_percent'];
        $out['price_from'] = !empty($card['price_from']);
        if (!empty($card['has_variants'])) {
            if ($vid < 1) {
                $out['variant_id'] = (int) $card['variant_id'];
            }
            $out['variant_price'] = (float) $card['variant_price'];
            $out['variant_unit_quantity'] = (float) $card['variant_unit_quantity'];
            if ($out['variant_name'] === '') {
                $out['variant_name'] = (string) $card['variant_name'];
            }
            $out['price_min'] = isset($card['price_min']) ? (float) $card['price_min'] : $out['price'];
            $out['price_max'] = isset($card['price_max']) ? (float) $card['price_max'] : $out['price'];
        }
    } else {
        $out['price'] = isset($product['price']) ? (float) $product['price'] : 0.0;
        $out['mrp'] = isset($product['mrp']) ? (float) $product['mrp'] : 0.0;
    }

    if ($out['price'] <= 0) {
        $variants = webshop_product_variants_from_row($product);
        if (!empty($variants) && function_exists('webshop_product_detail_variants_ui')) {
            $ui = webshop_product_detail_variants_ui($product, $variants, $Settings);
            if (!empty($ui['has_variants']) && !empty($ui['default'])) {
                $d = $ui['default'];
                $out['price'] = (float) $d['unit_price'];
                $out['variant_id'] = (int) $d['id'];
                $out['variant_price'] = (float) $d['variant_price'];
                $out['variant_unit_quantity'] = (float) $d['unit_quantity'];
                $out['variant_name'] = (string) $d['name'];
                $out['discount_percent'] = (int) $d['discount_percent'];
                if (!empty($d['display_mrp']) && (float) $d['display_mrp'] > 0) {
                    $out['mrp'] = (float) $d['display_mrp'];
                }
                if (!empty($ui['items']) && count($ui['items']) > 1) {
                    $prices = array();
                    foreach ($ui['items'] as $row) {
                        $prices[] = (float) $row['unit_price'];
                    }
                    $out['price_min'] = min($prices);
                    $out['price_max'] = max($prices);
                    if ($out['price_max'] > $out['price_min']) {
                        $out['price_from'] = true;
                    }
                }
            }
        }
    }

    return $out;
}

/**
 * Build variant_id => { id, name, product_id } from cart catalogue rows.
 *
 * @param array<int,array> $products_map
 * @return array<int,array{id:int,name:string,product_id:int}>
 */
function webshop_cart_variants_map_from_products(array $products_map) {
    $map = array();
    foreach ($products_map as $pid => $row) {
        if (!is_array($row)) {
            continue;
        }
        $pid = (int) $pid;
        foreach (webshop_product_variants_from_row($row) as $v) {
            $r = webshop_normalize_variant_row(is_array($v) ? $v : (array) $v);
            $vid = isset($r['id']) ? (int) $r['id'] : 0;
            if ($vid > 0 && !empty($r['name'])) {
                $map[$vid] = array(
                    'id'         => $vid,
                    'name'       => trim((string) $r['name']),
                    'product_id' => $pid,
                );
            }
        }
    }
    return $map;
}

/**
 * Human-readable variant label for one cart line.
 *
 * @param array      $item
 * @param array      $product
 * @param array<int,array> $variants_map optional from webshop_cart_variants_map_from_products()
 * @return string
 */
function webshop_cart_line_variant_label(array $item, array $product = array(), array $variants_map = array()) {
    $vid = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;
    if ($vid < 1) {
        return '';
    }
    if (!empty($item['variant_name'])) {
        $n = trim((string) $item['variant_name']);
        if ($n !== '') {
            return $n;
        }
    }
    if (isset($variants_map[$vid]['name'])) {
        $n = trim((string) $variants_map[$vid]['name']);
        if ($n !== '') {
            return $n;
        }
    }
    $product = is_array($product) ? $product : array();
    foreach (webshop_product_variants_from_row($product) as $v) {
        $r = webshop_normalize_variant_row(is_array($v) ? $v : (array) $v);
        if ((isset($r['id']) ? (int) $r['id'] : 0) === $vid && !empty($r['name'])) {
            return trim((string) $r['name']);
        }
    }
    $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
    if ($pid < 1 && !empty($product['id'])) {
        $pid = (int) $product['id'];
    }
    if ($pid > 0) {
        $CI = function_exists('get_instance') ? get_instance() : null;
        if ($CI && isset($CI->webshop_model) && is_object($CI->webshop_model)
            && method_exists($CI->webshop_model, 'resolve_product_row_by_id')) {
            $full = $CI->webshop_model->resolve_product_row_by_id($pid);
            if (is_array($full)) {
                foreach (webshop_product_variants_from_row($full) as $v) {
                    $r = webshop_normalize_variant_row(is_array($v) ? $v : (array) $v);
                    if ((isset($r['id']) ? (int) $r['id'] : 0) === $vid && !empty($r['name'])) {
                        return trim((string) $r['name']);
                    }
                }
            }
        }
    }
    return '';
}

/**
 * Resolve variant names for session cart lines; merge variant rows into products map.
 *
 * @param array<int,array> $products_map
 * @return array<int,array>
 */
function webshop_enrich_cart_session_variant_labels(array $products_map = array()) {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || $_SESSION['cart'] === array()) {
        return is_array($products_map) ? $products_map : array();
    }
    $products_map = is_array($products_map) ? $products_map : array();
    $CI = function_exists('get_instance') ? get_instance() : null;
    $can_resolve = ($CI && isset($CI->webshop_model) && is_object($CI->webshop_model)
        && method_exists($CI->webshop_model, 'resolve_product_row_by_id'));

    $resolve_pids = array();
    foreach ($_SESSION['cart'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $vid = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;
        if ($vid < 1) {
            continue;
        }
        $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        if ($pid < 1) {
            continue;
        }
        $p = isset($products_map[$pid]) && is_array($products_map[$pid]) ? $products_map[$pid] : array();
        if (webshop_cart_line_variant_label($item, $p, array()) === '' && $can_resolve) {
            $resolve_pids[$pid] = $pid;
        }
    }
    foreach ($resolve_pids as $pid) {
        $full = $CI->webshop_model->resolve_product_row_by_id($pid);
        if (is_array($full) && !empty($full)) {
            $products_map[$pid] = isset($products_map[$pid]) && is_array($products_map[$pid])
                ? array_merge($products_map[$pid], $full)
                : $full;
        }
    }

    $variants_map = webshop_cart_variants_map_from_products($products_map);
    foreach ($_SESSION['cart'] as $key => $item) {
        if (!is_array($item)) {
            continue;
        }
        $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $p = ($pid > 0 && isset($products_map[$pid]) && is_array($products_map[$pid])) ? $products_map[$pid] : array();
        $label = webshop_cart_line_variant_label($item, $p, $variants_map);
        if ($label !== '') {
            $_SESSION['cart'][$key]['variant_name'] = $label;
        }
    }

    return $products_map;
}

/**
 * Normalize CMS page tag rows (page_tag_mapping) for Webshop_meta_engine.
 *
 * @param mixed $rows
 * @return array<int,array{property_name:string,value:string}>
 */
function webshop_normalize_cms_meta_rows($rows) {
    if (!is_array($rows) || $rows === array()) {
        return array();
    }
    $out = array();
    foreach ($rows as $tag) {
        $a = is_object($tag) ? (array) $tag : (is_array($tag) ? $tag : array());
        $value = isset($a['value']) ? trim((string) $a['value']) : '';
        if ($value === '') {
            continue;
        }
        $property = isset($a['property_name']) ? trim((string) $a['property_name']) : '';
        if ($property === '' && isset($a['tag_name'])) {
            $property = trim((string) $a['tag_name']);
        }
        $property = strtolower(str_replace(array(' ', '-'), '_', $property));
        if ($property === '') {
            continue;
        }
        $out[] = array(
            'property_name' => $property,
            'value' => $value,
        );
    }
    return $out;
}

/**
 * Build <head> meta HTML from CMS "Tag Values by Category" rows (meta_tags_raw).
 *
 * @param mixed $rows
 * @param array $context page_title, base_url, site_name, current_url
 * @return string
 */
function webshop_meta_tags_html_from_cms_rows($rows, array $context = array()) {
    $normalized = webshop_normalize_cms_meta_rows($rows);
    if ($normalized === array()) {
        return '';
    }
    $CI =& get_instance();
    $CI->load->library('webshop_meta_engine');
    return (string) $CI->webshop_meta_engine->render_meta_html($normalized, $context);
}

/**
 * Cart packs ordered (ElintOm stores this in order_items.unit_quantity; quantity is stock units).
 */
function webshop_order_line_customer_qty(array $item) {
    if (isset($item['unit_quantity']) && (float) $item['unit_quantity'] > 0) {
        return (float) $item['unit_quantity'];
    }
    $qty = isset($item['quantity']) ? (float) $item['quantity'] : 1;
    return $qty > 0 ? $qty : 1;
}

/**
 * Sale price breakdown for submit_order: one cart pack price (never base + variant twice).
 *
 * @param array      $product
 * @param int        $option_id
 * @param float      $option_price Posted variant delta hint
 * @param float      $cart_unit_price
 * @param float      $cart_item_price
 * @param array|null $sess_line $_SESSION['cart'] line
 * @return array Same shape as product_sale_price_webshop()
 */
function webshop_submit_order_line_sale_price(array $product, $option_id, $option_price, $cart_unit_price, $cart_item_price, $sess_line = null) {
    $option_id = (int) $option_id;
    $hints_delta = $option_price > 0 ? (float) $option_price : null;
    $resolved = webshop_resolve_variant_line_price(
        $product,
        $option_id,
        $hints_delta,
        (float) $cart_unit_price,
        (float) $cart_item_price
    );

    $pack_unit = (float) (isset($resolved['net_unit_price']) && (float) $resolved['net_unit_price'] > 0
        ? $resolved['net_unit_price']
        : (isset($resolved['unit_price']) ? $resolved['unit_price'] : 0));

    if (is_array($sess_line)) {
        foreach (array('product_price', 'price') as $ck) {
            if (isset($sess_line[$ck]) && (float) $sess_line[$ck] > 0) {
                $pack_unit = (float) $sess_line[$ck];
                break;
            }
        }
    }
    if ($pack_unit <= 0) {
        $pack_unit = webshop_checkout_resolve_product_price($product, (float) $cart_unit_price, (float) $cart_item_price);
    }

    $work = $product;
    $work['price'] = $pack_unit;
    return product_sale_price_webshop($work, array('1' => 0.0), null, 1);
}

/**
 * Unit price for an order line (handles legacy rows with unit_price=0 but net_price/MRP set).
 */
function webshop_order_item_unit_price(array $item) {
    $pack_qty = webshop_order_line_customer_qty($item);
    $stock_qty = isset($item['quantity']) ? (float) $item['quantity'] : 0;

    if ($pack_qty > 0 && $stock_qty > $pack_qty + 0.0001) {
        if (isset($item['subtotal']) && (float) $item['subtotal'] > 0) {
            $stored = (float) $item['subtotal'];
            $per_stock = $stored / $stock_qty;
            foreach (array('net_unit_price', 'unit_price', 'invoice_unit_price', 'real_unit_price') as $k) {
                if (!isset($item[$k]) || (float) $item[$k] <= 0) {
                    continue;
                }
                $u = (float) $item[$k];
                if (abs($u - ($stored / $pack_qty)) < 0.05 && $u > ($per_stock * 1.99)) {
                    return $per_stock;
                }
                if (abs($stored - ($u * $pack_qty)) < 0.05) {
                    return $u;
                }
                if (abs($stored - ($u * $stock_qty)) < 0.05) {
                    return $per_stock;
                }
            }
            return $per_stock;
        }
        if (isset($item['net_price']) && (float) $item['net_price'] > 0) {
            return (float) $item['net_price'] / $pack_qty;
        }
    }

    foreach (array('unit_price', 'net_unit_price', 'invoice_unit_price', 'real_unit_price') as $k) {
        if (isset($item[$k]) && (float) $item[$k] > 0) {
            $unit = (float) $item[$k];
            if ($pack_qty > 0 && $stock_qty > $pack_qty + 0.0001 && isset($item['subtotal']) && (float) $item['subtotal'] > 0) {
                $stored = (float) $item['subtotal'];
                if (abs($stored - ($unit * $stock_qty)) < 0.05 && abs($stored - ($unit * $pack_qty)) > 0.05) {
                    return $stored / $stock_qty;
                }
            }
            return $unit;
        }
    }
    if (isset($item['subtotal']) && (float) $item['subtotal'] > 0 && $pack_qty > 0) {
        return (float) $item['subtotal'] / $pack_qty;
    }
    if (isset($item['net_price']) && (float) $item['net_price'] > 0) {
        if ($stock_qty > $pack_qty && $pack_qty > 0) {
            return (float) $item['net_price'] / $pack_qty;
        }
        if ($stock_qty > 0) {
            return (float) $item['net_price'] / $stock_qty;
        }
        return (float) $item['net_price'] / $pack_qty;
    }
    if (isset($item['mrp']) && (float) $item['mrp'] > 0) {
        return (float) $item['mrp'];
    }
    if (isset($item['price']) && (float) $item['price'] > 0) {
        return (float) $item['price'];
    }
    return 0;
}

function webshop_order_item_line_total(array $item) {
    $pack_qty = webshop_order_line_customer_qty($item);
    $unit = webshop_order_item_unit_price($item);
    $expected = $unit > 0 ? $unit * $pack_qty : 0;

    if (isset($item['subtotal']) && (float) $item['subtotal'] > 0) {
        $stored = (float) $item['subtotal'];
        $stock_qty = isset($item['quantity']) ? (float) $item['quantity'] : 0;
        // Legacy rows billed net_unit_price × stock quantity (cart × variant unit_quantity).
        if ($stock_qty > $pack_qty && $unit > 0 && abs($stored - ($unit * $stock_qty)) < 0.05) {
            return $expected > 0 ? $expected : $stored;
        }
        if ($stock_qty > $pack_qty && $pack_qty > 0 && abs($stored - ($unit * $pack_qty)) > 0.05) {
            $scaled = $stored * ($pack_qty / $stock_qty);
            if ($expected > 0 && abs($scaled - $expected) < 0.05) {
                return $expected;
            }
            if ($expected > 0 && $scaled > $expected + 0.05) {
                return $expected;
            }
        }
        return $stored;
    }
    if (isset($item['net_price']) && (float) $item['net_price'] > 0) {
        $stock_qty = isset($item['quantity']) ? (float) $item['quantity'] : 0;
        if ($stock_qty > $pack_qty && $pack_qty > 0) {
            return (float) $item['net_price'] / $stock_qty * $pack_qty;
        }
        return (float) $item['net_price'];
    }
    return $expected;
}

/**
 * Cart line total shown on checkout (unit price × cart quantity, not × variant unit_quantity).
 */
function webshop_cart_line_display_total(array $line) {
    $qty = isset($line['quantity']) ? (float) $line['quantity'] : 1;
    if ($qty <= 0) {
        $qty = 1;
    }
    $unit = 0.0;
    foreach (array('product_price', 'price') as $k) {
        if (isset($line[$k]) && (float) $line[$k] > 0) {
            $unit = (float) $line[$k];
            break;
        }
    }
    if ($unit <= 0 && function_exists('webshop_order_item_unit_price')) {
        $unit = webshop_order_item_unit_price($line);
    }
    return $unit * $qty;
}

/**
 * Normalize order + line rows when unit_price/subtotal were stored as 0 (eshop_price missing).
 *
 * @return array{order: array, items: array}
 */
/**
 * Ensure order header flags required for ElintOm E-shop list + admin new-order alert.
 *
 * @param array $order
 * @return array
 */
function webshop_normalize_order_for_elintom(array $order) {
    $order['eshop_sale'] = 1;
    if (!isset($order['eshop_order_alert_status']) || $order['eshop_order_alert_status'] === '') {
        $order['eshop_order_alert_status'] = 0;
    }
    return $order;
}

function webshop_normalize_order_payload(array $order, array $items) {
    $line_sum = 0;
    foreach ($items as $idx => $item) {
        $row = is_array($item) ? $item : (array) $item;
        $unit = webshop_order_item_unit_price($row);
        $line = webshop_order_item_line_total($row);
        if ($unit > 0) {
            $row['unit_price'] = $unit;
            $row['net_unit_price'] = isset($row['net_unit_price']) && (float) $row['net_unit_price'] > 0
                ? (float) $row['net_unit_price'] : $unit;
        }
        if ($line > 0) {
            $row['subtotal'] = $line;
        }
        if (function_exists('webshop_sanitize_order_line_for_elintom')) {
            $row = webshop_sanitize_order_line_for_elintom($row);
        }
        $items[$idx] = $row;
        $line_sum += $line;
    }

    if ($line_sum > 0) {
        if (!isset($order['total']) || (float) $order['total'] <= 0) {
            $order['total'] = $line_sum;
        }
        if (!isset($order['grand_total']) || (float) $order['grand_total'] <= 0) {
            $shipping = isset($order['shipping']) ? (float) $order['shipping'] : 0;
            $tax = isset($order['total_tax']) ? (float) $order['total_tax'] : 0;
            if (isset($order['product_tax']) && (float) $order['product_tax'] > 0) {
                $tax = (float) $order['product_tax'];
            }
            $order['grand_total'] = $line_sum + $shipping + $tax;
        }
    }

    return array('order' => $order, 'items' => $items);
}

/**
 * Allowed keys for ElintOm addorder line JSON (must match sma_order_items columns).
 *
 * @return array<int,string>
 */
function webshop_elintom_order_item_allowed_keys() {
    return array(
        'product_id', 'product_code', 'article_code', 'product_name', 'product_type',
        'option_id', 'net_unit_price', 'unit_discount', 'unit_tax', 'invoice_unit_price',
        'invoice_net_unit_price', 'unit_price', 'quantity', 'net_price', 'invoice_total_net_unit_price',
        'warehouse_id', 'item_tax', 'tax_method', 'tax_rate_id', 'tax', 'discount', 'item_discount',
        'subtotal', 'real_unit_price', 'product_unit_id', 'product_unit_code', 'unit_quantity',
        'mrp', 'hsn_code', 'note', 'delivery_status', 'pending_quantity', 'delivered_quantity',
        'gst_rate', 'cgst', 'sgst', 'igst', 'item_weight',
    );
}

/**
 * Strip storefront-only keys from a line before JSON post to ElintOm addorder.
 * Only whitelisted columns are kept (variant_price / variant_id never sent).
 *
 * @param array $row
 * @return array
 */
function webshop_sanitize_order_line_for_elintom(array $row) {
    $src = is_array($row) ? $row : (array) $row;
    $flat = array();
    foreach ($src as $k => $v) {
        if (!is_string($k) || $k === '' || is_array($v) || is_object($v)) {
            continue;
        }
        $flat[$k] = $v;
    }
    $allowed = array_flip(webshop_elintom_order_item_allowed_keys());
    $row = array();
    foreach ($flat as $k => $v) {
        if (isset($allowed[$k])) {
            $row[$k] = $v;
        }
    }
    $pid = isset($row['product_id']) ? (int) $row['product_id'] : 0;
    if ($pid < 1) {
        return array();
    }
    $row['product_id'] = $pid;
    $oid = isset($row['option_id']) ? (int) $row['option_id'] : 0;
    if ($oid > 0) {
        $row['option_id'] = $oid;
    } else {
        unset($row['option_id']);
    }
    if (isset($row['quantity'])) {
        $row['quantity'] = (float) $row['quantity'];
        if ($row['quantity'] <= 0) {
            $row['quantity'] = 1.0;
        }
    }
    foreach (array('unit_price', 'net_unit_price', 'subtotal', 'item_tax', 'item_discount', 'unit_tax', 'unit_discount', 'mrp') as $nk) {
        if (isset($row[$nk]) && $row[$nk] !== '' && is_numeric($row[$nk])) {
            $row[$nk] = (float) $row[$nk];
        }
    }
    if ((!isset($row['subtotal']) || (float) $row['subtotal'] <= 0) && isset($row['net_unit_price'])) {
        $bill_qty = function_exists('webshop_order_line_customer_qty')
            ? webshop_order_line_customer_qty($row)
            : (isset($row['quantity']) ? (float) $row['quantity'] : 1.0);
        if ($bill_qty <= 0) {
            $bill_qty = 1.0;
        }
        $row['subtotal'] = (float) $row['net_unit_price'] * $bill_qty;
    }
    if ((!isset($row['unit_price']) || (float) $row['unit_price'] <= 0) && isset($row['net_unit_price'])) {
        $row['unit_price'] = (float) $row['net_unit_price'];
    }
    return $row;
}

/**
 * Normalize checkout line rows before ElintOm addorder API (option_id + variant_price).
 *
 * ElintOm create_order() reads order_items.option_id (product_variants.id). The storefront
 * must send a positive integer for variant SKUs; 0 when the line is the parent product only.
 *
 * @param array      $items       Line rows from submit_order()
 * @param array|null $session_cart Optional $_SESSION['cart'] keyed by cart line id
 * @return array
 */
function webshop_prepare_order_lines_for_elintom(array $items, $session_cart = null) {
    $out = array();
    $cart = is_array($session_cart) ? $session_cart : array();

    foreach ($items as $idx => $item) {
        $row = is_array($item) ? $item : (array) $item;
        $pid = isset($row['product_id']) ? (int) $row['product_id'] : 0;
        if ($pid < 1) {
            continue;
        }

        $oid = 0;
        foreach (array('option_id', 'variant_id', 'product_option_id') as $k) {
            if (isset($row[$k]) && (int) $row[$k] > 0) {
                $oid = (int) $row[$k];
                break;
            }
        }

        $sess_key = is_string($idx) ? $idx : null;
        if ($sess_key !== null && isset($cart[$sess_key]) && is_array($cart[$sess_key])) {
            if ($oid <= 0 && !empty($cart[$sess_key]['variant_id'])) {
                $oid = (int) $cart[$sess_key]['variant_id'];
            }
            if ($oid <= 0 && function_exists('webshop_resolve_line_option_id')) {
                $hints = array();
                if (!empty($cart[$sess_key]['variant_name'])) {
                    $hints['variant_name'] = $cart[$sess_key]['variant_name'];
                }
                if (isset($cart[$sess_key]['variant_price'])) {
                    $hints['variant_price'] = (float) $cart[$sess_key]['variant_price'];
                }
                $CI = function_exists('get_instance') ? get_instance() : null;
                if ($CI && isset($CI->webshop_model) && method_exists($CI->webshop_model, 'resolve_product_row_by_id')) {
                    $full = $CI->webshop_model->resolve_product_row_by_id($pid);
                    if (is_array($full) && !empty($full)) {
                        $oid = webshop_resolve_line_option_id($full, 0, $hints);
                    }
                }
            }
        } elseif (!empty($cart)) {
            foreach ($cart as $cline) {
                if (!is_array($cline) || (int) (isset($cline['product_id']) ? $cline['product_id'] : 0) !== $pid) {
                    continue;
                }
                $cvid = isset($cline['variant_id']) ? (int) $cline['variant_id'] : 0;
                if ($oid > 0 && $cvid !== $oid) {
                    continue;
                }
                if ($oid <= 0 && $cvid > 0) {
                    $oid = $cvid;
                }
                break;
            }
        }

        $vprice = 0.0;
        if (isset($row['variant_price'])) {
            $vprice = (float) $row['variant_price'];
        }
        if ($vprice <= 0 && $sess_key !== null && isset($cart[$sess_key]['variant_price'])) {
            $vprice = (float) $cart[$sess_key]['variant_price'];
        } elseif ($vprice <= 0 && !empty($cart)) {
            foreach ($cart as $cline) {
                if (!is_array($cline) || (int) (isset($cline['product_id']) ? $cline['product_id'] : 0) !== $pid) {
                    continue;
                }
                $cvid = isset($cline['variant_id']) ? (int) $cline['variant_id'] : 0;
                if ($oid > 0 && $cvid !== $oid) {
                    continue;
                }
                if (isset($cline['variant_price'])) {
                    $vprice = (float) $cline['variant_price'];
                }
                break;
            }
        }

        $row['product_id'] = $pid;
        $row['option_id'] = $oid;
        unset($row['variant_id'], $row['variant_price'], $row['product_option_id']);
        $row = function_exists('webshop_sanitize_order_line_for_elintom')
            ? webshop_sanitize_order_line_for_elintom($row)
            : $row;
        if (empty($row) || (int) (isset($row['product_id']) ? $row['product_id'] : 0) < 1) {
            continue;
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * Snapshot checkout totals for order_success (avoids legacy API rows doubling variant qty).
 *
 * @param array $order
 * @param array $products Line rows from submit_order / add_order
 * @param int   $order_id
 * @return array{order: array, items: array}
 */
function webshop_build_order_success_flash(array $order, array $products, $order_id = 0) {
    $items = array();
    foreach ($products as $p) {
        $row = is_array($p) ? $p : (array) $p;
        $pack_qty = function_exists('webshop_order_line_customer_qty')
            ? webshop_order_line_customer_qty($row)
            : (isset($row['unit_quantity']) ? (float) $row['unit_quantity'] : 1.0);
        if ($pack_qty <= 0) {
            $pack_qty = 1.0;
        }
        $name = isset($row['product_name']) ? (string) $row['product_name'] : '';
        $subtotal = isset($row['subtotal']) ? (float) $row['subtotal'] : 0.0;
        if ($subtotal <= 0 && isset($row['net_unit_price'])) {
            $subtotal = (float) $row['net_unit_price'] * $pack_qty;
        }
        $items[] = array(
            'name' => $name,
            'product_name' => $name,
            'unit_quantity' => $pack_qty,
            'quantity' => isset($row['quantity']) ? (float) $row['quantity'] : $pack_qty,
            'subtotal' => $subtotal,
            'net_unit_price' => isset($row['net_unit_price']) ? (float) $row['net_unit_price'] : 0.0,
            'unit_price' => isset($row['unit_price']) ? (float) $row['unit_price'] : 0.0,
        );
    }
    return array(
        'order' => array(
            'id' => (int) $order_id,
            'reference_no' => isset($order['reference_no']) ? (string) $order['reference_no'] : '',
            'grand_total' => isset($order['grand_total']) ? (float) $order['grand_total'] : 0.0,
        ),
        'items' => $items,
    );
}

function webshop_order_grand_total_amount(array $order, array $items = array()) {
    if (isset($order['grand_total']) && (float) $order['grand_total'] > 0) {
        return (float) $order['grand_total'];
    }
    if (isset($order['total']) && (float) $order['total'] > 0) {
        $shipping = isset($order['shipping']) ? (float) $order['shipping'] : 0;
        $tax = isset($order['total_tax']) ? (float) $order['total_tax'] : 0;
        if (isset($order['product_tax']) && (float) $order['product_tax'] > 0) {
            $tax = (float) $order['product_tax'];
        }
        return (float) $order['total'] + $tax + $shipping;
    }
    $sum = 0;
    foreach ($items as $item) {
        $sum += webshop_order_item_line_total(is_array($item) ? $item : (array) $item);
    }
    if ($sum > 0) {
        $shipping = isset($order['shipping']) ? (float) $order['shipping'] : 0;
        return $sum + $shipping;
    }
    return 0;
}

if (!function_exists('webshop_country_row_dial_code')) {
    /**
     * Extract numeric dial code from a country row (API uses `code` e.g. +91; not always `phone_code`).
     *
     * @param array|object $row
     * @return string Digits only, e.g. 91
     */
    function webshop_country_row_dial_code($row) {
        $a = is_object($row) ? (array) $row : (is_array($row) ? $row : array());
        foreach (array('code', 'phone_code', 'country_code', 'dial_code') as $k) {
            if (empty($a[$k])) {
                continue;
            }
            $digits = preg_replace('/\D/', '', (string) $a[$k]);
            if ($digits !== '') {
                return $digits;
            }
        }
        return '';
    }
}

if (!function_exists('webshop_settings_default_country_key')) {
    /**
     * POS default country from getsettings ($Settings): name, id, or label.
     *
     * @param object|null $settings
     * @return string
     */
    function webshop_settings_default_country_key($settings = null) {
        if ($settings === null && function_exists('get_instance')) {
            $CI = get_instance();
            $settings = (isset($CI->Settings) && is_object($CI->Settings)) ? $CI->Settings : null;
        }
        if (!is_object($settings)) {
            return '';
        }
        foreach (array('country', 'default_country', 'country_name') as $k) {
            if (!empty($settings->$k)) {
                return trim((string) $settings->$k);
            }
        }
        return '';
    }
}

if (!function_exists('webshop_settings_phone_dial_code')) {
    /**
     * Dial prefix for storefront forms (forgot password, etc.) from ElintOm country + countries list.
     *
     * @param mixed $countries_list Optional preloaded getCountry() rows
     * @return string Digits only (no +), e.g. 91 for India
     */
    function webshop_settings_phone_dial_code($countries_list = null) {
        $CI = function_exists('get_instance') ? get_instance() : null;
        $key = webshop_settings_default_country_key();
        $key_lc = strtolower($key);

        $name_to_dial = array(
            'india' => '91',
            'oman' => '968',
            'united arab emirates' => '971',
            'uae' => '971',
            'saudi arabia' => '966',
        );
        if ($key_lc !== '' && isset($name_to_dial[$key_lc])) {
            return $name_to_dial[$key_lc];
        }

        if ($countries_list === null && $CI && isset($CI->webshop_model) && method_exists($CI->webshop_model, 'getCountry')) {
            try {
                $countries_list = $CI->webshop_model->getCountry();
            } catch (Exception $e) {
                $countries_list = array();
            }
        }

        if (is_array($countries_list) && !empty($countries_list)) {
            foreach ($countries_list as $c) {
                $a = is_object($c) ? (array) $c : (is_array($c) ? $c : array());
                $id = isset($a['id']) ? trim((string) $a['id']) : (isset($a['country_id']) ? trim((string) $a['country_id']) : '');
                $name = isset($a['name']) ? trim((string) $a['name']) : (isset($a['country_name']) ? trim((string) $a['country_name']) : '');
                $match = false;
                if ($key !== '') {
                    if ($id !== '' && $key === $id) {
                        $match = true;
                    } elseif ($name !== '' && strcasecmp($name, $key) === 0) {
                        $match = true;
                    }
                }
                if (!$match) {
                    continue;
                }
                $dial = webshop_country_row_dial_code($c);
                if ($dial !== '') {
                    return $dial;
                }
            }
        }

        if ($CI && isset($CI->Settings) && is_object($CI->Settings) && !empty($CI->Settings->timezone)) {
            $tz = strtolower((string) $CI->Settings->timezone);
            if (strpos($tz, 'kolkata') !== false || strpos($tz, 'calcutta') !== false) {
                return '91';
            }
        }

        return '91';
    }
}

if (!function_exists('webshop_settings_local_phone_length')) {
    /**
     * Expected local mobile length (without country code) for auto-prefix on forgot password.
     *
     * @param mixed $countries_list
     * @return int
     */
    function webshop_settings_local_phone_length($countries_list = null) {
        $dial = webshop_settings_phone_dial_code($countries_list);
        $defaults = array('91' => 10, '968' => 8, '971' => 9, '966' => 9);
        if (isset($defaults[$dial])) {
            return (int) $defaults[$dial];
        }

        $key = webshop_settings_default_country_key();
        if ($countries_list === null && function_exists('get_instance')) {
            $CI = get_instance();
            if ($CI && isset($CI->webshop_model) && method_exists($CI->webshop_model, 'getCountry')) {
                try {
                    $countries_list = $CI->webshop_model->getCountry();
                } catch (Exception $e) {
                    $countries_list = array();
                }
            }
        }
        if (is_array($countries_list)) {
            foreach ($countries_list as $c) {
                $a = is_object($c) ? (array) $c : (is_array($c) ? $c : array());
                $row_dial = webshop_country_row_dial_code($c);
                if ($row_dial !== $dial) {
                    continue;
                }
                if (!empty($a['phone_digits']) && is_numeric($a['phone_digits'])) {
                    return max(6, min(15, (int) $a['phone_digits']));
                }
            }
        }
        return 10;
    }
}

/**
 * Fallback when thumbs/uploads have no product or category photo (tries standard paths, then SVG data URI).
 *
 * @param string $uploads_base
 * @param string $thumbs_base  Usually …/uploads/thumbs/
 * @return string
 */
function webshop_no_image_src($uploads_base, $thumbs_base = '') {
    $try = array();
    if ($uploads_base !== null && $uploads_base !== '') {
        // Required default for product/category placeholders.
        $try[] = webshop_media_src($uploads_base, 'no_image.png');
    }
    if ($thumbs_base !== null && $thumbs_base !== '') {
        $try[] = webshop_media_src($thumbs_base, 'no_image.png');
    }
    foreach ($try as $u) {
        if ($u !== '') {
            return $u;
        }
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 180 180"><rect fill="#f1f5f9" width="180" height="180" rx="12"/><path fill="#e2e8f0" d="M52 58h76v48H52z"/><circle cx="64" cy="54" r="7" fill="#cbd5e1"/><path fill="#cbd5e1" d="M44 122h92v10H44z"/><text x="90" y="108" text-anchor="middle" fill="#64748b" font-family="system-ui,sans-serif" font-size="12">No image</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

/**
 * @param string               $uploads_base
 * @param string               $thumbs_base
 * @param array|object         $row
 * @return string
 */
function webshop_product_image_src($uploads_base, $thumbs_base, $row) {
    $row = is_array($row) ? $row : (array) $row;
    $img = '';
    if (!empty($row['image']) && trim((string) $row['image']) !== '') {
        $img = trim((string) $row['image']);
    } elseif (!empty($row['photo']) && trim((string) $row['photo']) !== '') {
        $img = trim((string) $row['photo']);
    }
    if ($img !== '') {
        return webshop_media_src($uploads_base, $img);
    }
    return webshop_no_image_src($uploads_base, $thumbs_base);
}

/**
 * Read numeric stock from a product or variant row (API payloads use quantity, qty, stock, etc.).
 *
 * @param array|object $row
 * @return float|null Null when no stock-like field is present.
 */
function webshop_row_numeric_stock($row) {
    $row = is_array($row) ? $row : (array) $row;
    foreach (array('quantity', 'qty', 'stock', 'available_qty') as $k) {
        if (isset($row[$k]) && $row[$k] !== '' && is_numeric($row[$k])) {
            return (float) $row[$k];
        }
    }
    return null;
}

/**
 * Sellable quantity for product-detail display: sum variant stock when variants expose quantities;
 * otherwise parent product row only (missing stock fields → 0, same as legacy views).
 *
 * @param array      $product
 * @param array|null $variants Product variants/options list from detail API (may be empty).
 * @return float
 */
function webshop_product_display_sellable_qty($product, $variants = null) {
    $product = is_array($product) ? $product : (array) $product;
    $variants = ($variants !== null && is_array($variants)) ? $variants : array();

    if (!empty($variants)) {
        $sum = 0.0;
        $anyVariantStock = false;
        foreach ($variants as $v) {
            $q = webshop_row_numeric_stock($v);
            if ($q !== null) {
                $anyVariantStock = true;
                if ($q > 0) {
                    $sum += $q;
                }
            }
        }
        if ($anyVariantStock) {
            return max(0.0, $sum);
        }
    }

    $pq = webshop_row_numeric_stock($product);
    return $pq !== null ? max(0.0, $pq) : 0.0;
}

/**
 * Normalize product variants for product-detail UI (prices, stock, first selected).
 *
 * @param array       $product
 * @param array       $variants Rows from get_product_by_hash variants[]
 * @param object|null $Settings
 * @return array{has_variants:bool,items:array,default:array|null}
 */
function webshop_product_detail_variants_ui($product, $variants, $Settings = null) {
    $product = is_array($product) ? $product : (array) $product;
    $variants = is_array($variants) ? $variants : array();
    $normalized = webshop_product_variants_from_row(array_merge($product, array('variants' => $variants)));
    if (!empty($normalized)) {
        $variants = $normalized;
    }
    $items = array();

    foreach ($variants as $idx => $v) {
        $v = webshop_normalize_variant_row(is_array($v) ? $v : (array) $v);
        $mapKey = is_string($idx) || is_int($idx) ? (string) $idx : '';
        $vid = webshop_variant_row_id($v, $mapKey);
        $name = webshop_variant_row_display_name($v, is_string($idx) && !is_numeric($idx) ? $idx : '');
        if ($vid < 1) {
            continue;
        }
        if ($name === '') {
            $name = 'Variant #' . $vid;
        }
        $unitQty = isset($v['unit_quantity']) ? (float) $v['unit_quantity'] : 1.0;
        if ($unitQty < 1) {
            $unitQty = 1.0;
        }
        $rowQty = webshop_row_numeric_stock($v);
        $qty = $rowQty !== null ? max(0.0, (float) $rowQty) : 0.0;

        $priced = webshop_variant_pricing_for_line($product, $v, $Settings);
        $unitPrice = (float) $priced['unit_price'];
        $promoUnit = (float) $priced['promo_price'];
        $displayMrp = (float) $priced['display_mrp'];
        $discountPct = (int) $priced['discount_percent'];

        $items[] = array(
            'id'               => $vid,
            'name'             => $name,
            'variant_price'    => (float) $priced['variant_price'],
            'unit_quantity'    => $unitQty,
            'quantity'         => $qty,
            'unit_price'       => $unitPrice,
            'promo_price'      => $promoUnit,
            'formatted_price'  => webshop_price_display($unitPrice, $Settings),
            'formatted_mrp'    => $displayMrp > 0 ? webshop_price_display($displayMrp, $Settings) : '',
            'display_mrp'      => $displayMrp,
            'discount_percent' => $discountPct,
            'in_stock'         => $qty > 0,
        );
    }

    if (empty($items)) {
        return array('has_variants' => false, 'items' => array(), 'default' => null);
    }

    return array(
        'has_variants' => true,
        'items'        => $items,
        'default'      => $items[0],
    );
}

/**
 * PLP/card pricing: use first variant when product has options (same as PDP default).
 *
 * @param array       $product
 * @param object|null $Settings
 * @return array{price:float,mrp:float,discount_percent:int,has_variants:bool,variant_id:int,variant_price:float,variant_unit_quantity:float,variant_name:string,price_from:bool,price_min:float,price_max:float}
 */
function webshop_product_list_card_pricing(array $product, $Settings = null) {
    $product = is_array($product) ? $product : (array) $product;
    $variants = webshop_product_variants_from_row($product);
    $out = array(
        'price'                 => 0.0,
        'mrp'                   => isset($product['mrp']) ? (float) $product['mrp'] : 0.0,
        'discount_percent'      => 0,
        'has_variants'          => false,
        'variant_id'            => 0,
        'variant_price'         => 0.0,
        'variant_unit_quantity' => 1.0,
        'variant_name'          => '',
        'price_from'            => false,
        'price_min'             => 0.0,
        'price_max'             => 0.0,
    );
    if (!empty($variants)) {
        $ui = webshop_product_detail_variants_ui($product, $variants, $Settings);
        if (!empty($ui['has_variants']) && !empty($ui['default'])) {
            $d = $ui['default'];
            $out['has_variants'] = true;
            $out['price'] = (float) $d['unit_price'];
            $out['variant_id'] = (int) $d['id'];
            $out['variant_price'] = (float) $d['variant_price'];
            $out['variant_unit_quantity'] = (float) $d['unit_quantity'];
            $out['variant_name'] = (string) $d['name'];
            $out['discount_percent'] = (int) $d['discount_percent'];
            if (!empty($d['display_mrp']) && (float) $d['display_mrp'] > 0) {
                $out['mrp'] = (float) $d['display_mrp'];
            }
            if (!empty($ui['items']) && count($ui['items']) > 1) {
                $prices = array();
                foreach ($ui['items'] as $row) {
                    $prices[] = (float) $row['unit_price'];
                }
                $out['price_min'] = min($prices);
                $out['price_max'] = max($prices);
                if ($out['price_max'] > $out['price_min']) {
                    $out['price_from'] = true;
                }
            }
            return $out;
        }
        foreach ($variants as $idx => $v) {
            $priced = webshop_variant_pricing_for_line($product, $v, $Settings);
            $unit = (float) $priced['unit_price'];
            if ($unit <= 0) {
                continue;
            }
            $v = webshop_normalize_variant_row(is_array($v) ? $v : (array) $v);
            $out['has_variants'] = true;
            $out['price'] = $unit;
            $out['variant_id'] = webshop_variant_row_id($v);
            $out['variant_price'] = (float) $priced['variant_price'];
            $out['variant_unit_quantity'] = isset($v['unit_quantity']) ? (float) $v['unit_quantity'] : 1.0;
            if ($out['variant_unit_quantity'] < 1) {
                $out['variant_unit_quantity'] = 1.0;
            }
            $out['variant_name'] = webshop_variant_row_display_name($v, is_string($idx) && !is_numeric($idx) ? $idx : '');
            $out['discount_percent'] = (int) $priced['discount_percent'];
            if (!empty($priced['display_mrp']) && (float) $priced['display_mrp'] > 0) {
                $out['mrp'] = (float) $priced['display_mrp'];
            }
            return $out;
        }
    }
    $out['price'] = webshop_checkout_resolve_product_price($product, 0, 0);
    $mrp = isset($product['mrp']) ? (float) $product['mrp'] : 0.0;
    $out['mrp'] = $mrp;
    if ($mrp > $out['price'] && $out['price'] > 0) {
        $out['discount_percent'] = (int) round((($mrp - $out['price']) / $mrp) * 100);
    }
    return $out;
}

/**
 * Stock state for product listing cards (category PLP, CMS product grid).
 * When the API omits stock fields, treats the item as in stock (legacy list behaviour).
 *
 * @param array|object $row
 * @return array{known:bool,in_stock:bool,qty:float}
 */
function webshop_product_list_stock_state($row) {
    $row = is_array($row) ? $row : (array) $row;
    $variants = function_exists('webshop_product_variants_from_row')
        ? webshop_product_variants_from_row($row)
        : array();
    $parent = webshop_row_numeric_stock($row);
    $hasVariantStock = false;
    if (!empty($variants)) {
        foreach ($variants as $v) {
            if (webshop_row_numeric_stock($v) !== null) {
                $hasVariantStock = true;
                break;
            }
        }
    }
    if ($parent === null && !$hasVariantStock) {
        return array('known' => false, 'in_stock' => true, 'qty' => 0.0);
    }
    $qty = webshop_product_display_sellable_qty($row, $variants);
    return array(
        'known'    => true,
        'in_stock' => $qty > 0,
        'qty'      => max(0.0, (float) $qty),
    );
}

/**
 * Single PLP status line + purchase flag (one label only; no duplicate badges).
 *
 * @param array|object $row
 * @param bool         $is_active_ok From productAvailable / product_is_active
 * @return array{can_purchase:bool,label:string,limited:bool,qty:float}
 */
function webshop_product_list_purchase_state($row, $is_active_ok = true) {
    $stock = webshop_product_list_stock_state($row);
    $known = !empty($stock['known']);
    $inStock = !empty($stock['in_stock']);
    $qty = isset($stock['qty']) ? (float) $stock['qty'] : 0.0;
    $outOfStock = $known && !$inStock;
    $activeOk = ($is_active_ok === true || $is_active_ok === 1 || $is_active_ok === 'true' || $is_active_ok === '1');
    $label = '';
    if ($outOfStock) {
        $label = 'Out of stock';
    } elseif (!$activeOk) {
        $label = 'Unavailable';
    }
    return array(
        'can_purchase' => $activeOk && !$outOfStock,
        'label'        => $label,
        'limited'      => $known && $inStock && $qty > 0 && $qty <= 15,
        'qty'          => $qty,
        'unavailable'  => ($label !== ''),
    );
}

/**
 * First non-empty category image path from API/DB row (same field order as product: image, photo, then common aliases).
 *
 * @param array|object $row
 * @return string Relative path or absolute URL; empty if none.
 */
function webshop_category_primary_image_path($row) {
    $keys = array('image', 'photo', 'category_image', 'categoryImage', 'thumb', 'thumbnail', 'picture', 'icon', 'logo');
    if (is_object($row)) {
        foreach ($keys as $k) {
            if (isset($row->$k)) {
                $s = trim((string) $row->$k);
                if ($s !== '') {
                    return $s;
                }
            }
        }
        return '';
    }
    if (!is_array($row)) {
        return '';
    }
    foreach ($keys as $k) {
        if (!empty($row[$k]) && trim((string) $row[$k]) !== '') {
            return trim((string) $row[$k]);
        }
    }
    return '';
}

/**
 * @param string               $uploads_base
 * @param string               $thumbs_base
 * @param array|object         $row
 * @return string
 */
function webshop_category_image_src($uploads_base, $thumbs_base, $row) {
    $img = webshop_category_primary_image_path($row);
    if ($img !== '') {
        return webshop_media_src($uploads_base, $img);
    }
    return webshop_no_image_src($uploads_base, $thumbs_base);
}

function product_sale_price( $productData=[], $variant_price = NULL, $discount = NULL) {

    $data['promo_price'] = 0;

    if ($productData['promotion']) {

        $now = strtotime(date('Y-m-d H:i:s'));

        $start_date = !empty($productData['start_date']) ? strtotime($productData['start_date']) : '';
        $end_date   = !empty($productData['end_date']) ? strtotime($productData['end_date']) : '';

        if (!empty($start_date) && $start_date <= $now && $end_date >= $now) {
            $data['promo_price'] = $productData['promo_price'];
        }
    }

    if (is_array($variant_price)) {
        $price = (float) $variant_price[1] + (float) $productData['price'];
    } else {
        $price = (isset($productData['variant_price'])) ? ((float) $productData['variant_price'] + (float) $productData['price']) : $productData['price'];
    }

    $data['real_unit_price'] = $price;
    $data['discount_rate'] = 0;
    $data['unit_discount'] = 0;
    $pr_discount = 0;
    if ($discount != NULL) {
        $dpos = strpos($discount, '%');
        if ($dpos !== false) {
            $pds = explode("%", $discount);
            //Note : unitprice is product and variant price. Real unit price is actual product price. if we taken realunitprice then grandtotal and discount calculate wrong becuase real unit price not included variant price. so now taken unit_price.(28-03-2020)
            $pr_discount = ( ( (Float) $price * (Float) $pds[0] ) / 100);
        } else {
            $pr_discount = $discount;
        }

        $price = $price - $pr_discount;
        $data['discount_rate'] = $discount;
        $data['unit_discount'] = $pr_discount;
    } else if ($data['promo_price'] > 0 && (int) $data['promo_price'] < $price) {

        $discount_amount = $price - $data['promo_price'];
        $data['discount_rate'] = $discount_amount;
        $data['unit_discount'] = $discount_amount;
        $price = $data['promo_price'];
    }

    $data['tax_rate'] = $productData['tax_rate'] . '%';
    $data['tax_method'] = $productData['tax_method'];

    if ($productData['tax_rate']) {
        if ($productData['tax_method'] == 1) {

            $unit_tax = ($price * (float) $productData['tax_rate'] / 100 );

            $data['unit_tax'] = $unit_tax;
            $data['net_unit_price'] = $price;

            $data['unit_price'] = ((float) $price + $unit_tax);
        } else {

            $unit_tax = (($price * (float) $productData['tax_rate']) / (100 + (float) $productData['tax_rate']));

            $data['unit_tax'] = $unit_tax;
            $data['net_unit_price'] = $price - $unit_tax;
            $data['unit_price'] = $price;
        }
    } else {

        $unit_tax = 0;
        $data['unit_tax'] = $unit_tax;
        $data['net_unit_price'] = $price - $unit_tax;
        $data['unit_price'] = $price;
    }

    return $data;
}
function product_sale_price_webshop( $productData=[], $variant_price = NULL, $discount = NULL, $unit_quantity = NULL) {

    $data['promo_price'] = 0;

    if ($productData['promotion']) {

        $now = strtotime(date('Y-m-d H:i:s'));

        $start_date = !empty($productData['start_date']) ? strtotime($productData['start_date']) : '';
        $end_date   = !empty($productData['end_date']) ? strtotime($productData['end_date']) : '';

        if (!empty($start_date) && $start_date <= $now && $end_date >= $now) {
            $data['promo_price'] = $productData['promo_price'];
        }
    }

    if (is_array($variant_price)) {
        $price = (float) $variant_price[1] + (float) $productData['price'];
    } else {
        $price = (isset($productData['variant_price'])) ? ((float) $productData['variant_price'] + (float) $productData['price']) : $productData['price'];
    }

    $data['real_unit_price'] = $price;
    $data['discount_rate'] = 0;
    $data['unit_discount'] = 0;
    $pr_discount = 0;
    if ($discount != NULL) {
        $dpos = strpos($discount, '%');
        if ($dpos !== false) {
            $pds = explode("%", $discount);
            //Note : unitprice is product and variant price. Real unit price is actual product price. if we taken realunitprice then grandtotal and discount calculate wrong becuase real unit price not included variant price. so now taken unit_price.(28-03-2020)
            $pr_discount = ( ( (Float) $price * (Float) $pds[0] ) / 100)/$unit_quantity;
        } else {
            $pr_discount = $discount/$unit_quantity;
        }

        $price = $price - $pr_discount;
        // $price = $price/$pr_discount;
        $data['discount_rate'] = $discount;
        $data['unit_discount'] = $pr_discount * $unit_quantity;
    } else if ($data['promo_price'] > 0 && (int) $data['promo_price'] < $price) {

        $discount_amount = $price - $data['promo_price'];
        $data['discount_rate'] = $discount_amount;
        $data['unit_discount'] = $discount_amount;
        $price = $data['promo_price'];
    }

    $data['tax_rate'] = $productData['tax_rate'] . '%';
    $data['tax_method'] = $productData['tax_method'];

    /*
     * Tax math contract used by submit_order:
     *   grand_total = sum(net_unit_price * qty) + sum(unit_tax * qty)
     *               = sum(unit_price * qty)
     *
     * So `unit_price` is what the customer pays and equals net + tax, regardless
     * of whether the catalogue price was stored tax-exclusive or tax-inclusive.
     *
     *   tax_method == 1 (EXCLUSIVE — price stored without tax):
     *     unit_tax       = price * rate / 100
     *     net_unit_price = price            (price has no tax in it)
     *     unit_price     = price + unit_tax (customer pays this)
     *
     *   tax_method == 0 (INCLUSIVE — price already contains tax):
     *     unit_tax       = price * rate / (100 + rate)   (extract tax portion)
     *     net_unit_price = price - unit_tax              (net of tax)
     *     unit_price     = price                         (customer pays the catalogue price)
     *
     * Historical regression: for tax_method=0, `net_unit_price` was being set to
     * `price` instead of `price - unit_tax`. That made grand_total = price + tax,
     * i.e. the buyer was charged the inclusive-tax catalogue price PLUS the same
     * tax a second time (a buyer seeing $33 on checkout was billed $34.57 at the
     * payment gateway when the rate was 5%). Restoring `price - unit_tax` for the
     * inclusive branch makes the payment amount match what the checkout shows.
     */
    if ($productData['tax_rate']) {
        if ($productData['tax_method'] == 1) {

            $unit_tax = ($price * (float) $productData['tax_rate'] / 100);

            $data['unit_tax']       = $unit_tax;
            $data['net_unit_price'] = $price;
            $data['unit_price']     = $price + $unit_tax;
        } else {

            $unit_tax = (($price * (float) $productData['tax_rate']) / (100 + (float) $productData['tax_rate']));

            $data['unit_tax']       = $unit_tax;
            $data['net_unit_price'] = $price - $unit_tax;
            $data['unit_price']     = $price;
        }
    } else {

        $unit_tax = 0;
        $data['unit_tax']       = $unit_tax;
        $data['net_unit_price'] = $price;
        $data['unit_price']     = $price;
    }

    return $data;
}
///////////////////////////////////////////////////////////////////////////////////
// #Format: Y-m-d H:i:s 		=> 	#output: 2012-03-24 17:45:12
// #Format: Y-m-d h:i A			=> 	#output: 2012-03-24 05:45 PM
// #Format: d/m/Y H:i:s 		=> 	#output: 24/03/2012 17:45:12
// #Format: d/m/Y                       => 	#output: 24/03/2012
// #Format: g:i A 			=> 	#output: 5:45 PM
// #Format: h:ia 			=> 	#output: 05:45pm
// #Format: g:ia \o\n l jS F Y          => 	#output: 5:45pm on Saturday 24th March 2012
// #Format: l jS F Y 			=> 	#output: Saturday 24th March 2012
// #Format: D jS M Y 			=> 	#output: Sat 24th Mar 2012
// #Format: jS F Y g:ia			=> 	#output: 24th March 2012 5:45pm
// #Format: j F Y			=> 	#output: 24 March 2012
// #Format: j M y			=> 	#output: 24 Mar 12
// #Format: F j				=> 	#output: March 24 
// #Format: F Y				=> 	#output: March 2012
/////////////////////////////////////////////////////////////////////////////////////
function Date_Time_Format($dateTime, $dateFormat = 'jS M Y') {
    $date = date_create($dateTime);

    $newDateFormat = date_format($date, $dateFormat);

    $newDateFormat = str_replace('th ', '<sup>th </sup>', $newDateFormat);
    $newDateFormat = str_replace('1st ', '1<sup>st </sup>', $newDateFormat);
    $newDateFormat = str_replace('nd ', '<sup>nd </sup>', $newDateFormat);
    $newDateFormat = str_replace('rd ', '<sup>rd </sup>', $newDateFormat);

    return $newDateFormat;
}

function get_brands($ids = NULL) {

    $CI = CI();
    if (!isset($CI->db)) {
        return false;
    }
    $CI->db->select('id, code, name, image');
    if ($ids) {
        $CI->db->where_in('id', $ids);
    }
    $q = $CI->db->get('brands');

    if ($q && is_object($q) && $q->num_rows() > 0) {

        foreach ($q->result() as $row) {
            $data[] = $row;
        }

        return $data;
    }

    return false;
}

function baseurl($uri){
    $uri = ltrim((string) $uri, '/');
    $CI = get_instance();
    $configured = '';
    if ($CI && isset($CI->config)) {
        $configured = (string) $CI->config->base_url();
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $script_name = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';

    $script_dir = trim(str_replace('\\', '/', dirname($script_name)), '/');
    $candidate_dir = $script_dir;

    // Fallback: if runtime script points to another app, infer app root from current URI.
    if ($request_uri !== '') {
        $path = (string) parse_url($request_uri, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (!empty($segments) && (empty($candidate_dir) || strcasecmp($candidate_dir, $segments[0]) !== 0)) {
            $candidate_dir = $segments[0];
        }
    }

    if ($host !== '') {
        $root = $protocol . $host . '/';
        if ($candidate_dir !== '') {
            $root .= trim($candidate_dir, '/') . '/';
        }
        return $root . $uri;
    }

    return rtrim($configured, '/') . '/' . $uri;
}

function create_products_structure($productsArray, $imagePath='', $sectionKey = null, $display=null ){
    
    if(is_array($productsArray)) {
        $p=0;
        $productStructure = '';
        
        foreach ($productsArray as $product) {
                        
            $p++;
          //  if($p > 24) { break; } //Maximum 24 Products Display In Tab Container
            $product_hash = md5($product['id'].$sectionKey);           

            $variant_options = '';
            if(isset($product['variants'])){
                $v=0;
                foreach ($product['variants'] as $variant) {
                    $v++;
                    $variant_options           .= '<option value="'.$variant['id'].'" price="'.($variant['price']).'" unit_quantity="'.$variant['unit_quantity'].'" quantity="'.$variant['quantity'].'" title="'.$variant['name']. '" class="attached enabled text-capitalize">'.$variant['name']. '</option>';              
                    $variant_quantity[$v]       = $variant['quantity'];
                    $variant_name[$v]           = $variant['name'];
                    $variant_price[$v]          = $variant['price'];
                    $variant_unit_quantity[$v]  = $variant['unit_quantity'];                        
                    $variant_id[$v]             = $variant['id'];     

                    if($v==1){
                        $product_variants = $variant;
                        $product_name = $product['name'] .' (<span class="variant_name_'.$product_hash.'">'.$variant['name'].'</span>)';
                    }
                }
                $item_quantity = $variant_quantity[1];

            } else {
                $variant_name[1]        = '';
                $variant_price[1]       = 0;
                $variant_quantity[1]    = 0;
                $item_quantity          = $product['quantity'];
                $product_name           = $product['name'];
                $product_variants       = false;
            }

            
            //Set Overselling Condition.
            $item_quantity = $webshop_settings->overselling ? 999 : $item_quantity;
            
            //Webshop helper function
            $product_price = product_sale_price($product, $variant_price);

            $promo_price = $product_price['promo_price'] ? $product_price['promo_price'] : FALSE;

            $sale_price = $promo_price ? $promo_price : $product_price['unit_price'];
            
            $product['promo_price'] = $promo_price;
            $product['unit_price']  = $sale_price;
            
        $productStructure .= '<div class="product product_'.$product_hash.' '.$active.'_tab_product"  style="'. ($display == true?'':'display:none;') .'" >
                    <div class="yith-wcwl-add-to-wishlist">
                        <a style="cursor:pointer; font-size:20px; float:right; margin-right:10px;" class="addtowishlist" product_hash="'.$product_hash.'"><i class="tm tm-favorites"></i></a>
                    </div>
                    <a href="'.baseurl("webshop/product_details/").md5($product['id']).'" class="woocommerce-LoopProduct-link">
                        <div style="height:200px;">
                            <img src="'.$imagePath.$product['image'].'" style="max-height:190px;" class="wp-post-image" alt="'.$product['code'].'">
                        </div>
                        <span class="price">';
       
                         if($promo_price && (int)$promo_price < $product_price['unit_price']) {
        $productStructure.= '<del class="text-danger" style="margin-right:15px;">
                                <span class="amount">Rs. '.number_format($product_price['unit_price'],2).'</span>
                            </del>';
                         }
        $productStructure.= ' <ins>
                                <span class="amount"> </span>
                            </ins>
                            <span class="amount">Rs. <span id="display_unit_price_'.$product_hash.'">'.number_format($sale_price,2).'</span></span> <br/>
                            <span class="mrp"> MRP : '.$Settings->symbol.' '. number_format($product['mrp'],2).'</span>
                        </span>
                        <!-- /.price -->
                        <h2 class="woocommerce-loop-product__title">'.$product_name.'</h2>
                    </a>
                    <div class="hover-area">';                                    

            if($variant_options){

             $productStructure.= '<div class="value">
                            <select class="form-control" name="product_variants['.$product['id'].']" id="product_variants_'.$product_hash.'" onchange="update_price_by_variants(\''.$product_hash.'\')">
                            '.$variant_options .'
                            </select>
                            <a href="#" class="reset_variations" style="visibility: hidden;">Clear</a>
                        </div>';
            }

            $productStructure.= product_hidden_fields($product, $product_hash, $product_variants);
         $posSettings = pos_settings();
        if($item_quantity) {                   
        $productStructure.= '<big class="text-danger btn_outofstock_'.$product_hash.'" style="display: none;" >Out Of Stock</big>
                            <button class="button add_to_cart_button form-control btn_addtocart_'.$product_hash.'" rel="nofollow" onclick="add_to_cart(\''.$product_hash.'\')" >Add to cart</button>';
        } else { 
 if($posSettings->eshop_overselling){
                   $productStructure.= '<big class="text-danger btn_outofstock_'.$product_hash.'" style="display: none;" >Out Of Stock</big>
                            <button class="button add_to_cart_button form-control btn_addtocart_'.$product_hash.'" rel="nofollow" onclick="add_to_cart(\''.$product_hash.'\')" >Add to cart</button>';
     
            }else{
        $productStructure.= '<big class="text-danger btn_outofstock_'.$product_hash.'" >Out Of Stock</big>
                            <button class="button add_to_cart_button form-control btn_addtocart_'.$product_hash.'" rel="nofollow" style="display: none;" onclick="add_to_cart(\''.$product_hash.'\')" >Add to cart</button>';
           }
        }

        $productStructure.= '</div>
                </div>
                <!-- .product -->';
  
        }//end foreach.
        
        return $productStructure;
        
    }//end if
}


function product_hidden_fields($product, $pr_hash='', $variant = false) {
        
    $product_hash = $pr_hash ? '_'.$pr_hash : '';

    $hiddenValues = '<input type="hidden" value="1" name="quantity['.$product['id'].']" id="quantity'.$product_hash.'">
                    <input type="hidden" value="'.$product['tax_rate'].'" name="tax_rate['.$product['id'].']" id="tax_rate'.$product_hash.'">
                    <input type="hidden" value="'.$product['tax_method'].'" name="tax_method['.$product['id'].']" id="tax_method'.$product_hash.'">
                    <input type="hidden" value="'.$product['price'].'" name="price['.$product['id'].']" id="price'.$product_hash.'">
                    <input type="hidden" value="'.$product['id'].'" name="product_id['.$product['id'].']" id="product_id'.$product_hash.'">
                    <input type="hidden" value="'.$product['unit_price'].'" name="unit_price['.$product['id'].']" id="unit_price'.$product_hash.'">
                    <input type="hidden" value="'.$product['promo_price'].'" name="promotion_price['.$product['id'].']" id="promotion_price'.$product_hash.'">';

    $hiddenValues .= '<input type="hidden" value="'.(isset($variant['unit_quantity']) ? $variant['unit_quantity']:1) .'" name="variant_unit_quantity['.$product['id'].']" id="variant_unit_quantity'.$product_hash.'">';
    $hiddenValues .= '<input type="hidden" value="'.(isset($variant['id']) ? $variant['id']:0).'" name="variant_id['.$product['id'].']" id="variant_id'.$product_hash.'" >';
    $hiddenValues .= '<input type="hidden" value="'.(isset($variant['price']) ? $variant['price']:0).'" name="variant_unit_price['.$product['id'].']" id="variant_unit_price'.$product_hash.'">';
         
    return $hiddenValues;
}
    

if (!function_exists('CI')) {

    function CI() {

        $CI = & get_instance(); // making instance of CI

        return $CI; // Its returning an object for CI class
    }

}
if (!function_exists('latest_Products')) {
    function latest_Products($imagePath){
       $CI = CI();
       if (!isset($CI->db)) {
           return '';
       }
       $latestP = $CI->db->limit(6)->order_by('id','DESC')->get('sma_products')->result();
       $Settings = isset($CI->Settings) ? $CI->Settings : (object) array('symbol' => '');
    
       $latestProductsStructure = '';
       foreach($latestP as $items){

            $variants =   product_variants($items->id);
          $price = $items->price;
          if($variants){
              foreach($variants as $variant_price){
                 if(round($variant_price['price'])){
                    $price = $variant_price['price'];
                     break;
                 } 
              }
          }else{
              $price = $items->price;
          }
         
           $latestProductsStructure.='<div class="landscape-product-widget product">';
                $latestProductsStructure.='<a class="woocommerce-LoopProduct-link" href="'.baseurl("webshop/product_details/").md5($items->id).'">';
                     $latestProductsStructure.='<div class="media d-flex ">';
                        $latestProductsStructure.='<img class="wp-post-image m-0" src="'.$imagePath.$items->image.'" alt="'.$items->name.'">';
                        $latestProductsStructure.='<div class="media-body">';
                            $latestProductsStructure.='<span class="price">';
                                $latestProductsStructure.='<ins>';
                                     $latestProductsStructure.='<span class="amount"> '.$Settings->symbol.number_format($price,2).'</span>';
                                $latestProductsStructure.='</ins>';
                                 $latestProductsStructure.='<br/><ins>';
                                     $latestProductsStructure.='<span class="amount"> MRP : '.$Settings->symbol.' '.number_format($items->mrp,2).'</span>';
                                $latestProductsStructure.='</ins>';
//                                $latestProductsStructure.='<del>';
//                                     $latestProductsStructure.='<span class="amount">26.99</span>';
//                                $latestProductsStructure.='</del>';
                            $latestProductsStructure.='</span>';

                            $latestProductsStructure.='<h2 class="woocommerce-loop-product__title">'.$items->name.'</h2>';
                            $latestProductsStructure.='<div class="techmarket-product-rating">';
                                $latestProductsStructure.='<div title="Rated 0 out of 5" class="star-rating">';
                                     $latestProductsStructure.='<span style="width:0%">';
                                     $latestProductsStructure.='<strong class="rating">0</strong> out of 5</span>';
                                $latestProductsStructure.='</div>';
                                $latestProductsStructure.='<span class="review-count">(0)</span>';
                            $latestProductsStructure.='</div>';
                        
                        $latestProductsStructure.='</div>';
                     $latestProductsStructure.='</div>';
                $latestProductsStructure.='</a>';
           $latestProductsStructure.='</div>';
       }
       
       return $latestProductsStructure;
      
    }
}



if (!function_exists('rupeeFormat')) {  
 function rupeeFormat($number, $decimal=2, $prefix='&#x20B9;') {
      
       return $prefix.'&nbsp;'.number_format($number, $decimal, ".", ",");    
    }
}



if(!function_exists('product_variants')){
    function product_variants($product_id = NULL){
         $CI = CI();
         if (!isset($CI->db)) {
             return false;
         }
         $q = $CI->db->where('product_id', $product_id)->order_by('price', 'asc')->get('product_variants');

        if ($q && is_object($q) && $q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[] = (array) $row;
            }
            return $data;
        }
        return false;
    }
}


if(!function_exists('pos_settings')){
    function pos_settings(){
       $CI = CI();
       /* DB-less storefront: settings come from MY_Controller + Webshop_api_model (ElintOm getsettings API). */
       if (!isset($CI->db)) {
           if (isset($CI->webshop_model) && is_object($CI->webshop_model) && method_exists($CI->webshop_model, 'get_webshop_pos_settings')) {
               return $CI->webshop_model->get_webshop_pos_settings();
           }
           $row = new stdClass();
           $row->default_eshop_warehouse = '0';
           $row->default_eshop_biller = '0';
           $row->eshop_overselling = '0';
           $row->eshop_active = (isset($CI->Settings->active_webshop) ? (int) $CI->Settings->active_webshop : 1);
           return $row;
       }
       $q = $CI->db->select('default_eshop_warehouse, default_eshop_biller, eshop_overselling,eshop_active')->get('pos_settings');
    
         if ($q && is_object($q) && $q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE; 
    }
}

if (!function_exists('webshop_theme_host_key')) {
    /**
     * Normalized HTTP host for theme folder lookup (no port, no www).
     *
     * @return string
     */
    function webshop_theme_host_key() {
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string) $_SERVER['HTTP_HOST'])) : '';
        if ($host === '') {
            return 'localhost';
        }
        if (preg_match('/:\d+$/', $host)) {
            $host = preg_replace('/:\d+$/', '', $host);
        }
        $host = preg_replace('/^www\./', '', $host);
        $host = preg_replace('/[^a-zA-Z0-9_.-]/', '', $host);
        return $host !== '' ? $host : 'localhost';
    }
}

if (!function_exists('webshop_plane_vanila_theme_folder')) {
    /**
     * Active plane_vanila_theme directory (from elintom_api_switch or auto-detect on disk).
     *
     * Priority: $CI->data → elintom_theme_view_folder (elintom_api_switch) → host folder on disk.
     *
     * @return string e.g. webshop_vanila_structure
     */
    function webshop_plane_vanila_theme_folder() {
        $CI =& get_instance();
        if (isset($CI->data['plane_vanila_theme_folder']) && (string) $CI->data['plane_vanila_theme_folder'] !== '') {
            return (string) $CI->data['plane_vanila_theme_folder'];
        }
        $CI->config->load('elintom_api', true);
        $from_switch = trim((string) $CI->config->item('elintom_theme_view_folder', 'elintom_api'));
        if ($from_switch !== '') {
            $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '', $from_switch);
            if ($safe !== '') {
                return $safe;
            }
        }
        $host = webshop_theme_host_key();
        $hostDir = VIEWPATH . 'plane_vanila_theme' . DIRECTORY_SEPARATOR . $host;
        if (is_file($hostDir . DIRECTORY_SEPARATOR . 'header.php') || is_file($hostDir . DIRECTORY_SEPARATOR . 'index.php')) {
            return $host;
        }
        $ws = isset($CI->webshop_settings) ? $CI->webshop_settings : null;
        if ($ws === null && isset($CI->data['webshop_settings'])) {
            $ws = $CI->data['webshop_settings'];
        }
        $theme = '';
        if (is_object($ws) && isset($ws->webshop_theme)) {
            $theme = trim((string) $ws->webshop_theme);
        }
        if ($theme === 'restaurant') {
            return 'restaurant';
        }
        if ($theme === 'nw') {
            return 'nw_theme';
        }
        if ($theme !== '') {
            $assets_from_switch = trim((string) $CI->config->item('elintom_theme_assets_directory', 'elintom_api'));
            if ($assets_from_switch !== '') {
                $safeAssets = preg_replace('/[^a-zA-Z0-9_.-]/', '', $assets_from_switch);
                if ($safeAssets !== '' && is_dir(VIEWPATH . 'plane_vanila_theme' . DIRECTORY_SEPARATOR . $safeAssets)) {
                    return $safeAssets;
                }
            }
            $guess = preg_replace('/[^a-zA-Z0-9_.-]/', '', $theme . '_theme');
            $guessDir = VIEWPATH . 'plane_vanila_theme' . DIRECTORY_SEPARATOR . $guess;
            if (is_dir($guessDir)) {
                return $guess;
            }
        }
        return $host !== '' ? $host : 'default';
    }
}

if (!function_exists('webshop_plane_vanila_view_prefix')) {
    /**
     * View path prefix for load->view(), with trailing slash.
     *
     * @param string|null $folder
     * @return string e.g. plane_vanila_theme/gulfpharmacy_theme_new/
     */
    function webshop_plane_vanila_view_prefix($folder = null) {
        $CI =& get_instance();
        if ($folder === null && isset($CI->data['plane_vanila_view_prefix']) && (string) $CI->data['plane_vanila_view_prefix'] !== '') {
            return (string) $CI->data['plane_vanila_view_prefix'];
        }
        $name = ($folder !== null && (string) $folder !== '')
            ? preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) $folder)
            : webshop_plane_vanila_theme_folder();
        return 'plane_vanila_theme/' . $name . '/';
    }
}

if (!function_exists('webshop_plane_vanila_view')) {
    /**
     * Relative view path for $this->load->view() (no .php).
     *
     * @param string $relative e.g. components/login_form or index
     * @return string
     */
    function webshop_plane_vanila_view($relative = '') {
        $rel = ltrim(str_replace('\\', '/', (string) $relative), '/');
        $prefix = rtrim(webshop_plane_vanila_view_prefix(), '/');
        return $rel !== '' ? $prefix . '/' . $rel : $prefix;
    }
}

if (!function_exists('webshop_plane_vanila_view_file')) {
    /**
     * Absolute path to a theme view file (for require_once).
     *
     * @param string $relative
     * @return string
     */
    function webshop_plane_vanila_view_file($relative = '') {
        $rel = ltrim(str_replace('\\', '/', (string) $relative), '/');
        if (substr($rel, -4) === '.php') {
            $rel = substr($rel, 0, -4);
        }
        return VIEWPATH . webshop_plane_vanila_view($rel) . '.php';
    }
}

if (!function_exists('webshop_render_wishlist_card_button')) {
    /**
     * Heart button for PLP/carousel cards (avoids CodeIgniter load->view per product in a grid).
     *
     * @param int         $product_id
     * @param int         $variant_id
     * @param array|null  $wishlist_lookup
     * @param string      $extra_class
     * @return void
     */
    function webshop_render_wishlist_card_button($product_id, $variant_id = 0, $wishlist_lookup = null, $extra_class = '') {
        $product_id = (int) $product_id;
        if ($product_id < 1) {
            return;
        }
        $variant_id = (int) $variant_id;
        $lookup = function_exists('webshop_view_wishlist_lookup')
            ? webshop_view_wishlist_lookup(is_array($wishlist_lookup) ? $wishlist_lookup : null)
            : (is_array($wishlist_lookup) ? $wishlist_lookup : array());
        $logged_in = function_exists('webshop_is_customer_logged_in') ? webshop_is_customer_logged_in() : false;
        $in_wishlist = $logged_in && function_exists('webshop_product_in_wishlist_lookup')
            ? webshop_product_in_wishlist_lookup($lookup, $product_id, $variant_id)
            : false;
        $extra_class = trim((string) $extra_class);
        $cls = 'gp-fav-btn gp-card-fav-btn'
            . ($extra_class !== '' ? ' ' . $extra_class : '')
            . ($in_wishlist ? ' is-saved' : '');
        echo '<button type="button" class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-wishlist-toggle data-product-id="' . $product_id . '" data-variant-id="' . $variant_id . '"'
            . ' data-in-wishlist="' . ($in_wishlist ? '1' : '0') . '"'
            . ' aria-pressed="' . ($in_wishlist ? 'true' : 'false') . '"'
            . ' aria-label="' . htmlspecialchars($in_wishlist ? 'Remove from favourites' : 'Save to favourites', ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="gp-fav-btn__icon" aria-hidden="true">' . ($in_wishlist ? '♥' : '♡') . '</span>'
            . '</button>';
    }
}

if (!function_exists('webshop_extract_controller_view_data')) {
    /**
     * Make $CI->data variables available to theme partials included via require().
     * Without this, header/footer included from webshop_require_theme_*() miss cms_nav_pages, cart counts, etc.
     */
    function webshop_extract_controller_view_data() {
        if (!function_exists('get_instance')) {
            return;
        }
        $CI =& get_instance();
        if (isset($CI->data) && is_array($CI->data)) {
            extract($CI->data, EXTR_SKIP);
        }
        if (isset($CI->load) && is_object($CI->load) && isset($CI->load->_ci_cached_vars) && is_array($CI->load->_ci_cached_vars)) {
            extract($CI->load->_ci_cached_vars, EXTR_SKIP);
        }
    }
}

if (!function_exists('webshop_theme_storefront_stylesheets')) {
    /**
     * Standard CSS stack for Herbinn / plane_vanila account & checkout shells (header nav + footer grid).
     *
     * @param array $extra Relative paths under theme assets/css/ (optional).
     * @return void
     */
    function webshop_theme_storefront_stylesheets(array $extra = array()) {
        $ver = '20260526j';
        $core = array(
            'css/common.css',
            'css/header.css',
            'css/header-drawers.css',
            'css/herbinn-site.css',
            'css/herbinn-overrides.css',
            'css/storefront-layout.css',
            'css/components.css',
        );
        foreach (array_merge($core, $extra) as $rel) {
            $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');
            if ($rel === '') {
                continue;
            }
            $href = function_exists('webshop_theme_assets_url')
                ? webshop_theme_assets_url($rel . '?ver=' . $ver)
                : '';
            if ($href === '') {
                continue;
            }
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
}

if (!function_exists('webshop_require_theme_header')) {
    /**
     * Include active plane_vanila header (e.g. herbinnwellness/header.php) or legacy webshop/header.php.
     *
     * @return bool True when a header file was included.
     */
    function webshop_require_theme_header() {
        webshop_extract_controller_view_data();
        if (function_exists('webshop_plane_vanila_view_file')) {
            $path = webshop_plane_vanila_view_file('header');
            if (is_file($path)) {
                require $path;
                return true;
            }
        }
        $legacy = VIEWPATH . 'webshop/header.php';
        if (is_file($legacy)) {
            require $legacy;
            return true;
        }
        return false;
    }
}

if (!function_exists('webshop_require_theme_footer')) {
    /**
     * Include active plane_vanila footer or legacy webshop/footer.php.
     *
     * @return bool True when a footer file was included.
     */
    function webshop_require_theme_footer() {
        webshop_extract_controller_view_data();
        if (function_exists('webshop_plane_vanila_view_file')) {
            $path = webshop_plane_vanila_view_file('footer');
            if (is_file($path)) {
                require $path;
                return true;
            }
        }
        $legacy = VIEWPATH . 'webshop/footer.php';
        if (is_file($legacy)) {
            require $legacy;
            return true;
        }
        return false;
    }
}

if (!function_exists('webshop_plane_vanila_views_apppath')) {
    /**
     * Absolute APPPATH to active plane_vanila_theme folder (trailing slash).
     *
     * @return string
     */
    function webshop_plane_vanila_views_apppath() {
        $folder = webshop_plane_vanila_theme_folder();
        return APPPATH . 'views/plane_vanila_theme/' . $folder . '/';
    }
}

if (!function_exists('webshop_theme_assets_directory_name')) {
    /**
     * CSS/JS folder under assets/webshop/{name}/ (from elintom_api_switch or host).
     *
     * @return string
     */
    function webshop_theme_assets_directory_name() {
        $CI =& get_instance();
        if (isset($CI->data['Assets_directory_name']) && (string) $CI->data['Assets_directory_name'] !== '') {
            return (string) $CI->data['Assets_directory_name'];
        }
        $CI->config->load('elintom_api', true);
        $from_switch = trim((string) $CI->config->item('elintom_theme_assets_directory', 'elintom_api'));
        if ($from_switch !== '') {
            $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '', $from_switch);
            if ($safe !== '') {
                return $safe;
            }
        }
        $folder = webshop_plane_vanila_theme_folder();
        $assetsDir = FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'webshop' . DIRECTORY_SEPARATOR . $folder;
        if (is_dir($assetsDir)) {
            return $folder;
        }
        return webshop_theme_host_key();
    }
}

if (!function_exists('webshop_theme_assets_url')) {
    /**
     * URL to a file under assets/webshop/{active theme folder}/.
     *
     * @param string $relative e.g. css/forgot-password.css
     * @return string
     */
    function webshop_theme_assets_url($relative = '') {
        $base = function_exists('webshop_theme_assets_base_url')
            ? webshop_theme_assets_base_url()
            : rtrim(base_url('assets/webshop/'), '/') . '/';
        $dir = function_exists('webshop_theme_assets_directory_name')
            ? webshop_theme_assets_directory_name()
            : webshop_plane_vanila_theme_folder();
        $rel = ltrim(str_replace('\\', '/', (string) $relative), '/');
        return $rel !== '' ? $base . $dir . '/' . $rel : $base . $dir . '/';
    }
}

if (!function_exists('webshop_theme_assets_base_url')) {
    /**
     * Base URL for theme CSS/JS (assets/webshop/{folder}/).
     *
     * @return string Trailing slash
     */
    function webshop_theme_assets_base_url() {
        $CI =& get_instance();
        if (isset($CI->data['assets']) && (string) $CI->data['assets'] !== '') {
            $url = (string) $CI->data['assets'];
            if (strpos($url, 'assets/webshop/') !== false) {
                return rtrim($url, '/') . '/';
            }
            if (strpos($url, 'themes/') !== false) {
                return rtrim($url, '/') . '/';
            }
        }
        $dir = function_exists('webshop_theme_assets_directory_name')
            ? trim((string) webshop_theme_assets_directory_name())
            : '';
        if ($dir !== '') {
            return rtrim(base_url('assets/webshop/'), '/') . '/';
        }
        return rtrim(base_url('assets/webshop/'), '/') . '/';
    }
}

if (!function_exists('webshop_checkout_submit_token')) {
    /**
     * Hourly anti-replay token embedded in checkout forms (submit_order hidden field).
     *
     * @return string
     */
    function webshop_checkout_submit_token() {
        return md5(date('Y-m-d H'));
    }
}

if (!function_exists('webshop_checkout_submit_token_is_valid')) {
    /**
     * Accept current hour, previous hour (checkout left open across the hour), or session token from GET checkout.
     *
     * @param string $posted
     * @return bool
     */
    function webshop_checkout_submit_token_is_valid($posted) {
        $posted = trim((string) $posted);
        if ($posted === '') {
            return false;
        }
        if ($posted === webshop_checkout_submit_token()) {
            return true;
        }
        if ($posted === md5(date('Y-m-d H', time() - 3600))) {
            return true;
        }
        $CI = function_exists('get_instance') ? get_instance() : null;
        if ($CI && isset($CI->session) && is_object($CI->session)) {
            $sess = $CI->session->userdata('checkout_submit_token');
            if (is_string($sess) && $sess !== '' && $posted === $sess) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('webshop_checkout_flash_error_message')) {
    /**
     * User-visible checkout error from session flash (error_message or error).
     *
     * @return string
     */
    function webshop_checkout_flash_error_message() {
        $CI = function_exists('get_instance') ? get_instance() : null;
        if (!$CI || !isset($CI->session) || !is_object($CI->session)) {
            return '';
        }
        $cart_has_lines = isset($_SESSION['cart']) && is_array($_SESSION['cart']) && count($_SESSION['cart']) > 0;
        foreach (array('error_message', 'error') as $key) {
            $msg = $CI->session->flashdata($key);
            if (!is_string($msg) || trim($msg) === '') {
                continue;
            }
            $msg = trim($msg);
            if ($cart_has_lines && stripos($msg, 'cart is empty') !== false) {
                continue;
            }
            if ($cart_has_lines && stripos($msg, 'variant_price') !== false) {
                continue;
            }
            return $msg;
        }
        return '';
    }
}

if (!function_exists('webshop_csrf_pair')) {
    /**
     * CSRF token for storefront AJAX (webshop/webshop_request).
     *
     * @return array{name:string,hash:string}
     */
    function webshop_csrf_pair() {
        $CI =& get_instance();
        return array(
            'name' => $CI->security->get_csrf_token_name(),
            'hash' => $CI->security->get_csrf_hash(),
        );
    }
}

if (!function_exists('webshop_avatar_initials_from_name')) {
    /**
     * @param string $name
     * @return string 1–2 uppercase initials
     */
    function webshop_avatar_initials_from_name($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', $name, 3);
        if (!$parts || !isset($parts[0])) {
            return '?';
        }
        $first = strtoupper(substr($parts[0], 0, 1));
        $second = isset($parts[1]) && $parts[1] !== '' ? strtoupper(substr($parts[1], 0, 1)) : '';

        return $second !== '' ? $first . $second : $first;
    }
}

if (!function_exists('webshop_customer_avatar_src')) {
    /**
     * Resolve profile photo URL for My Account (companies.logo → image).
     *
     * @param array  $customer         Customer row from get_customer()
     * @param string $local_images_base e.g. base_url('assets/images/customers/')
     * @param string $uploads_base     ElintOm mdata uploads root from $uploads
     * @return string Absolute URL or empty when no photo
     */
    function webshop_customer_avatar_src(array $customer, $local_images_base = '', $uploads_base = '') {
        $path = '';
        foreach (array('image', 'logo', 'profile_image', 'avatar') as $key) {
            if (!empty($customer[$key])) {
                $candidate = trim((string) $customer[$key]);
                if ($candidate !== '' && strtolower($candidate) !== 'null') {
                    $path = $candidate;
                    break;
                }
            }
        }
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path) || preg_match('#^data:image/#i', $path)) {
            return $path;
        }
        if (function_exists('webshop_media_src') && (string) $uploads_base !== '') {
            $from_uploads = webshop_media_src((string) $uploads_base, $path);
            if ($from_uploads !== '') {
                return $from_uploads;
            }
        }
        $base = rtrim((string) $local_images_base, '/');
        if ($base === '') {
            $base = rtrim(base_url('assets/images/customers'), '/');
        }

        return $base . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}

if (!function_exists('webshop_read_json_request_body')) {
    /**
     * Read JSON request body once (MY_Security may have cached it for CSRF + controllers).
     *
     * @return array<string,mixed>
     */
    function webshop_read_json_request_body() {
        static $parsed = null;
        if ($parsed !== null) {
            return $parsed;
        }
        $raw = defined('WEBSHOP_RAW_JSON_BODY') ? WEBSHOP_RAW_JSON_BODY : file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            $parsed = array();
            return $parsed;
        }
        $decoded = json_decode($raw, true);
        $parsed = is_array($decoded) ? $decoded : array();
        return $parsed;
    }
}

if (!function_exists('webshop_csrf_hidden_input')) {
    /**
     * Hidden input for native POST forms (checkout submit_order, login, etc.).
     *
     * @return string Safe HTML fragment
     */
    function webshop_csrf_hidden_input() {
        $pair = webshop_csrf_pair();
        if (empty($pair['name']) || $pair['hash'] === '') {
            return '';
        }
        $name = htmlspecialchars((string) $pair['name'], ENT_QUOTES, 'UTF-8');
        $hash = htmlspecialchars((string) $pair['hash'], ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="' . $name . '" value="' . $hash . '">';
    }
}

if (!function_exists('webshop_normalize_webshop_view_method')) {
    /**
     * Strip legacy hardcoded theme folder prefixes from load_view() method strings.
     *
     * @param string $method e.g. gulfpharmacy_theme/index or index
     * @return string e.g. index
     */
    function webshop_normalize_webshop_view_method($method) {
        $method = trim(str_replace('\\', '/', (string) $method), '/');
        if ($method === '') {
            return '';
        }
        if (preg_match('#^plane_vanila_theme/[^/]+/(.+)$#i', $method, $m)) {
            return $m[1];
        }
        $active = webshop_plane_vanila_theme_folder();
        if ($active !== '' && stripos($method, $active . '/') === 0) {
            return substr($method, strlen($active) + 1);
        }
        if (preg_match('#^[a-z0-9_.-]+_theme(?:_new|_old)?/(.+)$#i', $method, $m)) {
            return $m[1];
        }
        return $method;
    }
}

if (!function_exists('webshop_store_display_name')) {
    /**
     * Public store name for header/footer when the logo is absent or fails to load.
     * Scans ElintOm getsettings payloads: pos_settings ($Settings) then webshop_settings, common key aliases.
     *
     * @param object|null $Settings        MY_Controller $Settings (API pos_settings)
     * @param object|null $webshop_settings
     * @return string Non-empty display name, or empty string if none found (caller may fallback).
     */
    function webshop_store_display_name($Settings = null, $webshop_settings = null) {
        $blocks = array();
        if (is_object($Settings)) {
            $blocks[] = $Settings;
        }
        if (is_object($webshop_settings)) {
            $blocks[] = $webshop_settings;
        }
        $keys = array(
            'site_name',
            'shop_name',
            'store_name',
            'eshop_name',
            'company_name',
            'business_name',
            'app_name',
            'meta_title',
            'biller_name',
        );
        foreach ($blocks as $obj) {
            foreach ($keys as $k) {
                if (isset($obj->$k)) {
                    $v = trim((string) $obj->$k);
                    if ($v !== '') {
                        return $v;
                    }
                }
            }
        }

        // Optional getsettings `website_setting[]` rows (fields/value), ElintOm Storefront / NW payload.
        $ws_field_names = array('site_name', 'shop_name', 'store_name', 'site_title', 'shop_title', 'store_title');
        if (function_exists('webshop_ws_website_setting_bundles')) {
            $seen = array();
            foreach (webshop_ws_website_setting_bundles() as $items) {
                foreach ($items as $item) {
                    $f = webshop_ws_row_field_key($item);
                    if ($f === '' || !in_array($f, $ws_field_names, true)) {
                        continue;
                    }
                    if (isset($seen[$f])) {
                        continue;
                    }
                    $seen[$f] = true;
                    $v = webshop_ws_row_value_string($item);
                    if ($v !== '') {
                        return $v;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('webshop_ws_row_field_key')) {
    /**
     * Normalized `fields` key from a website_setting row (handles Fields/fields from JSON/API).
     *
     * @param mixed $item
     * @return string Lowercase key or empty
     */
    function webshop_ws_row_field_key($item) {
        $row = is_object($item) ? $item : (object) (array) $item;
        foreach (array('field_key', 'fields', 'Fields', 'FIELDS') as $k) {
            if (isset($row->$k) && trim((string) $row->$k) !== '') {
                return strtolower(trim((string) $row->$k));
            }
        }
        return '';
    }
}

if (!function_exists('webshop_ws_row_value_string')) {
    /**
     * Value column from a website_setting row (handles Value/value; SQL NULL / JSON null).
     *
     * @param mixed $item
     * @return string
     */
    function webshop_ws_row_value_string($item) {
        $row = is_object($item) ? $item : (object) (array) $item;
        foreach (array('value', 'Value', 'VALUE') as $k) {
            if (!property_exists($row, $k)) {
                continue;
            }
            $v = $row->$k;
            if ($v === null) {
                continue;
            }
            return trim((string) $v);
        }
        return '';
    }
}

if (!function_exists('webshop_ws_row_sort_order')) {
    /**
     * Sort order from storefront / website_setting row (header/footer table or API object).
     *
     * @param mixed $item
     * @return int
     */
    function webshop_ws_row_sort_order($item) {
        $row = is_object($item) ? $item : (object) (array) $item;
        foreach (array('sort_order', 'Sort_order', 'SORT_ORDER') as $k) {
            if (!property_exists($row, $k)) {
                continue;
            }
            $v = $row->$k;
            if ($v === null || $v === '') {
                continue;
            }
            return (int) $v;
        }
        return 0;
    }
}

if (!function_exists('webshop_ws_row_label_string')) {
    /**
     * Admin label from Storefront row when present; otherwise humanize field key.
     *
     * @param mixed  $item
     * @param string $field_key_fallback lowercase fields key if known
     * @return string
     */
    function webshop_ws_row_label_string($item, $field_key_fallback = '') {
        $row = is_object($item) ? $item : (object) (array) $item;
        foreach (array('label', 'Label', 'LABEL') as $k) {
            if (isset($row->$k) && trim((string) $row->$k) !== '') {
                return trim((string) $row->$k);
            }
        }
        $fk = $field_key_fallback !== '' ? $field_key_fallback : webshop_ws_row_field_key($item);
        if ($fk === '') {
            return '';
        }
        return ucwords(str_replace('_', ' ', $fk));
    }
}

if (!function_exists('webshop_ws_row_icons_string')) {
    /**
     * Optional Font Awesome fragment from a settings row.
     *
     * @param mixed $item
     * @return string
     */
    function webshop_ws_row_icons_string($item) {
        $row = is_object($item) ? $item : (object) (array) $item;
        foreach (array('icons', 'Icons', 'ICONS') as $k) {
            if (isset($row->$k) && trim((string) $row->$k) !== '') {
                return trim((string) $row->$k);
            }
        }
        return '';
    }
}

if (!function_exists('webshop_ws_row_is_active')) {
    /**
     * Storefront header/footer row is active only when is_active === 1 (strict).
     * Rows with no is_active property are treated as active for legacy API payloads.
     *
     * @param mixed $item
     * @return bool
     */
    function webshop_ws_row_is_active($item) {
        $row = is_object($item) ? $item : (object) (array) $item;
        $found = false;
        $raw = null;
        foreach (array('is_active', 'Is_active', 'IS_ACTIVE', 'active', 'Active') as $k) {
            if (!property_exists($row, $k) && !isset($row->$k)) {
                continue;
            }
            $found = true;
            $raw = $row->$k;
            break;
        }
        if (!$found) {
            return true;
        }
        if ($raw === true || $raw === 'true') {
            return true;
        }
        return ((int) $raw) === 1;
    }
}

if (!function_exists('webshop_filter_active_setting_section_rows')) {
    /**
     * Normalize section row list and keep only is_active === 1 rows.
     *
     * @param mixed $rows
     * @return array<int, mixed>
     */
    function webshop_filter_active_setting_section_rows($rows) {
        $list = webshop_normalize_setting_section_row_list($rows);
        $out = array();
        foreach ($list as $item) {
            if (webshop_ws_row_is_active($item)) {
                $out[] = $item;
            }
        }
        return $out;
    }
}

if (!function_exists('webshop_filter_active_website_setting_rows')) {
    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    function webshop_filter_active_website_setting_rows($items) {
        if (!is_array($items)) {
            return array();
        }
        $out = array();
        foreach ($items as $item) {
            if (webshop_ws_row_is_active($item)) {
                $out[] = $item;
            }
        }
        return $out;
    }
}

if (!function_exists('webshop_filter_website_setting_sections_object')) {
    /**
     * Defense in depth: strip inactive rows from getsettings website_setting_sections.
     *
     * @param mixed $sections header/footer object or array
     * @return object { header: array, footer: array }
     */
    function webshop_filter_website_setting_sections_object($sections) {
        $wrap = is_object($sections) ? $sections : (object) (is_array($sections) ? $sections : array());
        $header = isset($wrap->header) ? $wrap->header : array();
        $footer = isset($wrap->footer) ? $wrap->footer : array();
        return (object) array(
            'header' => webshop_filter_active_setting_section_rows($header),
            'footer' => webshop_filter_active_setting_section_rows($footer),
        );
    }
}

if (!function_exists('webshop_clear_elintom_settings_cache')) {
    /**
     * Drop session getsettings cache so the next request refetches ElintOm (after CMS admin save).
     */
    function webshop_clear_elintom_settings_cache() {
        if (!function_exists('get_instance')) {
            return;
        }
        $CI = get_instance();
        if (isset($CI->session)) {
            $CI->session->unset_userdata('elintom_cache_getsettings');
        }
    }
}

if (!function_exists('webshop_storefront_preset_field_keys')) {
    /**
     * Known Storefront field_key presets (admin should use these — reduces typos).
     *
     * @return array<int, string>
     */
    function webshop_storefront_preset_field_keys() {
        return array(
            'logo_image', 'banner_image', 'favicon',
            'announcement_bar', 'top_bar_html', 'phone_strip',
            'about_us', 'contact_us', 'privacy_policy', 'terms_and_conditions',
            'phone', 'mobile', 'hotline', 'email', 'address',
            'media_facebook_link', 'media_instagram_link', 'media_twitter_link',
            'media_linkedin_link', 'media_youtube_link', 'media_tiktok_link',
        );
    }
}

if (!function_exists('webshop_footer_row_has_semantic_fallback')) {
    /**
     * True when empty value may be filled from POS/biller Settings (phone, address, about, email).
     *
     * @param string $field_key
     * @param string $label
     * @return bool
     */
    function webshop_footer_row_has_semantic_fallback($field_key, $label = '') {
        $hint = strtolower(trim((string) $field_key . ' ' . (string) $label));
        if ($hint === '') {
            return false;
        }
        return (bool) preg_match(
            '/phone|tel|mobile|hotline|whatsapp|fax|call|address|location|visit|office|branch|about|intro|company|story|mission|email|e-mail|mail|support|contact/i',
            $hint
        );
    }
}

if (!function_exists('webshop_storefront_default_link_for_field_key')) {
    /**
     * Internal webshop URL when VALUE is empty but field_key is a known CMS/static slug.
     *
     * @param string $field_key
     * @return string Absolute URL or empty
     */
    function webshop_storefront_default_link_for_field_key($field_key) {
        $fk = strtolower(trim((string) $field_key));
        $fk = preg_replace('/[^a-z0-9_\-]/', '', str_replace('-', '_', $fk));
        static $map = array(
            'about_us' => 'about_us',
            'aboutus' => 'about_us',
            'about_uss' => 'about_us',
            'contact_us' => 'contact_us',
            'contactus' => 'contact_us',
            'privacy_policy' => 'privacy_policy',
            'privacypolicy' => 'privacy_policy',
            'terms_and_conditions' => 'terms_and_conditions',
            'terms_conditions' => 'terms_and_conditions',
            'terms' => 'terms_and_conditions',
        );
        if (!isset($map[$fk])) {
            return '';
        }
        return base_url('webshop/' . $map[$fk]);
    }
}

if (!function_exists('webshop_storefront_resolve_row_display_value')) {
    /**
     * Apply default internal links for known page field keys when value is empty.
     *
     * @param string $field_key
     * @param string $value
     * @return string
     */
    function webshop_storefront_resolve_row_display_value($field_key, $value) {
        $val = trim((string) $value);
        if ($val !== '') {
            return $val;
        }
        $link = webshop_storefront_default_link_for_field_key($field_key);
        return $link !== '' ? $link : '';
    }
}

if (!function_exists('webshop_footer_content_row_should_display')) {
    /**
     * Skip empty unknown footer columns (no value, no POS fallback semantics).
     *
     * @param array<string, mixed> $row field_key, label, value, …
     * @return bool
     */
    function webshop_footer_content_row_should_display(array $row) {
        $val = isset($row['value']) ? trim((string) $row['value']) : '';
        if ($val !== '') {
            return true;
        }
        $fk = isset($row['field_key']) ? (string) $row['field_key'] : '';
        $lab = isset($row['label']) ? (string) $row['label'] : '';
        return webshop_footer_row_has_semantic_fallback($fk, $lab);
    }
}

if (!function_exists('webshop_header_row_is_structural_field')) {
    /**
     * Header rows handled elsewhere (logo, banner, favicon, social) — not display strips.
     *
     * @param string $field_key
     * @return bool
     */
    function webshop_header_row_is_structural_field($field_key) {
        $fk = strtolower(trim((string) $field_key));
        if ($fk === '') {
            return true;
        }
        if (webshop_footer_row_is_header_only_field($fk)) {
            return true;
        }
        if (function_exists('webshop_footer_row_is_social_field') && webshop_footer_row_is_social_field($fk)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('webshop_header_slot_kind_for_field_key')) {
    /**
     * Map admin field_key to header strip placement.
     *
     * @param string $field_key
     * @return string announcement|top_html|phone|''
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
}

if (!function_exists('webshop_header_slot_kind_resolve')) {
    /**
     * Resolve strip kind; unknown keys with content render as top_html (CMS custom header text).
     *
     * @param string $field_key
     * @param string $value       Resolved display value
     * @return string announcement|top_html|phone|''
     */
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
}

if (!function_exists('webshop_header_gather_display_slots')) {
    /**
     * Active HEADER rows for storefront strips (announcement / HTML bar / phone), sorted.
     *
     * @return array{announcement: array<int, array>, top_html: array<int, array>, phone: array<int, array>}
     */
    function webshop_header_gather_display_slots() {
        $slots = array(
            'announcement' => array(),
            'top_html'     => array(),
            'phone'        => array(),
        );
        foreach (webshop_website_setting_section_rows('header') as $item) {
            $fk = webshop_ws_row_field_key($item);
            if ($fk === '' || webshop_header_row_is_structural_field($fk)) {
                continue;
            }
            $val = webshop_storefront_resolve_row_display_value($fk, webshop_ws_row_value_string($item));
            $kind = webshop_header_slot_kind_resolve($fk, $val);
            if ($kind === '') {
                continue;
            }
            if (trim($val) === '' && $kind !== 'phone') {
                continue;
            }
            $row = array(
                'field_key'  => $fk,
                'label'      => webshop_ws_row_label_string($item, $fk),
                'value'      => $val,
                'sort_order' => webshop_ws_row_sort_order($item),
            );
            $slots[$kind][] = $row;
        }
        foreach (array_keys($slots) as $k) {
            usort($slots[$k], function ($a, $b) {
                $sa = isset($a['sort_order']) ? (int) $a['sort_order'] : 0;
                $sb = isset($b['sort_order']) ? (int) $b['sort_order'] : 0;
                if ($sa !== $sb) {
                    return $sa - $sb;
                }
                return strcmp(isset($a['field_key']) ? $a['field_key'] : '', isset($b['field_key']) ? $b['field_key'] : '');
            });
        }
        return $slots;
    }
}

if (!function_exists('webshop_ws_website_setting_bundles')) {
    /**
     * Rows from getsettings ($api_website_setting) plus controller view data when present.
     *
     * @return array<int, array<int, mixed>>
     */
    function webshop_ws_website_setting_bundles() {
        $bundles = array();
        if (!function_exists('get_instance')) {
            return $bundles;
        }
        $CI = get_instance();
        if (isset($CI->api_website_setting) && is_array($CI->api_website_setting)) {
            $bundles[] = webshop_filter_active_website_setting_rows($CI->api_website_setting);
        }
        if (isset($CI->data['website_setting']) && is_array($CI->data['website_setting'])) {
            $bundles[] = webshop_filter_active_website_setting_rows($CI->data['website_setting']);
        }
        return $bundles;
    }
}

if (!function_exists('webshop_api_website_setting_sections')) {
    /**
     * Grouped storefront slots from getsettings (`website_setting_sections`: header / footer rows).
     *
     * @return object|array { header: array, footer: array }
     */
    function webshop_api_website_setting_sections() {
        if (!function_exists('get_instance')) {
            return (object) array('header' => array(), 'footer' => array());
        }
        $CI = get_instance();
        
        $api_sections = (object) array('header' => array(), 'footer' => array());
        if (isset($CI->api_website_setting_sections)) {
            $api_sections = (object) $CI->api_website_setting_sections;
        }
        if (!isset($api_sections->header) || !is_array($api_sections->header)) {
            $api_sections->header = isset($api_sections->header) ? webshop_normalize_setting_section_row_list($api_sections->header) : array();
        }
        if (!isset($api_sections->footer) || !is_array($api_sections->footer)) {
            $api_sections->footer = isset($api_sections->footer) ? webshop_normalize_setting_section_row_list($api_sections->footer) : array();
        }

        // Merge local DB table if it exists (optional; production webshopapi is API-only)
        if (isset($CI->db)) {
            $table = 'sma_webshop_header_footer';
            if ($CI->db->table_exists($table)) {
                $q = $CI->db->where('is_active', 1)->order_by('sort_order', 'ASC')->get($table);
                if ($q && $q->num_rows() > 0) {
                    $rows = $q->result_array();
                    foreach ($rows as $row) {
                        $st = isset($row['section_type']) ? strtolower(trim($row['section_type'])) : '';
                        if ($st === '') continue;
                        
                        $row_key = function_exists('webshop_ws_row_field_key') ? webshop_ws_row_field_key($row) : (isset($row['field_key']) ? (string)$row['field_key'] : '');
                        
                        if ($row_key === '') {
                            if (!isset($api_sections->$st)) {
                                $api_sections->$st = array();
                            }
                            $api_sections->{$st}[] = $row;
                            continue;
                        }
                        if (!isset($api_sections->$st)) {
                            $api_sections->$st = array();
                        }
                        $merged = false;
                        foreach ($api_sections->{$st} as $idx => $existing) {
                            $ek = function_exists('webshop_ws_row_field_key') ? webshop_ws_row_field_key($existing) : '';
                            if ($ek === '' || $ek !== $row_key) {
                                continue;
                            }
                            $merged = true;
                            $api_val = function_exists('webshop_ws_row_value_string') ? webshop_ws_row_value_string($existing) : '';
                            $db_val  = function_exists('webshop_ws_row_value_string') ? webshop_ws_row_value_string($row) : '';
                            if ($db_val !== '' || $api_val === '') {
                                $api_sections->{$st}[$idx] = $row;
                            }
                            break;
                        }
                        if (!$merged) {
                            $api_sections->{$st}[] = $row;
                        }
                    }
                }
            }
        }

        return $api_sections;
    }
}

if (!function_exists('webshop_normalize_setting_section_row_list')) {
    /**
     * Ensure header/footer section payload is a 0..n-1 list of row objects (handles JSON object-vs-array quirks).
     *
     * @param mixed $rows
     * @return array<int, mixed>
     */
    function webshop_normalize_setting_section_row_list($rows) {
        if ($rows === null || $rows === '') {
            return array();
        }
        if (is_array($rows)) {
            return array_values($rows);
        }
        if (!is_object($rows)) {
            return array();
        }
        foreach (array('fields', 'Fields') as $k) {
            if (isset($rows->$k) && trim((string) $rows->$k) !== '') {
                return array($rows);
            }
        }
        $vars = get_object_vars($rows);
        if ($vars === array()) {
            return array();
        }
        $keys = array_keys($vars);
        $numeric_list = true;
        foreach ($keys as $k) {
            if (!is_int($k) && !(is_string($k) && ctype_digit((string) $k))) {
                $numeric_list = false;
                break;
            }
        }
        if ($numeric_list) {
            return array_values($vars);
        }
        return array($rows);
    }
}

if (!function_exists('webshop_website_setting_section_rows')) {
    /**
     * Active rows for one section (header or footer), same shape as flat website_setting rows plus section_type/label/sort_order when present.
     *
     * @param string $section header|footer
     * @return array<int, mixed>
     */
    function webshop_website_setting_section_rows($section) {
        $section = strtolower(trim((string) $section));
        if ($section === '') {
            return array();
        }
        $wrap = webshop_api_website_setting_sections();
        $rows = array();
        if (is_array($wrap)) {
            $rows = isset($wrap[$section]) ? $wrap[$section] : array();
        } elseif (is_object($wrap)) {
            $rows = isset($wrap->$section) ? $wrap->$section : array();
        }
        $normalized = webshop_normalize_setting_section_row_list($rows);
        $out = array();
        foreach ($normalized as $item) {
            if (webshop_ws_row_is_active($item)) {
                $out[] = $item;
            }
        }
        usort($out, function ($a, $b) {
            return webshop_ws_row_sort_order($a) - webshop_ws_row_sort_order($b);
        });
        return $out;
    }
}

if (!function_exists('webshop_website_setting_lookup_row_in_section')) {
    /**
     * Find first row by field key within a single section (prefer when API exposes website_setting_sections).
     *
     * @param string $field_key e.g. logo_image, about_us
     * @param string $section   header|footer
     * @return object|null
     */
    function webshop_website_setting_lookup_row_in_section($field_key, $section) {
        $field_key = strtolower(trim((string) $field_key));
        if ($field_key === '') {
            return null;
        }
        foreach (webshop_website_setting_section_rows($section) as $item) {
            $f = webshop_ws_row_field_key($item);
            if ($f !== $field_key) {
                continue;
            }
            $v = webshop_storefront_resolve_row_display_value($f, webshop_ws_row_value_string($item));
            if ($v !== '' || in_array($f, array('logo_image', 'banner_image', 'favicon'), true)) {
                return is_object($item) ? $item : (object) (array) $item;
            }
        }
        return null;
    }
}

if (!function_exists('webshop_website_setting_lookup_row')) {
    /**
     * Find first website_setting row by fields key (ElintOm Storefront header & footer / sma_website_setting).
     *
     * @param string $field_key e.g. logo_image, about_us
     * @return object|null     Row with fields, value, icons
     */
    function webshop_website_setting_lookup_row($field_key) {
        $field_key = strtolower(trim((string) $field_key));
        if ($field_key === '' || !function_exists('get_instance')) {
            return null;
        }
        foreach (webshop_ws_website_setting_bundles() as $items) {
            foreach ($items as $item) {
                $f = webshop_ws_row_field_key($item);
                if ($f !== $field_key) {
                    continue;
                }
                if (!webshop_ws_row_is_active($item)) {
                    continue;
                }
                $v = webshop_storefront_resolve_row_display_value($f, webshop_ws_row_value_string($item));
                if ($v !== '' || in_array($f, array('logo_image', 'banner_image', 'favicon'), true)) {
                    return is_object($item) ? $item : (object) (array) $item;
                }
            }
        }
        return null;
    }
}

if (!function_exists('webshop_footer_sort_identity_rows')) {
    /**
     * Stable sort for footer columns: DB sort_order then field_key (supports dynamic footer rows).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    function webshop_footer_sort_identity_rows(array $rows) {
        usort($rows, function ($a, $b) {
            $sa = isset($a['sort_order']) ? (int) $a['sort_order'] : 0;
            $sb = isset($b['sort_order']) ? (int) $b['sort_order'] : 0;
            if ($sa !== $sb) {
                return $sa - $sb;
            }
            $fa = isset($a['field_key']) ? (string) $a['field_key'] : '';
            $fb = isset($b['field_key']) ? (string) $b['field_key'] : '';
            return strcmp($fa, $fb);
        });
        return $rows;
    }
}

if (!function_exists('webshop_footer_fill_row_fallbacks')) {
    /**
     * When storefront footer rows exist in ElintOm `webshop_header_footer` but Value is empty,
     * fill from getsettings merged biller/POS fields on MY_Controller::$Settings (Eshop_model::getPosSettings join).
     *
     * @param array<int, array{field_key:string,label:string,value:string,icons:string}> $rows
     * @return array<int, array{field_key:string,label:string,value:string,icons:string}>
     */
    function webshop_footer_fill_row_fallbacks(array $rows) {
        if ($rows === array() || !function_exists('get_instance')) {
            return $rows;
        }
        $CI = get_instance();
        $S = (isset($CI->Settings) && is_object($CI->Settings)) ? $CI->Settings : new stdClass();

        foreach ($rows as $i => $row) {
            if (!is_array($row) || !isset($row['field_key'])) {
                continue;
            }
            $cur = isset($row['value']) ? trim((string) $row['value']) : '';
            if ($cur !== '') {
                continue;
            }
            $fk = strtolower(trim((string) $row['field_key']));
            $lab = isset($row['label']) ? strtolower(trim((string) $row['label'])) : '';
            $hint = $fk . ' ' . $lab;

            $fill = '';
            if (preg_match('/phone|tel|mobile|hotline|whatsapp|fax|call us|contact number/i', $hint)) {
                foreach (array('phone', 'tel', 'mobile', 'merchant_phone') as $k) {
                    if (isset($S->$k) && trim((string) $S->$k) !== '') {
                        $fill = trim((string) $S->$k);
                        break;
                    }
                }
            } elseif (preg_match('/address|location|visit|showroom|branch|office|find us/i', $hint)) {
                $parts = array();
                foreach (array('address', 'city', 'state', 'postal_code', 'country') as $k) {
                    if (isset($S->$k) && trim((string) $S->$k) !== '') {
                        $parts[] = trim((string) $S->$k);
                    }
                }
                $fill = implode(', ', $parts);
            } elseif (preg_match('/about|intro|who we|company|story|description|overview|mission|why/i', $hint)) {
                $name = isset($S->site_name) ? trim((string) $S->site_name) : '';
                $tag = '';
                foreach (array('biller_name', 'company') as $k) {
                    if (isset($S->$k) && trim((string) $S->$k) !== '') {
                        $bn = trim((string) $S->$k);
                        if ($bn !== '' && $bn !== $name) {
                            $tag = $bn;
                            break;
                        }
                    }
                }
                $chunks = array();
                if ($name !== '') {
                    $chunks[] = $name;
                }
                if ($tag !== '') {
                    $chunks[] = $tag;
                }
                foreach (array('cf_title1', 'cf_title2') as $k) {
                    if (isset($S->$k) && trim((string) $S->$k) !== '') {
                        $chunks[] = trim((string) $S->$k);
                        break;
                    }
                }
                $uniq = array();
                foreach ($chunks as $c) {
                    if ($c !== '' && !in_array($c, $uniq, true)) {
                        $uniq[] = $c;
                    }
                }
                $fill = implode(' — ', $uniq);
            } elseif (preg_match('/email|e-mail|mail\W|support/i', $hint)) {
                foreach (array('default_email', 'email', 'account_email') as $k) {
                    if (isset($S->$k) && trim((string) $S->$k) !== '') {
                        $fill = trim((string) $S->$k);
                        break;
                    }
                }
            }

            if ($fill !== '') {
                $rows[$i]['value'] = $fill;
            }
        }

        return $rows;
    }
}

if (!function_exists('webshop_footer_identity_rows')) {
    /**
     * All footer storefront slots from API: field_key, admin label, value, icons — no hardcoded field names.
     * Uses website_setting_sections.footer when present; otherwise flat website_setting minus keys that appear in header section.
     * Empty slot values are backfilled from getsettings biller/POS fields when possible (see webshop_footer_fill_row_fallbacks).
     *
     * @return array<int, array{field_key:string,label:string,value:string,icons:string}>
     */
    function webshop_footer_identity_rows() {
        $out = array();
        $sections_obj = webshop_api_website_setting_sections();
        $has_sections = false;
        $seen_keys = array();

        foreach ($sections_obj as $section_name => $section_rows) {
            $sn = strtolower(trim((string) $section_name));
            // Footer template only — header slots (logo_image, etc.) are not footer columns.
            if ($sn !== 'footer') {
                continue;
            }

            $normalized = webshop_normalize_setting_section_row_list($section_rows);
            if (!empty($normalized)) {
                $has_sections = true;
                foreach ($normalized as $item) {
                    if (!webshop_ws_row_is_active($item)) {
                        continue;
                    }
                    $fk  = webshop_ws_row_field_key($item);
                    $val = webshop_storefront_resolve_row_display_value($fk, webshop_ws_row_value_string($item));
                    $lab = webshop_ws_row_label_string($item, $fk);

                    if ($fk === '' && $val === '' && $lab === '') {
                        continue;
                    }
                    if ($fk !== '' && webshop_footer_row_is_header_only_field($fk)) {
                        continue;
                    }

                    if ($fk !== '') {
                        $seen_keys[$fk] = true;
                    }

                    $out[] = array(
                        'field_key'   => ($fk !== '' ? $fk : 'db_row_' . count($out)),
                        'label'       => $lab,
                        'value'       => $val,
                        'icons'       => webshop_ws_row_icons_string($item),
                        'sort_order'  => function_exists('webshop_ws_row_sort_order') ? webshop_ws_row_sort_order($item) : 0,
                        'section'     => $sn,
                    );
                }
            }
        }

        // Always merge POS fallbacks if not already seen in sections
        $header_keys = array();
        if (function_exists('webshop_website_setting_section_rows')) {
            foreach (webshop_website_setting_section_rows('header') as $hitem) {
                $hf = webshop_ws_row_field_key($hitem);
                if ($hf !== '') {
                    $header_keys[$hf] = true;
                }
            }
        }

        foreach (webshop_ws_website_setting_bundles() as $items) {
            foreach ($items as $item) {
                $fk = webshop_ws_row_field_key($item);
                if ($fk === '' || isset($seen_keys[$fk])) {
                    continue;
                }
                if (isset($header_keys[$fk])) {
                    continue;
                }
                if (webshop_footer_row_is_header_only_field($fk)) {
                    continue;
                }
                $val = webshop_storefront_resolve_row_display_value($fk, webshop_ws_row_value_string($item));

                $seen_keys[$fk] = true;
                $out[] = array(
                    'field_key'   => $fk,
                    'label'       => webshop_ws_row_label_string($item, $fk),
                    'value'       => $val,
                    'icons'       => webshop_ws_row_icons_string($item),
                    'sort_order'  => function_exists('webshop_ws_row_sort_order') ? webshop_ws_row_sort_order($item) : 0,
                    'section'     => 'footer', // Default section for fallbacks
                );
            }
        }

        $out = webshop_footer_fill_row_fallbacks(webshop_footer_sort_identity_rows($out));
        return array_values(array_filter($out, function ($row) {
            return is_array($row) && webshop_footer_content_row_should_display($row);
        }));
    }
}

if (!function_exists('webshop_resolve_storefront_logo_image_url')) {
    /**
     * Absolute URL for logo_image row from getsettings website_setting[] (Storefront manager in ElintOm).
     *
     * @param string $uploads_base
     * @return string
     */
    function webshop_resolve_storefront_logo_image_url($uploads_base) {
        $row = function_exists('webshop_website_setting_lookup_row_in_section')
            ? webshop_website_setting_lookup_row_in_section('logo_image', 'header') : null;
        if (!$row) {
            $row = webshop_website_setting_lookup_row('logo_image');
        }
        if (!$row) {
            return '';
        }
        return webshop_resolve_storefront_media_path(webshop_ws_row_value_string($row), $uploads_base);
    }
}

if (!function_exists('webshop_resolve_storefront_media_path')) {
    /**
     * Resolve logo_image / favicon value from sma_webshop_header_footer.
     * Supports: absolute URL, assets/webshop/… theme path, images/… under active theme, ElintOm uploads path.
     *
     * @param string $path
     * @param string $uploads_base
     * @return string
     */
    function webshop_resolve_storefront_media_path($path, $uploads_base = '') {
        $p = trim((string) $path);
        if ($p === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }
        $p = str_replace('\\', '/', $p);
        if (stripos($p, 'assets/webshop/') === 0) {
            return rtrim(base_url(), '/') . '/' . ltrim($p, '/');
        }
        if (strpos($p, 'assets/') === 0) {
            return rtrim(base_url(), '/') . '/' . ltrim($p, '/');
        }
        if (preg_match('#^images/|^css/|^js/#i', $p) && function_exists('webshop_theme_assets_url')) {
            return webshop_theme_assets_url($p);
        }
        if ($uploads_base !== '') {
            return webshop_media_src((string) $uploads_base, $p);
        }
        return '';
    }
}

if (!function_exists('webshop_resolve_storefront_favicon_url')) {
    /**
     * @param string $uploads_base
     * @return string
     */
    function webshop_resolve_storefront_favicon_url($uploads_base = '') {
        $row = function_exists('webshop_website_setting_lookup_row_in_section')
            ? webshop_website_setting_lookup_row_in_section('favicon', 'header') : null;
        if (!$row) {
            $row = webshop_website_setting_lookup_row('favicon');
        }
        if (!$row) {
            return function_exists('webshop_theme_assets_url') ? webshop_theme_assets_url('images/favicon.png') : '';
        }
        $url = webshop_resolve_storefront_media_path(webshop_ws_row_value_string($row), $uploads_base);
        if ($url !== '') {
            return $url;
        }
        return function_exists('webshop_theme_assets_url') ? webshop_theme_assets_url('images/favicon.png') : '';
    }
}

if (!function_exists('webshop_footer_row_is_header_only_field')) {
    /**
     * Storefront keys that belong in the header only — never as footer text columns.
     *
     * @param string $field_key
     * @return bool
     */
    function webshop_footer_row_is_header_only_field($field_key) {
        static $keys = array('logo_image', 'banner_image', 'favicon', 'header_logo', 'store_logo', 'site_logo');
        return in_array(strtolower(trim((string) $field_key)), $keys, true);
    }
}

if (!function_exists('webshop_footer_row_display_icon_class')) {
    /**
     * Icon class for a footer content row: DB icons column, else defaults by field_key.
     *
     * @param string $field_key
     * @param string $icons_from_db
     * @return string
     */
    function webshop_footer_row_display_icon_class($field_key, $icons_from_db = '') {
        $icon = trim((string) $icons_from_db);
        if ($icon !== '' && preg_match('/\bfa[\s-]/i', $icon)) {
            return $icon;
        }
        $fk = strtolower(trim((string) $field_key));
        if (preg_match('/phone|tel|mobile|hotline|whatsapp|fax|call/i', $fk)) {
            return 'fa fa-phone';
        }
        if (preg_match('/address|location|visit|office|branch/i', $fk)) {
            return 'fa fa-map-marker';
        }
        if (preg_match('/email|e-mail|mail/i', $fk)) {
            return 'fa fa-envelope';
        }
        if (preg_match('/about|intro|company|story|mission/i', $fk)) {
            return 'fa fa-info-circle';
        }
        return $icon;
    }
}

if (!function_exists('webshop_footer_gather_display_rows')) {
    /**
     * Footer view data: content columns + social icons from sma_webshop_header_footer (footer section, social in header too).
     * One column per active footer row (sort_order); values from API/DB with POS fallbacks when value is NULL.
     *
     * @return array{content: array<int, array>, social: array<int, array>}
     */
    function webshop_footer_gather_display_rows() {
        $content = array();
        $social = array();
        $seen_social = array();

        $push = function ($item, $section) use (&$content, &$social, &$seen_social) {
            $fk = webshop_ws_row_field_key($item);
            if ($fk === '' || webshop_footer_row_is_header_only_field($fk)) {
                return;
            }
            $row = array(
                'field_key'   => $fk,
                'label'       => webshop_ws_row_label_string($item, $fk),
                'value'       => webshop_storefront_resolve_row_display_value($fk, webshop_ws_row_value_string($item)),
                'icons'       => webshop_ws_row_icons_string($item),
                'sort_order'  => webshop_ws_row_sort_order($item),
                'section'     => $section,
            );
            if (webshop_footer_row_is_social_field($fk)) {
                if (isset($seen_social[$fk])) {
                    return;
                }
                $seen_social[$fk] = true;
                $social[] = $row;
                return;
            }
            $content[] = $row;
        };

        foreach (webshop_website_setting_section_rows('footer') as $item) {
            $push($item, 'footer');
        }
        foreach (webshop_website_setting_section_rows('header') as $item) {
            $fk = webshop_ws_row_field_key($item);
            if ($fk !== '' && webshop_footer_row_is_social_field($fk)) {
                $push($item, 'header');
            }
        }

        $seen_content = array();
        foreach ($content as $r) {
            $seen_content[$r['field_key']] = true;
        }
        $header_keys = array();
        foreach (webshop_website_setting_section_rows('header') as $hitem) {
            $hf = webshop_ws_row_field_key($hitem);
            if ($hf !== '') {
                $header_keys[$hf] = true;
            }
        }
        foreach (webshop_ws_website_setting_bundles() as $items) {
            foreach ($items as $item) {
                $fk = webshop_ws_row_field_key($item);
                if ($fk === '' || webshop_footer_row_is_header_only_field($fk)) {
                    continue;
                }
                if (webshop_footer_row_is_social_field($fk)) {
                    if (!isset($seen_social[$fk])) {
                        $seen_social[$fk] = true;
                        $social[] = array(
                            'field_key'   => $fk,
                            'label'       => webshop_ws_row_label_string($item, $fk),
                            'value'       => webshop_storefront_resolve_row_display_value($fk, webshop_ws_row_value_string($item)),
                            'icons'       => webshop_ws_row_icons_string($item),
                            'sort_order'  => webshop_ws_row_sort_order($item),
                            'section'     => 'footer',
                        );
                    }
                    continue;
                }
                if (isset($seen_content[$fk]) || isset($header_keys[$fk])) {
                    continue;
                }
                $seen_content[$fk] = true;
                $content[] = array(
                    'field_key'   => $fk,
                    'label'       => webshop_ws_row_label_string($item, $fk),
                    'value'       => webshop_storefront_resolve_row_display_value($fk, webshop_ws_row_value_string($item)),
                    'icons'       => webshop_ws_row_icons_string($item),
                    'sort_order'  => webshop_ws_row_sort_order($item),
                    'section'     => 'footer',
                );
            }
        }

        $social = webshop_footer_sort_identity_rows($social);
        $content = webshop_footer_fill_row_fallbacks(webshop_footer_sort_identity_rows($content));
        $content = array_values(array_filter($content, function ($row) {
            return is_array($row) && webshop_footer_content_row_should_display($row);
        }));

        return array('content' => $content, 'social' => $social);
    }
}

if (!function_exists('webshop_footer_row_is_social_field')) {
    /**
     * True for ElintOm storefront keys (media_facebook_link, media_instagram_link, …) and common aliases.
     *
     * @param string $field_key
     * @return bool
     */
    function webshop_footer_row_is_social_field($field_key) {
        $fk = strtolower(trim((string) $field_key));
        if ($fk === '') {
            return false;
        }
        if (preg_match('/^media_[a-z0-9_]+_link$/', $fk)) {
            return true;
        }
        if (preg_match('/^(facebook|fb|instagram|insta|ig|twitter|x_twitter|linkedin|youtube|tiktok)(?:_link|_url|_page)?$/', $fk)) {
            return true;
        }
        if (preg_match('/^(social_)?(facebook|fb|instagram|insta|twitter|linkedin|youtube|tiktok)(?:_link|_url)?$/', $fk)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('webshop_footer_social_icon_class')) {
    /**
     * Font Awesome 4 icon class for a social field_key (footer uses techmarket-font-awesome.css).
     *
     * @param string $field_key
     * @return string e.g. fa fa-instagram
     */
    function webshop_footer_social_icon_class($field_key) {
        $fk = strtolower(trim((string) $field_key));
        if (strpos($fk, 'facebook') !== false || strpos($fk, 'fb') !== false) {
            return 'fa fa-facebook-f';
        }
        if (strpos($fk, 'instagram') !== false || strpos($fk, 'insta') !== false || $fk === 'ig') {
            return 'fa fa-instagram';
        }
        if (strpos($fk, 'twitter') !== false || strpos($fk, '_x_') !== false) {
            return 'fa fa-twitter';
        }
        if (strpos($fk, 'youtube') !== false) {
            return 'fa fa-youtube-play';
        }
        if (strpos($fk, 'linkedin') !== false) {
            return 'fa fa-linkedin';
        }
        if (strpos($fk, 'tiktok') !== false) {
            return 'fa fa-link';
        }
        return 'fa fa-link';
    }
}

if (!function_exists('webshop_footer_external_url')) {
    /**
     * Normalize footer/social values that may be a bare URL or legacy HTML snippet with href=.
     *
     * @param string $raw
     * @return string URL or empty
     */
    function webshop_footer_external_url($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }
        if (preg_match('#https?://[^\s"\'<>]+#i', $raw, $m)) {
            return trim($m[0]);
        }
        $bare = preg_replace('/\s+/', '', $raw);
        if (preg_match('#^(?:www\.)?[a-z0-9][-a-z0-9.]*\.[a-z]{2,}(?:/[^\s]*)?$#i', $bare)) {
            return 'https://' . ltrim($bare, '/');
        }
        return '';
    }
}

if (!function_exists('webshop_footer_row_link_href')) {
    /**
     * Resolved href for a footer slot (http(s), tel:, or empty). Used to make icon/label clickable without duplicating body text.
     *
     * @param string $field_key
     * @param string $value
     * @return string safe attribute value or empty
     */
    function webshop_footer_row_link_href($field_key, $value) {
        $fk = strtolower(trim((string) $field_key));
        $val = trim((string) $value);
        if ($val === '') {
            return '';
        }
        if (function_exists('webshop_footer_row_is_social_field') && webshop_footer_row_is_social_field($fk)) {
            $u = webshop_footer_external_url($val);
            return $u !== '' ? $u : '';
        }
        if (preg_match('/^media_[a-z0-9_]+_link$/', $fk)) {
            $u = webshop_footer_external_url($val);
            return $u !== '' ? $u : '';
        }
        if (preg_match('#^https?://\S+$#i', $val)) {
            return $val;
        }
        if (preg_match('/phone|tel|mobile|hotline|whatsapp|fax/i', $fk)) {
            $tel = preg_replace('/[^0-9+]/', '', $val);
            return $tel !== '' ? 'tel:' . $tel : '';
        }
        if (preg_match('/^[\+]?[0-9][0-9\s\-\(\)\.]{6,}$/', $val)) {
            $tel = preg_replace('/[^0-9+]/', '', $val);
            return $tel !== '' ? 'tel:' . $tel : '';
        }
        $ext = webshop_footer_external_url($val);
        if ($ext !== '' && preg_match('#^https?://#i', $ext)) {
            return $ext;
        }
        return '';
    }
}

if (!function_exists('webshop_footer_value_is_link_only')) {
    /**
     * True when value is only a linkable string (no rich HTML) so body can be hidden when icon/label carry the link.
     *
     * @param string $field_key
     * @param string $value
     * @return bool
     */
    function webshop_footer_value_is_link_only($field_key, $value) {
        $val = trim((string) $value);
        if ($val === '') {
            return false;
        }
        if (preg_match('/<[a-z][\s\S]/i', $val)) {
            return false;
        }
        $fk = strtolower(trim((string) $field_key));
        if (preg_match('/^media_[a-z0-9_]+_link$/', $fk)) {
            return true;
        }
        if (preg_match('#^https?://\S+$#i', $val)) {
            return true;
        }
        if (preg_match('/phone|tel|mobile|hotline|whatsapp|fax/i', $fk)) {
            return (bool) preg_match('/^[\+]?[0-9][0-9\s\-\(\)\.]{6,}$/', $val);
        }
        if (preg_match('/^[\+]?[0-9][0-9\s\-\(\)\.]{6,}$/', $val)) {
            return true;
        }
        return webshop_footer_external_url($val) !== '';
    }
}

if (!function_exists('webshop_footer_row_body_html')) {
    /**
     * Safe HTML for one footer cell: URLs / media_*_link / tel heuristics; rich text uses CMS-style preparation (not escaped).
     *
     * @param string $field_key
     * @param string $value
     * @param string $uploads_base Trailing slash; used to resolve relative media in HTML (same as CMS body).
     * @return string HTML fragment
     */
    function webshop_footer_row_body_html($field_key, $value, $uploads_base = '', $label = '') {
        $fk = strtolower(trim((string) $field_key));
        $val = trim((string) $value);
        $lab = trim((string) $label);
        
        $prefix = '';
        if ($lab !== '') {
            $prefix = '<span class="gp-footer-label" style="font-weight: 600;">' . htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') . ':</span> ';
        }
        
        if ($val === '') {
            return $prefix; // Show label even if value is empty if requested
        }
        if (preg_match('/^media_[a-z0-9_]+_link$/', $fk)) {
            $url = webshop_footer_external_url($val);
            if ($url === '') {
                return nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
            }
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
                . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        $trim = trim($val);
        if (preg_match('#^https?://\S+$#i', $trim)) {
            return '<a href="' . htmlspecialchars($trim, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
                . htmlspecialchars($trim, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        if (preg_match('/phone|tel|mobile|hotline|whatsapp|fax/i', $fk)) {
            $tel = preg_replace('/[^0-9+]/', '', $val);
            if ($tel !== '') {
                return '<a class="gp-footer-phone" href="tel:' . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</a>';
            }
        }
        if (preg_match('/^[\+]?[0-9][0-9\s\-\(\)\.]{6,}$/', $val)) {
            $tel = preg_replace('/[^0-9+]/', '', $val);
            if ($tel !== '') {
                return '<a class="gp-footer-phone" href="tel:' . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</a>';
            }
        }
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i', $val)) {
            $src = (strpos($val, 'http') === 0) ? $val : webshop_media_src($uploads_base, $val);
            return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" class="gp-footer-img" style="max-height: 40px; width: auto; display: block; margin-bottom: 5px;" alt="' . htmlspecialchars($fk, ENT_QUOTES, 'UTF-8') . '">';
        }
        $looks_like_html = (bool) preg_match('/<[a-z][a-z0-9]{0,24}\b/i', $val)
            || (bool) preg_match('/<\/[a-z][a-z0-9]{0,24}>/i', $val);
        if (!$looks_like_html && strpos($val, '&lt;') !== false && strpos($val, '&gt;') !== false) {
            $looks_like_html = (bool) preg_match('/&lt;[a-z][a-z0-9]{0,24}\b/i', $val);
        }
        if ($looks_like_html && function_exists('webshop_prepare_cms_html_for_output')) {
            $prepared = webshop_prepare_cms_html_for_output($val, $uploads_base);
            return trim($prepared);
        }
        return $prefix . nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('webshop_footer_media_link_rows')) {
    /**
     * Rows with fields like media_facebook_link, media_instagram_link from website_setting.
     *
     * @return array<int, array{label:string,url:string,field:string}>
     */
    function webshop_footer_media_link_rows() {
        $out = array();
        if (!function_exists('get_instance')) {
            return $out;
        }
        $seen_fields = array();
        $bundles = array();
        if (function_exists('webshop_website_setting_section_rows')) {
            $footer_only = webshop_website_setting_section_rows('footer');
            if (!empty($footer_only)) {
                $bundles[] = $footer_only;
            }
        }
        if (empty($bundles)) {
            $bundles = webshop_ws_website_setting_bundles();
        }
        if (function_exists('webshop_website_setting_section_rows')) {
            foreach (webshop_website_setting_section_rows('header') as $item) {
                $bundles[] = array($item);
            }
        }
        foreach ($bundles as $items) {
            foreach ($items as $item) {
                $f = webshop_ws_row_field_key($item);
                if ($f === '' || !webshop_footer_row_is_social_field($f)) {
                    continue;
                }
                if (isset($seen_fields[$f])) {
                    continue;
                }
                $rawVal = webshop_ws_row_value_string($item);
                $url = function_exists('webshop_footer_row_link_href')
                    ? webshop_footer_row_link_href($f, $rawVal)
                    : webshop_footer_external_url($rawVal);
                if ($url === '') {
                    continue;
                }
                $seen_fields[$f] = true;
                $inner = preg_replace('/^media_/', '', $f);
                $inner = preg_replace('/_link$/', '', $inner);
                $label = ucwords(str_replace('_', ' ', $inner));
                $out[] = array(
                    'label' => $label,
                    'url'   => $url,
                    'field' => $f,
                );
            }
        }
        return $out;
    }
}

if (!function_exists('webshop_resolve_header_logo_url')) {
    /**
     * Public webshop header logo: **only** ElintOm Storefront `logo_image` (getsettings `website_setting[]` / sma_website_setting).
     * CMS page logos, POS Settings logos, and legacy webshop_settings keys are intentionally not used.
     *
     * @param string      $uploads_base       View $uploads / mdata uploads root
     * @param object|null $Settings           Unused (signature retained for callers)
     * @param object|null $webshop_settings   Unused
     * @param string      $page_logo_cms      Unused (CMS logos not applied to header)
     * @return string URL or empty (header shows text shop name)
     */
    function webshop_resolve_header_logo_url($uploads_base, $Settings, $webshop_settings, $page_logo_cms = '') {
        return webshop_resolve_storefront_logo_image_url($uploads_base);
    }
}

if (!function_exists('webshop_phone_digit_variants')) {
    /**
     * Build phone lookup variants so register (local 10-digit) and forgot-password (+91 prefix) match ElintOm DB.
     *
     * @param string $raw
     * @param string $dial_code      e.g. 91 (no +)
     * @param int    $local_digits   e.g. 10 for India
     * @return string[] Unique digit strings, local first (matches register_check / sma_customers.phone)
     */
    function webshop_phone_digit_variants($raw, $dial_code = '91', $local_digits = 10) {
        $digits = preg_replace('/\D/', '', (string) $raw);
        if ($digits === '') {
            return array();
        }
        $dial_code = preg_replace('/\D/', '', (string) $dial_code);
        $local_digits = max(0, (int) $local_digits);

        $local = $digits;
        if ($dial_code !== '' && strpos($digits, $dial_code) === 0 && strlen($digits) > $local_digits) {
            $local = substr($digits, strlen($dial_code));
        }
        if ($local_digits > 0 && strlen($local) > $local_digits) {
            $local = substr($local, -$local_digits);
        }

        $variants = array();
        if ($local !== '') {
            $variants[] = $local;
        }
        if ($dial_code !== '' && $local !== '') {
            $intl = $dial_code . $local;
            if (!in_array($intl, $variants, true)) {
                $variants[] = $intl;
            }
        }
        if ($digits !== '' && !in_array($digits, $variants, true)) {
            $variants[] = $digits;
        }
        return array_values($variants);
    }
}

if (!function_exists('webshop_mask_phone_for_log')) {
    /**
     * Mask phone/mobile for log lines (last 4 digits visible).
     *
     * @param string $value
     * @return string
     */
    function webshop_mask_phone_for_log($value) {
        $digits = preg_replace('/\D/', '', (string) $value);
        $len = strlen($digits);
        if ($len <= 4) {
            return str_repeat('*', max(1, $len));
        }
        return str_repeat('*', $len - 4) . substr($digits, -4);
    }
}

if (!function_exists('webshop_forgot_password_log')) {
    /**
     * Structured trace for forgot-password flow. Uses log level "error" so it is
     * always written when log_threshold >= 1 (see application/config/config.php).
     *
     * Log file: application/logs/log-YYYY-MM-DD.php
     *
     * @param string $step    Short step id, e.g. send_otp.customer_not_found
     * @param array  $context Key/value context (phones/otp/passwords are masked)
     */
    function webshop_forgot_password_log($step, array $context = array()) {
        $redact = array(
            'mobile', 'phone', 'mobileno', 'otp', 'password', 'new_password',
            'confirm_password', 'privatekey', 'private_key',
        );
        $safe = array();
        foreach ($context as $key => $val) {
            $lk = strtolower((string) $key);
            $is_secret = in_array($lk, $redact, true)
                || strpos($lk, 'pass') !== false
                || strpos($lk, 'otp') !== false;
            if ($is_secret) {
                if ($lk === 'otp' && is_string($val)) {
                    $safe[$key] = '******';
                } elseif (in_array($lk, array('mobile', 'phone', 'mobileno'), true)) {
                    $safe[$key] = webshop_mask_phone_for_log($val);
                } else {
                    $safe[$key] = '[redacted]';
                }
                continue;
            }
            if (is_scalar($val) || $val === null) {
                $safe[$key] = $val;
            } elseif (is_array($val)) {
                $safe[$key] = $val;
            } else {
                $safe[$key] = json_encode($val);
            }
        }
        $payload = '';
        if ($safe !== array()) {
            $json = json_encode($safe, defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
            $payload = ' ' . ($json !== false ? $json : '{"json_encode_failed":true}');
        }
        $level = 'error';
        if (strpos((string) $step, 'render_form') !== false || strpos((string) $step, 'post_received') !== false) {
            $level = 'debug';
        }
        log_message($level, '[FP_TRACE] ' . trim((string) $step) . $payload);
    }
}

if (!function_exists('webshop_resolve_cms_path_href')) {
    /**
     * Turn CMS paths (/services) into webshop URLs when needed.
     *
     * @param string $href
     * @param string|null $webshop_base
     * @return string
     */
    function webshop_resolve_cms_path_href($href, $webshop_base = null)
    {
        $href = trim((string) $href);
        if ($href === '' || $href === '#') {
            return $href;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if ($webshop_base === null) {
            $webshop_base = rtrim(base_url('webshop'), '/');
        }
        if (isset($href[0]) && $href[0] === '/') {
            return $webshop_base . $href;
        }
        return $href;
    }
}

if (!function_exists('webshop_herbinn_storefront_field_value')) {
    /**
     * Active row value from sma_webshop_header_footer (via getsettings website_setting_sections).
     *
     * @param string $section header|footer
     * @param string $field_key
     * @return string
     */
    function webshop_herbinn_storefront_field_value($section, $field_key)
    {
        $section = strtolower(trim((string) $section));
        $field_key = strtolower(trim((string) $field_key));
        if ($section === '' || $field_key === '') {
            return '';
        }
        foreach (webshop_website_setting_section_rows($section) as $item) {
            if (webshop_ws_row_field_key($item) !== $field_key) {
                continue;
            }
            return trim(webshop_ws_row_value_string($item));
        }
        return '';
    }
}

if (!function_exists('webshop_herbinn_header_cta_from_storefront')) {
    /**
     * Nav CTA from header rows header_cta_label / header_cta_href.
     *
     * @return array{label: string, href: string}
     */
    function webshop_herbinn_header_cta_from_storefront()
    {
        return array(
            'label' => webshop_herbinn_storefront_field_value('header', 'header_cta_label'),
            'href'  => webshop_herbinn_storefront_field_value('header', 'header_cta_href'),
        );
    }
}

if (!function_exists('webshop_herbinn_collect_footer_section_rows')) {
    /**
     * Footer rows from getsettings website_setting_sections.footer (+ flat website_setting fallback).
     *
     * @return array<int, mixed>
     */
    function webshop_herbinn_collect_footer_section_rows()
    {
        $rows = webshop_website_setting_section_rows('footer');
        if (!empty($rows)) {
            return $rows;
        }
        $out = array();
        if (!function_exists('webshop_ws_website_setting_bundles')) {
            return $out;
        }
        foreach (webshop_ws_website_setting_bundles() as $items) {
            foreach ($items as $item) {
                if (!webshop_ws_row_is_active($item)) {
                    continue;
                }
                $fk = webshop_ws_row_field_key($item);
                if ($fk === '' || strpos($fk, 'footer_') !== 0) {
                    continue;
                }
                $row = is_object($item) ? $item : (object) (array) $item;
                $sec = isset($row->section_type) ? strtolower(trim((string) $row->section_type)) : '';
                if ($sec === 'header') {
                    continue;
                }
                $out[] = $item;
            }
        }
        usort($out, function ($a, $b) {
            return webshop_ws_row_sort_order($a) - webshop_ws_row_sort_order($b);
        });
        return $out;
    }
}

if (!function_exists('webshop_herbinn_footer_field_is_structural')) {
    /**
     * Footer field_key handled outside link lists (tagline, headings, certs, office).
     *
     * @param string $field_key
     * @return bool
     */
    function webshop_herbinn_footer_field_is_structural($field_key)
    {
        $fk = strtolower(trim((string) $field_key));
        if ($fk === '') {
            return true;
        }
        static $exact = array(
            'footer_tagline', 'footer_copyright', 'footer_certifications', 'footer_certifications_html',
            'footer_office_address', 'footer_heading_company', 'footer_heading_legal',
            'footer_heading_certifications', 'footer_heading_office',
        );
        if (in_array($fk, $exact, true)) {
            return true;
        }
        return (strpos($fk, 'footer_heading_') === 0);
    }
}

if (!function_exists('webshop_herbinn_footer_layout_from_storefront')) {
    /**
     * Four-column Herbinn footer layout from sma_webshop_header_footer (footer section).
     * field_key conventions — see herbinnwellness_header_footer_seed.sql
     *
     * @return array
     */
    function webshop_herbinn_footer_layout_from_storefront()
    {
        $layout = array(
            'tagline'              => '',
            'copyright'            => '',
            'headings'             => array(
                'company'         => '',
                'legal'           => '',
                'certifications'  => '',
                'office'          => '',
            ),
            'company_links'        => array(),
            'legal_links'          => array(),
            'extra_links'          => array(),
            'misc_lines'           => array(),
            'certifications'       => array(),
            'certifications_html'  => '',
            'office_address'       => '',
            'has_data'             => false,
        );

        $company_links = array();
        $legal_links = array();
        $extra_links = array();
        $misc_lines = array();

        foreach (webshop_herbinn_collect_footer_section_rows() as $item) {
            $fk = webshop_ws_row_field_key($item);
            if ($fk === '') {
                continue;
            }
            $val = trim(webshop_ws_row_value_string($item));
            $lab = trim(webshop_ws_row_label_string($item, $fk));
            $sort = webshop_ws_row_sort_order($item);
            $layout['has_data'] = true;

            if ($fk === 'footer_tagline') {
                $layout['tagline'] = $val;
                continue;
            }
            if ($fk === 'footer_copyright') {
                $layout['copyright'] = $val;
                continue;
            }
            if ($fk === 'footer_certifications_html') {
                $layout['certifications_html'] = $val;
                continue;
            }
            if ($fk === 'footer_certifications') {
                if ($val !== '') {
                    $layout['certifications'] = array_values(array_filter(array_map('trim', preg_split('/\||\r\n|\r|\n/', $val))));
                }
                continue;
            }
            if ($fk === 'footer_office_address') {
                $layout['office_address'] = str_replace(array('\\n', '\r\n'), array("\n", "\n"), $val);
                continue;
            }
            if ($fk === 'footer_heading_company') {
                $layout['headings']['company'] = $val !== '' ? $val : $lab;
                continue;
            }
            if ($fk === 'footer_heading_legal') {
                $layout['headings']['legal'] = $val !== '' ? $val : $lab;
                continue;
            }
            if ($fk === 'footer_heading_certifications') {
                $layout['headings']['certifications'] = $val !== '' ? $val : $lab;
                continue;
            }
            if ($fk === 'footer_heading_office') {
                $layout['headings']['office'] = $val !== '' ? $val : $lab;
                continue;
            }
            if (strpos($fk, 'footer_link_company_') === 0 && $lab !== '' && $val !== '') {
                $company_links[] = array('title' => $lab, 'href' => $val, 'sort' => $sort, 'field_key' => $fk);
                continue;
            }
            if (strpos($fk, 'footer_link_legal_') === 0 && $lab !== '' && $val !== '') {
                $legal_links[] = array('title' => $lab, 'href' => $val, 'sort' => $sort, 'field_key' => $fk);
                continue;
            }
            if (strpos($fk, 'footer_link_') === 0 && $lab !== '' && $val !== '') {
                $extra_links[] = array('title' => $lab, 'href' => $val, 'sort' => $sort, 'field_key' => $fk);
                continue;
            }
            if (webshop_herbinn_footer_field_is_structural($fk)) {
                continue;
            }
            if ($lab !== '' && $val !== '') {
                $extra_links[] = array('title' => $lab, 'href' => $val, 'sort' => $sort, 'field_key' => $fk);
                continue;
            }
            if ($lab !== '') {
                $misc_lines[] = array('text' => $lab, 'sort' => $sort, 'field_key' => $fk);
            }
        }

        $sort_links = function ($a, $b) {
            $sa = isset($a['sort']) ? (int) $a['sort'] : 0;
            $sb = isset($b['sort']) ? (int) $b['sort'] : 0;
            if ($sa !== $sb) {
                return $sa - $sb;
            }
            return strcmp(isset($a['field_key']) ? $a['field_key'] : '', isset($b['field_key']) ? $b['field_key'] : '');
        };
        usort($company_links, $sort_links);
        usort($legal_links, $sort_links);
        foreach ($company_links as $row) {
            $layout['company_links'][] = array('title' => $row['title'], 'href' => $row['href']);
        }
        foreach ($legal_links as $row) {
            $layout['legal_links'][] = array('title' => $row['title'], 'href' => $row['href']);
        }
        usort($extra_links, $sort_links);
        usort($misc_lines, $sort_links);
        foreach ($extra_links as $row) {
            $layout['extra_links'][] = array('title' => $row['title'], 'href' => $row['href']);
        }
        foreach ($misc_lines as $row) {
            $layout['misc_lines'][] = (string) $row['text'];
        }

        if (empty($layout['company_links']) || empty($layout['legal_links'])) {
            $cms_nav = array();
            if (function_exists('get_instance')) {
                $CI = get_instance();
                if (isset($CI->data['cms_nav_pages']) && is_array($CI->data['cms_nav_pages'])) {
                    $cms_nav = $CI->data['cms_nav_pages'];
                }
            }
            $nav_company = array();
            $nav_legal = array();
            foreach ($cms_nav as $np) {
                $u = isset($np['url']) ? strtolower((string) $np['url']) : '';
                $t = isset($np['title']) ? (string) $np['title'] : '';
                $h = isset($np['href']) ? (string) $np['href'] : '';
                if ($t === '' || $h === '') {
                    continue;
                }
                if (strpos($u, 'privacy') !== false || strpos($u, 'terms') !== false || strpos($u, 'contact') !== false || strpos($u, 'faq') !== false) {
                    $nav_legal[] = array('title' => $t, 'href' => $h);
                } else {
                    $nav_company[] = array('title' => $t, 'href' => $h);
                }
            }
            if (empty($layout['company_links'])) {
                $layout['company_links'] = $nav_company;
            }
            if (empty($layout['legal_links'])) {
                $layout['legal_links'] = $nav_legal;
            }
        }

        return $layout;
    }
}

if (!function_exists('webshop_herbinn_footer_copyright_line')) {
    /**
     * @param string $template From footer_copyright row; supports {year}
     * @return string
     */
    function webshop_herbinn_footer_copyright_line($template)
    {
        $tpl = trim((string) $template);
        if ($tpl === '') {
            return '';
        }
        return str_replace('{year}', date('Y'), $tpl);
    }
}