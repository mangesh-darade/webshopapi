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
 * Stock state for product listing cards (category PLP, CMS product grid).
 * When the API omits stock fields, treats the item as in stock (legacy list behaviour).
 *
 * @param array|object $row
 * @return array{known:bool,in_stock:bool,qty:float}
 */
function webshop_product_list_stock_state($row) {
    $row = is_array($row) ? $row : (array) $row;
    $variants = array();
    foreach (array('variants', 'product_variants', 'options', 'product_options') as $vk) {
        if (!empty($row[$vk]) && is_array($row[$vk])) {
            $variants = $row[$vk];
            break;
        }
    }
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
     * Priority: $CI->data → elintom_theme_view_folder config → existing VIEW folder matching host
     * → {webshop_theme}_theme → gulfpharmacy_theme.
     *
     * @return string e.g. gulfpharmacy_theme_new
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
        if ($theme === 'gulfpharmacy') {
            return 'gulfpharmacy_theme';
        }
        if ($theme !== '') {
            $guess = preg_replace('/[^a-zA-Z0-9_.-]/', '', $theme . '_theme');
            $guessDir = VIEWPATH . 'plane_vanila_theme' . DIRECTORY_SEPARATOR . $guess;
            if (is_dir($guessDir)) {
                return $guess;
            }
        }
        return 'gulfpharmacy_theme';
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
        return VIEWPATH . webshop_plane_vanila_view($relative) . '.php';
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
            : 'gulfpharmacy_theme';
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
            return rtrim((string) $CI->data['assets'], '/') . '/';
        }
        return rtrim(base_url('assets/webshop/'), '/') . '/';
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
            $bundles[] = $CI->api_website_setting;
        }
        if (isset($CI->data['website_setting']) && is_array($CI->data['website_setting'])) {
            $bundles[] = $CI->data['website_setting'];
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
        
        $api_sections = (object) array();
        if (isset($CI->api_website_setting_sections)) {
            $api_sections = (object) $CI->api_website_setting_sections;
        }

        // Merge local DB table if it exists
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
        return webshop_normalize_setting_section_row_list($rows);
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
            if ($f === $field_key) {
                $v = webshop_ws_row_value_string($item);
                if ($v !== '') {
                    return is_object($item) ? $item : (object) (array) $item;
                }
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
                $v = webshop_ws_row_value_string($item);
                if ($v !== '') {
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
                    $fk  = webshop_ws_row_field_key($item);
                    $val = webshop_ws_row_value_string($item);
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
                $val = webshop_ws_row_value_string($item);
                // Allow empty values here so webshop_footer_fill_row_fallbacks can backfill them from Settings

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

        return webshop_footer_fill_row_fallbacks(webshop_footer_sort_identity_rows($out));
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
        $p = webshop_ws_row_value_string($row);
        if ($p === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }
        if ($uploads_base !== '') {
            return webshop_media_src((string) $uploads_base, $p);
        }
        return '';
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
                'value'       => webshop_ws_row_value_string($item),
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
                            'value'       => webshop_ws_row_value_string($item),
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
                    'value'       => webshop_ws_row_value_string($item),
                    'icons'       => webshop_ws_row_icons_string($item),
                    'sort_order'  => webshop_ws_row_sort_order($item),
                    'section'     => 'footer',
                );
            }
        }

        $social = webshop_footer_sort_identity_rows($social);
        $content = webshop_footer_fill_row_fallbacks(webshop_footer_sort_identity_rows($content));

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