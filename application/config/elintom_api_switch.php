<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$selected_api_base_url = '';
$selected_api_private_key = '';
$selected_media_uploads_base_url = '';
$selected_webshop_theme = '';
$selected_theme_assets_directory = '';
$selected_theme_view_folder = '';
$selected_customer_assets_folder = 'default';
$selected_mdata_include_http_host_segment = false;
$selected_media_use_http_host = false;
$selected_api_ssl_verify = true;
$selected_api_ssl_ca_bundle = '';

$normalize_host = function ($raw_host) {
    $host = strtolower(trim((string) $raw_host));
    $host = preg_replace('/[\x00-\x1F\x7F]/', '', $host);
    $host = preg_replace('/:\d+$/', '', (string) $host);
    $host = ($host === '[::1]' || $host === '::1') ? 'localhost' : $host;
    $host = strpos($host, 'www.') === 0 ? substr($host, 4) : $host;
    return preg_match('/^(localhost|[a-z0-9.-]+)$/', $host) ? $host : 'localhost';
};

$detected_host_for_api = $normalize_host(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');

$elintom_switch_profiles = array(
 
    'gulfpharmacy_production' => array(
        'api_base_url'                       => 'https://testingpos.elintpos.in/',
        'api_private_key'                    => '3e8676ed23c627117437c7e6a1bbd6e9',
        'ssl_verify'                         => true,
        'ssl_ca_bundle'                      => '',
        'media_uploads_base_url'             => 'https://testingpos.elintpos.in/assets/mdata/localhost/uploads/',
        'customer_assets_folder'             => 'localhost',
        'mdata_include_http_host_segment'    => false,
        'media_use_http_host'                => false,
        'webshop_theme'                   => 'gulfpharmacy',
        'theme_view_folder'               => 'herbinnwellness',
        'theme_assets_directory'          => 'herbinnwellness',
    ),
    'localhost_elintom' => array(
        'api_base_url'                    => 'http://localhost/ElintOm/',
        'api_private_key'                 => '3e8676ed23c627117437c7e6a1bbd6e9',
        'ssl_verify'                      => false,
        'ssl_ca_bundle'                   => '',
        'media_uploads_base_url'          => 'http://localhost/ElintOm/assets/mdata/localhost/uploads/',
        'customer_assets_folder'          => 'localhost',
        'mdata_include_http_host_segment' => false,
        'media_use_http_host'             => false,
        'webshop_theme'                   => 'gulfpharmacy',
        'theme_view_folder'               => 'herbinnwellness',
        'theme_assets_directory'          => 'herbinnwellness',
    ),
   
);

$host_to_profile = array(
    'localhost'                         => 'localhost_elintom',
    'webshop.elintpos.in'               => 'gulfpharmacy_production',
    'herbinnmicromedicines.elintpos.in' => 'herbinnwellness_production',
);

$elintom_active_profile = isset($host_to_profile[$detected_host_for_api])  ? $host_to_profile[$detected_host_for_api] : '';

$p = isset($elintom_switch_profiles[$elintom_active_profile])
    ? $elintom_switch_profiles[$elintom_active_profile]
    : array();

$selected_api_base_url = isset($p['api_base_url']) ? (string) $p['api_base_url'] : '';
$selected_api_private_key = isset($p['api_private_key']) ? trim((string) $p['api_private_key']) : '';
$selected_media_uploads_base_url = isset($p['media_uploads_base_url']) ? (string) $p['media_uploads_base_url'] : '';
$selected_webshop_theme = isset($p['webshop_theme']) ? (string) $p['webshop_theme'] : '';
$selected_theme_view_folder = isset($p['theme_view_folder']) ? (string) $p['theme_view_folder'] : '';
$selected_theme_assets_directory = isset($p['theme_assets_directory']) ? (string) $p['theme_assets_directory'] : '';
$selected_customer_assets_folder = isset($p['customer_assets_folder']) ? (string) $p['customer_assets_folder'] : 'default';
$selected_mdata_include_http_host_segment = isset($p['mdata_include_http_host_segment']) ? (bool) $p['mdata_include_http_host_segment'] : false;
$selected_media_use_http_host = isset($p['media_use_http_host']) ? (bool) $p['media_use_http_host'] : false;
$selected_api_ssl_verify = isset($p['ssl_verify']) ? (bool) $p['ssl_verify'] : true;
$selected_api_ssl_ca_bundle = isset($p['ssl_ca_bundle']) ? (string) $p['ssl_ca_bundle'] : '';

unset($normalize_host, $host_to_profile, $elintom_switch_profiles, $elintom_active_profile, $p);
