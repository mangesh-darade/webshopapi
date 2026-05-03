<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shared ElintOm assets/mdata path rules (MY_Controller + Webshop_api_model).
 */

/**
 * Tenant folder label from HTTP_HOST for MY_Controller::$Customer_assets
 * (first subdomain label; localhost → config elintom_customer_assets_folder).
 *
 * @return string safe folder name
 */
function elintom_customer_assets_from_http_host() {
    $CI =& get_instance();
    $CI->config->load('elintom_api', true);
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string) $_SERVER['HTTP_HOST'])) : '';
    if ($host === '') {
        $fallback = $CI->config->item('elintom_customer_assets_folder', 'elintom_api');
        return is_string($fallback) && $fallback !== ''
            ? preg_replace('/[^a-zA-Z0-9_.-]/', '', $fallback)
            : 'default';
    }
    $skip = $CI->config->item('elintom_subdomain_skip_hosts', 'elintom_api');
    if (!is_array($skip)) {
        $skip = array('localhost', '127.0.0.1', '[::1]');
    }
    foreach ($skip as $s) {
        if (strcasecmp($host, strtolower((string) $s)) === 0) {
            $fallback = $CI->config->item('elintom_customer_assets_folder', 'elintom_api');
            $f = is_string($fallback) ? preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($fallback)) : '';
            return ($f !== '') ? $f : 'default';
        }
    }
    if ($CI->config->item('elintom_subdomain_strip_www', 'elintom_api') !== false && strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    $parts = explode('.', $host);
    $label = isset($parts[0]) ? $parts[0] : 'default';
    $label = preg_replace('/[^a-zA-Z0-9_.-]/', '', $label);
    return ($label !== '') ? $label : 'default';
}

/**
 * Safe folder segment from browser Host (…/assets/mdata/{this}/uploads/).
 *
 * @return string
 */
function elintom_mdata_http_host_folder_segment() {
    if (!isset($_SERVER['HTTP_HOST']) || (string) $_SERVER['HTTP_HOST'] === '') {
        return '';
    }
    $browser_hostname = strtolower(trim((string) $_SERVER['HTTP_HOST']));
    if (preg_match('/^\[[^\]]+\]$/', $browser_hostname)) {
        $browser_hostname = trim($browser_hostname, '[]');
    } elseif (preg_match('/:\d+$/', $browser_hostname)) {
        $browser_hostname = preg_replace('/:\d+$/', '', $browser_hostname);
    }
    return preg_replace('/[^a-zA-Z0-9_.-]/', '', $browser_hostname);
}

/**
 * Path after …/assets/mdata/ — "{tenant}/uploads/" or "{http_host}/uploads/".
 *
 * @param string $tenant_folder_name
 * @return string ending with uploads/
 */
function elintom_mdata_uploads_tail_path($tenant_folder_name) {
    $CI =& get_instance();
    $CI->config->load('elintom_api', true);
    $tenant_folder_name = trim((string) $tenant_folder_name, '/');
    if ($tenant_folder_name === '') {
        $tenant_folder_name = 'default';
    }
    $use_domain_name_as_mdata_folder = $CI->config->item('elintom_mdata_include_http_host_segment', 'elintom_api');
    if ($use_domain_name_as_mdata_folder === null || $use_domain_name_as_mdata_folder === '') {
        $use_domain_name_as_mdata_folder = true;
    } elseif (is_string($use_domain_name_as_mdata_folder)) {
        $use_domain_name_as_mdata_folder = in_array(strtolower(trim($use_domain_name_as_mdata_folder)), array('1', 'true', 'yes', 'on'), true);
    } else {
        $use_domain_name_as_mdata_folder = (bool) $use_domain_name_as_mdata_folder;
    }
    if (!$use_domain_name_as_mdata_folder) {
        return $tenant_folder_name . '/uploads/';
    }
    $safe_folder_name_from_hostname = elintom_mdata_http_host_folder_segment();
    if ($safe_folder_name_from_hostname === '') {
        return $tenant_folder_name . '/uploads/';
    }
    return $safe_folder_name_from_hostname . '/uploads/';
}
