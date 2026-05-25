<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
$root = $protocol . $host . str_replace(basename($script), '', $script);
$config['base_url'] = $root;

$config['webshop_ai_name'] = defined('WEBSHOP_AI_NAME') ? WEBSHOP_AI_NAME : 'Webshop AI';

// Empty when .htaccess rewrite is on (clean URLs). Use 'index.php' if mod_rewrite is disabled.
$config['index_page'] = '';
$config['uri_protocol'] = 'REQUEST_URI';
$config['url_suffix'] = '';
$config['language'] = 'english';
$config['charset'] = 'UTF-8';
$config['enable_hooks'] = FALSE;
$config['subclass_prefix'] = 'MY_';
$config['composer_autoload'] = FALSE;
$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-';
$config['allow_get_array'] = TRUE;
$config['enable_query_strings'] = FALSE;
$config['controller_trigger'] = 'c';
$config['function_trigger'] = 'm';
$config['directory_trigger'] = 'd';

// 1=errors only; 4=all. Localhost uses 4 so [FP_TRACE] + debug lines are visible during forgot-password testing.
$host_for_log = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
$is_local_host = ($host_for_log === 'localhost' || strpos($host_for_log, '127.0.0.1') === 0);
$config['log_threshold'] = $is_local_host ? 4 : 1;
$config['log_path'] = '';
$config['log_file_extension'] = '';
$config['log_file_permissions'] = 0644;
$config['log_date_format'] = 'Y-m-d H:i:s';

$config['encryption_key'] = 'ElintOmWebshopAIChangeMe32chars!!';
$config['sess_driver'] = 'files';
$config['sess_cookie_name'] = 'ci_session_elintomapi';
$config['sess_expiration'] = 7200;
$config['sess_save_path'] = APPPATH . 'cache/sessions';
$config['sess_match_ip'] = FALSE;
$config['sess_time_to_update'] = 300;
$config['sess_regenerate_destroy'] = FALSE;
// PHP 8.4 default session ID length (32 hex). CI3 originally assumed 40; Session.php now matches ini.
if ((int) ini_get('session.sid_length') < 32) {
    ini_set('session.sid_length', '32');
    ini_set('session.sid_bits_per_character', '4');
}
$config['cookie_prefix'] = '';
$config['cookie_domain'] = '';
$config['cookie_path'] = '/';
// Secure cookies are not stored on plain http://localhost — CSRF then fails with 403 on webshop_request.
$config['cookie_secure'] = $is_local_host ? FALSE : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$config['cookie_httponly'] = TRUE;
$config['standardize_newlines'] = TRUE;
$config['global_xss_filtering'] = FALSE;
$config['csrf_protection'] = TRUE;
$config['csrf_token_name'] = 'elintom_csrf_token';
$config['csrf_cookie_name'] = 'elintom_csrf_cookie';
$config['csrf_expire'] = 7200;
$config['csrf_regenerate'] = TRUE;
$config['csrf_exclude_uris'] = array(
    'whatsapp/webhook',
    // Storefront auth + AJAX (session/cookie issues on local http://localhost; forms still send elintom_csrf_token when possible).
    'webshop/login',
    'webshop/register',
    'webshop/forgot_password',
    'webshop/webshop_request',
    // Payment gateways redirect/POST back without elintom_csrf_token (CCAvenue, Paytm, Razorpay, Instamojo).
    'webshop/payment_cancel',
    'webshop/payment_declined',
    'webshop/payment_ccavResponseHandler',
    'webshop/payment_ccavRequestHandler',
    'webshop/payment_paytmResponseHandler',
    'webshop/payment_instamojoResponseHandler',
    'webshop/razorpay_verify',
);
$config['compress_output'] = FALSE;
$config['time_reference'] = 'local';
$config['rewrite_short_tags'] = TRUE;
$config['proxy_ips'] = '';
