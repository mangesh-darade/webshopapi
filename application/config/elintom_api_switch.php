<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$detected_host_for_api = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string) $_SERVER['HTTP_HOST'])) : 'localhost';
if (preg_match('/:\d+$/', $detected_host_for_api)) {
    $detected_host_for_api = preg_replace('/:\d+$/', '', $detected_host_for_api);
}
if ($detected_host_for_api === '[::1]' || $detected_host_for_api === '::1') {
    $detected_host_for_api = 'localhost';
}
if (strpos($detected_host_for_api, 'www.') === 0) {
    $detected_host_for_api = substr($detected_host_for_api, 4);
}

$selected_api_base_url = '';
$selected_api_private_key = '';
$selected_media_uploads_base_url = '';
$selected_webshop_theme = '';
$selected_theme_assets_directory = '';
$selected_theme_view_folder = '';
$selected_customer_assets_folder = '';
$selected_mdata_include_http_host_segment = null;
$selected_media_use_http_host = null;
$selected_api_ssl_verify = true;
$selected_api_ssl_ca_bundle = '';

$elintom_switch_profiles = array(
    'vanila_testing' => array(
        'api_base_url'                    => 'https://testingpos.elintpos.in/',
        'api_private_key'                 => '3e8676ed23c627117437c7e6a1bbd6e9',
        'ssl_verify'                      => false,
        'ssl_ca_bundle'                   => '',
        'media_uploads_base_url'          => 'https://testingpos.elintpos.in/assets/mdata/localhost/uploads/',
        'customer_assets_folder'          => 'localhost',
        'mdata_include_http_host_segment' => false,
        'media_use_http_host'             => false,
        'webshop_theme'                   => 'gulfpharmacy',
        'theme_view_folder'               => 'gulfpharmacy_theme',
        'theme_assets_directory'          => 'gulfpharmacy_theme',
    ),
    'gulfpharmacy_testing' => array(
        'api_base_url'                       => 'https://testingpos.elintpos.in/',
        'api_private_key'                    => '3e8676ed23c627117437c7e6a1bbd6e9',
        'media_uploads_base_url'             => 'https://testingpos.elintpos.in/assets/mdata/localhost/uploads/',
        'customer_assets_folder'             => 'localhost',
        'mdata_include_http_host_segment'    => false,
        'media_use_http_host'                => false,
        'webshop_theme'                      => 'gulfpharmacy',
        'theme_view_folder'                  => 'gulfpharmacy_theme',
        'theme_assets_directory'             => 'gulfpharmacy_theme',
    ),
);

$elintom_active_profile = 'gulfpharmacy_testing';

switch ($detected_host_for_api) {
    case '127.0.0.1':
    case 'localhost':
        $elintom_active_profile = 'vanila_testing';
        break;

    case 'webshop':
    case 'webshop.elintpos.in':
        $elintom_active_profile = 'gulfpharmacy_testing';
        break;

    default:
        $elintom_active_profile = 'gulfpharmacy_testing';
        break;
}

if (isset($elintom_switch_profiles[$elintom_active_profile])) {
    $p = $elintom_switch_profiles[$elintom_active_profile];
    $selected_api_base_url = $p['api_base_url'];
    $selected_api_private_key = $p['api_private_key'];
    $selected_media_uploads_base_url = isset($p['media_uploads_base_url']) ? $p['media_uploads_base_url'] : '';
    $selected_webshop_theme = $p['webshop_theme'];
    $selected_theme_view_folder = $p['theme_view_folder'];
    $selected_theme_assets_directory = $p['theme_assets_directory'];
    if (!empty($p['customer_assets_folder'])) {
        $selected_customer_assets_folder = $p['customer_assets_folder'];
    }
    if (array_key_exists('mdata_include_http_host_segment', $p)) {
        $selected_mdata_include_http_host_segment = $p['mdata_include_http_host_segment'];
    }
    if (array_key_exists('media_use_http_host', $p)) {
        $selected_media_use_http_host = $p['media_use_http_host'];
    }
    if (array_key_exists('ssl_verify', $p)) {
        $selected_api_ssl_verify = (bool) $p['ssl_verify'];
    }
    if (!empty($p['ssl_ca_bundle'])) {
        $selected_api_ssl_ca_bundle = (string) $p['ssl_ca_bundle'];
    }
}

unset($elintom_switch_profiles, $elintom_active_profile, $p);
