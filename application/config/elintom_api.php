<?php
defined('BASEPATH') OR exit('No direct script access allowed');

@include __DIR__ . DIRECTORY_SEPARATOR . 'elintom_api_switch.php';

$apiBase = trim((string)($selected_api_base_url ?? ''));
$mediaBase = trim((string)($selected_media_uploads_base_url ?? ''));

$config['elintom_api_base_url'] = $apiBase === '' ? '' : rtrim(str_replace('\\', '/', $apiBase), '/') . '/';
$config['elintom_api_private_key'] = trim((string)($selected_api_private_key ?? ''));
$config['elintom_api_ssl_verify'] = (bool)($selected_api_ssl_verify ?? true);
$config['elintom_api_ssl_ca_bundle'] = trim((string)($selected_api_ssl_ca_bundle ?? ''));

$config['elintom_theme_assets_directory'] = trim((string)($selected_theme_assets_directory ?? ''));
$config['elintom_theme_view_folder'] = trim((string)($selected_theme_view_folder ?? ''));
$config['elintom_storefront_theme'] = trim((string)($selected_webshop_theme ?? ''));

$config['elintom_api_webshop_endpoint_path'] = 'webshop_api/index';
$config['elintom_api_legacy_endpoint_path'] = 'api3/eshop';

$config['elintom_media_uploads_base_url'] = $mediaBase === '' ? '' : rtrim(str_replace('\\', '/', $mediaBase), '/') . '/';
$config['elintom_customer_assets_folder'] = trim((string)($selected_customer_assets_folder ?? 'default'));
$config['elintom_mdata_include_http_host_segment'] = (bool)($selected_mdata_include_http_host_segment ?? true);
$config['elintom_media_use_http_host'] = (bool)($selected_media_use_http_host ?? true);
$config['elintom_media_resolve_at_runtime'] = true;

$config['elintom_subdomain_strip_www'] = true;
$config['elintom_subdomain_skip_hosts'] = array('localhost', '127.0.0.1', '[::1]');

$config['elintom_catalog_source'] = 'api';
$config['elintom_catalog_fallback_database'] = false;
$config['elintom_domain_theme_map'] = array();

$config['elintom_http_cache_settings_seconds'] = 45;
$config['elintom_http_cache_categories_seconds'] = 0;
$config['elintom_http_cache_cart_products_seconds'] = 60;
