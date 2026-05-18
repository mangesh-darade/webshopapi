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

/*
| Per-host storefront theme (one switch file for API + theme + CSS folder).
|
| $selected_webshop_theme
|   ElintOm / CMS view theme id: gulfpharmacy | nw | restaurant …
|   Maps to views under plane_vanila_theme/{theme}_theme/
|
| $selected_theme_assets_directory
|   Folder under assets/webshop/{name}/ for CSS & JS
|   Examples: gulfpharmacy_theme, gulfpharmacy_theme_new, nw_theme, localhost
|   Leave empty to use the browser hostname as the folder name.
|
| $selected_theme_view_folder
|   PHP views under application/views/plane_vanila_theme/{name}/
|   Use when the folder name is NOT {webshop_theme}_theme (e.g. gulfpharmacy_theme_new).
|   Leave empty to use {webshop_theme}_theme (gulfpharmacy → gulfpharmacy_theme).
*/
$selected_webshop_theme = '';
$selected_theme_assets_directory = '';
$selected_theme_view_folder = '';

/////////////////////////////////////////////////////////////// Switch Case for API Base URL and Private Key ///////////////////////////////////////////////////////////////

switch ($detected_host_for_api) {

    case '127.0.0.1':
    case 'localhost':
        // API host matches how you open the shop (localhost vs 127.0.0.1).
        $selected_api_base_url = 'http://localhost/ElintOm/';
        $selected_api_private_key = '3e8676ed23c627117437c7e6a1bbd6e9';
        $selected_media_uploads_base_url = '';
        $selected_webshop_theme = 'gulfpharmacy';
        $selected_theme_view_folder = 'gulfpharmacy_theme_3';
        $selected_theme_assets_directory = 'gulfpharmacy_theme_2';

        break;

    // Add customer domains here, for example:
    // case 'customer1.yourdomain.com':
    //     $selected_api_base_url = 'https://customer1.yourdomain.com/ElintOm/';
    //     $selected_api_private_key = 'customer1-private-key';
    //     $selected_media_uploads_base_url = '';
    //     $selected_webshop_theme = 'gulfpharmacy';
    //     $selected_theme_assets_directory = 'customer1.yourdomain.com';
    //     break;

    default:
        break;
}
