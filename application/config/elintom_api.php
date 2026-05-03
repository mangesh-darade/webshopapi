<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| ElintOm API — quick setup
|--------------------------------------------------------------------------
|
| 1) Set elintom_api_base_url to your ElintOm address (a trailing / is added for you).
| 2) Set elintom_api_private_key to match ElintOm → Settings → API private key.
| 3) Leave elintom_media_uploads_base_url empty unless you use a CDN or a fixed image host.
|
| The names on the left of $config['name'] are fixed — the rest of the app looks them up by name.
| Only the values (right side) are what you normally change.
|--------------------------------------------------------------------------
*/

// --- Step 1: connect to ElintOm ------------------------------------------------
$config['elintom_api_base_url']    = 'http://localhost/ElintOm/';
$config['elintom_api_private_key'] = '3e8676ed23c627117437c7e6a1bbd6e9';

// Relative to base URL above (no leading slash). Change only if your ElintOm needs index.php (common on WAMP without rewrite):
//   index.php/webshop_api/index
$config['elintom_api_webshop_endpoint_path'] = 'webshop_api/index';
$config['elintom_api_legacy_endpoint_path']  = 'api3/eshop';

// --- Step 2: product images (optional — empty = automatic) --------------------
// Full URL to the uploads root, or leave empty. Example:
//   http://localhost/ElintOm/assets/mdata/localhost/uploads/
$config['elintom_media_uploads_base_url'] = '';

// When host-based mdata is off, this folder name is used: …/mdata/{this}/uploads/
$config['elintom_customer_assets_folder'] = 'default';

// TRUE  → images under …/assets/mdata/{website-hostname}/uploads/
// FALSE → images under …/assets/mdata/{elintom_customer_assets_folder}/uploads/
$config['elintom_mdata_include_http_host_segment'] = true;

// TRUE = use the same host the customer types in the browser (localhost vs 127.0.0.1).
$config['elintom_media_use_http_host'] = true;

// TRUE = recalculate the image base on every request. FALSE = use the prebuilt value at the bottom of this file.
$config['elintom_media_resolve_at_runtime'] = true;

// Subdomain → customer folder (shop1.example.com → "shop1"). These hosts use the folder above instead.
$config['elintom_subdomain_strip_www']  = true;
$config['elintom_subdomain_skip_hosts'] = array('localhost', '127.0.0.1', '[::1]');

// --- Optional: read settings from the server environment (Docker, Apache SetEnv) --
// Map: "OS environment variable name" => "CodeIgniter $config key to fill"
$environment_variable_to_config_key = array(
    'ELINTOM_API_BASE_URL'                      => 'elintom_api_base_url',
    'ELINTOM_API_PRIVATE_KEY'                   => 'elintom_api_private_key',
    'ELINTOM_MEDIA_UPLOADS_BASE_URL'            => 'elintom_media_uploads_base_url',
    'ELINTOM_CUSTOMER_ASSETS_FOLDER'            => 'elintom_customer_assets_folder',
    'ELINTOM_MEDIA_USE_HTTP_HOST'               => 'elintom_media_use_http_host',
    'ELINTOM_MDATA_INCLUDE_HTTP_HOST_SEGMENT'   => 'elintom_mdata_include_http_host_segment',
);

foreach ($environment_variable_to_config_key as $environment_name => $config_key_in_array) {
    $value_read_from_server = getenv($environment_name);
    if (($value_read_from_server === false || $value_read_from_server === '')
        && isset($_SERVER[$environment_name])
        && (string) $_SERVER[$environment_name] !== '') {
        $value_read_from_server = $_SERVER[$environment_name];
    }
    if ($value_read_from_server === false || $value_read_from_server === '') {
        continue;
    }
    if ($config_key_in_array === 'elintom_media_use_http_host'
        || $config_key_in_array === 'elintom_mdata_include_http_host_segment') {
        $config[$config_key_in_array] = in_array(
            strtolower(trim((string) $value_read_from_server)),
            array('1', 'true', 'yes', 'on'),
            true
        );
    } elseif ($config_key_in_array === 'elintom_api_private_key') {
        $config[$config_key_in_array] = trim((string) $value_read_from_server);
    } else {
        $config[$config_key_in_array] = (string) $value_read_from_server;
    }
}
unset($environment_variable_to_config_key, $environment_name, $config_key_in_array, $value_read_from_server);

// --- Optional: per-machine file next to this one (not in git) -------------------
$path_to_optional_local_overrides = __DIR__ . DIRECTORY_SEPARATOR . 'elintom_api.local.php';
if (is_file($path_to_optional_local_overrides)) {
    include $path_to_optional_local_overrides;
}
unset($path_to_optional_local_overrides);

