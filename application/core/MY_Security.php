<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Allow CSRF on JSON POST bodies (My Account AJAX: manage_address_webshop, profile_update_webshop).
 * CI3 only checks $_POST; php://input is read once and cached for controllers.
 */
class MY_Security extends CI_Security {

    public function csrf_verify()
    {
        $this->_hydrate_csrf_from_json_body();
        return parent::csrf_verify();
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
