<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$detected_host_for_api = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string) $_SERVER['HTTP_HOST'])) : 'localhost';
if (preg_match('/:\d+$/', $detected_host_for_api)) {
    $detected_host_for_api = preg_replace('/:\d+$/', '', $detected_host_for_api);
}

// Filled per `case` below. Empty `$selected_media_uploads_base_url` lets `Webshop_api_model::get_media_uploads_base()`
// build `…/assets/mdata/{HTTP_HOST}/uploads/` from the browser host (`elintom_media_use_http_host` + `elintom_mdata_include_http_host_segment`).
$selected_api_base_url = '';
$selected_api_private_key = '';
$selected_media_uploads_base_url = '';

/////////////////////////////////////////////////////////////// Switch Case for API Base URL and Private Key ///////////////////////////////////////////////////////////////

switch ($detected_host_for_api) {

    case 'localhost':
        // API host matches how you open the shop (localhost vs 127.0.0.1).
        $selected_api_base_url = 'http://' . $detected_host_for_api . '/ElintOm/';
        $selected_api_private_key = '3e8676ed23c627117437c7e6a1bbd6e9';
        $selected_media_uploads_base_url = '';
        break;

    // Add customer domains here, for example:
    // case 'customer1.yourdomain.com':
    //     $selected_api_base_url = 'https://customer1.yourdomain.com/ElintOm/';
    //     $selected_api_private_key = 'customer1-private-key';
    //     // Leave empty to use host-based mdata, or set a fixed/CDN uploads root:
    //     $selected_media_uploads_base_url = '';
    //     break;

    default:
        break;
}
