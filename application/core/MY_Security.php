<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Allow CSRF on JSON POST bodies (My Account AJAX: manage_address_webshop, profile_update_webshop).
 * CI3 only checks $_POST; php://input is read once and cached for controllers.
 *
 * Session CSRF fallback uses CI session only after the controller is bootstrapped (Input
 * runs csrf_verify before CI_Controller / get_instance() exist).
 */
class MY_Security extends CI_Security {

    public function csrf_verify()
    {
        $this->_hydrate_csrf_from_json_body();
        $this->_hydrate_csrf_from_session_fallback();
        $out = parent::csrf_verify();
        $this->_persist_csrf_hash_to_session();
        return $out;
    }

    public function csrf_set_cookie()
    {
        $out = parent::csrf_set_cookie();
        $this->_persist_csrf_hash_to_session();
        return $out;
    }

    /**
     * True only after CodeIgniter has loaded Controller.php and get_instance().
     */
    protected function _ci_session_available()
    {
        return class_exists('CI_Controller', FALSE)
            && function_exists('get_instance');
    }

    /**
     * Keep CSRF hash in PHP session so POST works when the CSRF cookie cannot be stored (local HTTP).
     */
    protected function _persist_csrf_hash_to_session()
    {
        $hash = $this->get_csrf_hash();
        if ($hash === '' || $hash === null) {
            return;
        }
        if ($this->_ci_session_available()) {
            $CI =& get_instance();
            if (isset($CI->session)) {
                $CI->session->set_userdata('elintom_csrf_hash', $hash);
            }
        }
        // csrf_verify() runs before CI Session is loaded; mirror hash for POST fallback on localhost/incognito.
        if (!is_cli() && !headers_sent()) {
            $expire = time() + (int) config_item('csrf_expire');
            $path = config_item('cookie_path');
            $path = ($path !== null && $path !== '') ? $path : '/';
            $domain = config_item('cookie_domain');
            $domain = ($domain !== null && $domain !== '') ? $domain : '';
            setcookie(
                'elintom_csrf_backup',
                $hash,
                $expire,
                $path,
                $domain,
                (bool) config_item('cookie_secure'),
                true
            );
        }
    }

    /**
     * When the CSRF cookie is missing (common on http://localhost with cookie_secure),
     * accept the token from session if it was set on a previous GET in the same session.
     */
    protected function _hydrate_csrf_from_session_fallback()
    {
        if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') {
            return;
        }
        $token_name = $this->_csrf_token_name;
        if ($token_name === '' || isset($_COOKIE[$this->_csrf_cookie_name])) {
            return;
        }
        if (!isset($_POST[$token_name])) {
            return;
        }
        if (!$this->_ci_session_available()) {
            return;
        }
        $CI =& get_instance();
        if (!isset($CI->session)) {
            return;
        }
        $stored = '';
        if ($this->_ci_session_available() && isset($CI->session)) {
            $stored = $CI->session->userdata('elintom_csrf_hash');
        }
        if (($stored === '' || $stored === null) && isset($_COOKIE['elintom_csrf_backup'])) {
            $stored = (string) $_COOKIE['elintom_csrf_backup'];
        }
        if (is_string($stored) && $stored !== '' && hash_equals($stored, (string) $_POST[$token_name])) {
            $_COOKIE[$this->_csrf_cookie_name] = $stored;
        }
    }

    /**
     * When Content-Type is application/json, copy elintom_csrf_token from the body into $_POST.
     */
    protected function _hydrate_csrf_from_json_body()
    {
        if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') {
            return;
        }

        $token_name = $this->_csrf_token_name;
        if ($token_name !== '' && isset($_POST[$token_name])) {
            return;
        }

        $content_type = '';
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $content_type = (string) $_SERVER['CONTENT_TYPE'];
        } elseif (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $content_type = (string) $_SERVER['HTTP_CONTENT_TYPE'];
        }
        if (stripos($content_type, 'application/json') === false) {
            return;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return;
        }

        if (!defined('WEBSHOP_RAW_JSON_BODY')) {
            define('WEBSHOP_RAW_JSON_BODY', $raw);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        if (isset($decoded[$token_name])) {
            $_POST[$token_name] = $decoded[$token_name];
        }

        foreach ($decoded as $key => $value) {
            if ($key === $token_name || is_array($value) || is_object($value)) {
                continue;
            }
            if (!isset($_POST[$key])) {
                $_POST[$key] = $value;
            }
        }
    }
}