// Always store base URL with one trailing slash
if (!empty($config['elintom_api_base_url'])) {
    $config['elintom_api_base_url'] = rtrim(str_replace('\\', '/', $config['elintom_api_base_url']), '/') . '/';
}

if (!isset($config['elintom_media_use_http_host'])) {
    $config['elintom_media_use_http_host'] = false;
}
if (!isset($config['elintom_media_resolve_at_runtime'])) {
    $config['elintom_media_resolve_at_runtime'] = true;
}

/*
| Pre-build media URL only when runtime resolve is off and media URL is still empty.
*/
if (empty($config['elintom_media_resolve_at_runtime'])
    && isset($config['elintom_media_uploads_base_url'])
    && (string) $config['elintom_media_uploads_base_url'] === ''
    && !empty($config['elintom_api_base_url'])) {

    $tenant_folder_name = isset($config['elintom_customer_assets_folder'])
        ? trim((string) $config['elintom_customer_assets_folder'], '/')
        : 'default';
    if ($tenant_folder_name === '') {
        $tenant_folder_name = 'default';
    }

    $use_domain_name_as_mdata_folder = isset($config['elintom_mdata_include_http_host_segment'])
        ? (bool) $config['elintom_mdata_include_http_host_segment']
        : true;

    // Relative bit after …/assets/mdata/ — ends with "uploads/"
    $folder_path_after_mdata = $tenant_folder_name . '/uploads/';
    if ($use_domain_name_as_mdata_folder && isset($_SERVER['HTTP_HOST']) && (string) $_SERVER['HTTP_HOST'] !== '') {
        $browser_hostname = strtolower(trim((string) $_SERVER['HTTP_HOST']));
        if (preg_match('/^\[[^\]]+\]$/', $browser_hostname)) {
            $browser_hostname = trim($browser_hostname, '[]');
        } elseif (preg_match('/:\d+$/', $browser_hostname)) {
            $browser_hostname = preg_replace('/:\d+$/', '', $browser_hostname);
        }
        $safe_folder_name_from_hostname = preg_replace('/[^a-zA-Z0-9_.-]/', '', $browser_hostname);
        if ($safe_folder_name_from_hostname !== '') {
            $folder_path_after_mdata = $safe_folder_name_from_hostname . '/uploads/';
        }
    }

    if (!empty($config['elintom_media_use_http_host'])
        && isset($_SERVER['HTTP_HOST'])
        && (string) $_SERVER['HTTP_HOST'] !== '') {

        $parts_of_elintom_base_url        = parse_url($config['elintom_api_base_url']);
        $path_from_elintom_url            = isset($parts_of_elintom_base_url['path'])
            ? trim(str_replace('\\', '/', $parts_of_elintom_base_url['path']), '/')
            : '';
        $slash_path_to_elintom_app        = ($path_from_elintom_url === '') ? '/' : '/' . $path_from_elintom_url . '/';
        $url_scheme_http_or_https         = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $url_scheme_http_or_https = 'https';
        }
        $config['elintom_media_uploads_base_url'] = $url_scheme_http_or_https
            . '://' . $_SERVER['HTTP_HOST'] . $slash_path_to_elintom_app
            . 'assets/mdata/' . $folder_path_after_mdata;
    } else {
        $elintom_root_without_trailing_slash = rtrim(str_replace('\\', '/', $config['elintom_api_base_url']), '/');
        $config['elintom_media_uploads_base_url'] = $elintom_root_without_trailing_slash
            . '/assets/mdata/' . $folder_path_after_mdata;
    }
}

// How to load the shop catalogue: "api" = from ElintOm over HTTP (usual). "database" = same MySQL as POS.
if (!isset($config['elintom_catalog_source'])) {
    $config['elintom_catalog_source'] = 'api';
}

// If the API call fails, try the local database model (needs MySQL in this app).
if (!isset($config['elintom_catalog_fallback_database'])) {
    $config['elintom_catalog_fallback_database'] = false;
}

// Session-backed HTTP response cache (seconds). 0 = disable. Low values reduce ElintOm round-trips per shopper session.
if (!isset($config['elintom_http_cache_settings_seconds'])) {
    $config['elintom_http_cache_settings_seconds'] = 45;
}
if (!isset($config['elintom_http_cache_categories_seconds'])) {
    $config['elintom_http_cache_categories_seconds'] = 120;
}
if (!isset($config['elintom_http_cache_cart_products_seconds'])) {
    $config['elintom_http_cache_cart_products_seconds'] = 60;
}
