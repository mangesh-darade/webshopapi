<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ElintOm POS API client — POST {ElintOm}/api3/eshop (webshop catalogue & settings).
 * Same protocol as POS offline sync (privatekey + action).
 *
 * Server: ElintOm Api3::eshop() — validates key vs sma_settings.api_privatekey,
 * requires POS ≥ 3 and api_access enabled.
 */
class Elintom_api_client {

    protected $CI;
    protected $endpoint;
    protected $private_key;
    protected $last_error;
    protected $last_raw;

    public function __construct($params = array()) {
        $this->CI =& get_instance();
        $this->CI->config->load('elintom_api', true);

        $base = isset($params['base_url'])
            ? $params['base_url']
            : $this->CI->config->item('elintom_api_base_url', 'elintom_api');

        $key = isset($params['private_key'])
            ? $params['private_key']
            : $this->CI->config->item('elintom_api_private_key', 'elintom_api');

        $this->endpoint = $this->normalize_eshop_endpoint($base);
        $this->private_key = (string) $key;
        $this->last_error = null;
        $this->last_raw = null;
    }

    protected function normalize_eshop_endpoint($base) {
        $base = trim((string) $base);
        if ($base === '') {
            return '';
        }
        if (stripos($base, 'api3') !== false && stripos($base, 'eshop') !== false) {
            return rtrim($base, '/');
        }
        return rtrim($base, '/') . '/api3/eshop';
    }

    public function get_last_error() {
        return $this->last_error;
    }

    public function get_last_raw_response() {
        return $this->last_raw;
    }

    /**
     * Decode JSON; tolerate UTF-8 BOM and stray output before/after the object.
     */
    protected function decode_api_json($body) {
        if ($body === null || $body === '') {
            return null;
        }
        $body = (string) $body;
        if (strncmp($body, "\xEF\xBB\xBF", 3) === 0) {
            $body = substr($body, 3);
        }
        $body = trim($body);
        $decoded = json_decode($body);
        if ($decoded !== null || $body === '' || strtolower($body) === 'null') {
            return $decoded;
        }
        $start = strpos($body, '{');
        $end = strrpos($body, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($body, $start, $end - $start + 1);
            $decoded = json_decode($slice);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        return null;
    }

    public function eshop_post($action, array $extra = array()) {
        $this->last_error = null;
        $this->last_raw = null;

        if ($this->endpoint === '') {
            $this->last_error = 'elintom_api_base_url is not configured';
            return null;
        }
        if ($this->private_key === '') {
            $this->last_error = 'elintom_api_private_key is not configured';
            return null;
        }

        $fields = array_merge(
            array(
                'privatekey' => $this->private_key,
                'action' => $action,
            ),
            $extra
        );

        $post_data = http_build_query($fields);
        $body = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($this->endpoint);
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                ),
            ));
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($errno) {
                $this->last_raw = $body;
                $this->last_error = 'cURL: ' . $err;
                return null;
            }
        } else {
            $ctx = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $post_data,
                    'timeout' => 90,
                    'ignore_errors' => true,
                ),
            ));
            $body = @file_get_contents($this->endpoint, false, $ctx);
            if ($body === false) {
                $this->last_error = 'HTTP request failed (enable php_curl extension or allow_url_fopen for streams)';
                return null;
            }
        }

        $this->last_raw = $body;

        $decoded = $this->decode_api_json($body);
        if ($decoded === null && $body !== '' && strtolower(trim($body)) !== 'null') {
            $hint = '';
            $trim = ltrim($body);
            if ($trim !== '' && ($trim[0] === '<' || stripos($trim, '<!DOCTYPE') !== false || stripos($trim, '<html') !== false)) {
                $hint = ' ElintOm returned HTML (often a PHP/DB error page). Fix ElintOm first: open the same URL in a browser, check app/config/database.php matches HTTP_HOST (use case 127 for 127.0.0.1).';
            } elseif (strlen($body) > 0) {
                $hint = ' First bytes: ' . substr(preg_replace('/\s+/', ' ', $body), 0, 180);
            }
            $this->last_error = 'Invalid JSON from API.' . $hint;
            return null;
        }

        if (is_object($decoded) && isset($decoded->status)) {
            $st = strtoupper((string) $decoded->status);
            if ($st === 'ERROR') {
                $msg = isset($decoded->mag) ? $decoded->mag : (isset($decoded->msg) ? $decoded->msg : 'API error');
                if (isset($decoded->error_code)) {
                    $msg .= ' (code ' . $decoded->error_code . ')';
                }
                $this->last_error = $msg;
            }
        }

        return $decoded;
    }

    public function get_store_settings() {
        return $this->eshop_post('getsettings');
    }

    public function get_parent_categories($keyword = '') {
        return $this->eshop_post('getparentcategories', array('keyword' => $keyword));
    }

    public function get_all_categories($keyword = '') {
        return $this->eshop_post('getallcategories', array('keyword' => $keyword));
    }

    public function get_subcategories($parent_id, $keyword = '') {
        return $this->eshop_post('getsubcategories', array(
            'parent_id' => $parent_id,
            'keyword' => $keyword,
        ));
    }

    public function get_all_products(array $params = array()) {
        $defaults = array(
            'keyword' => '',
            'category_id' => '',
            'subcategory_id' => '',
            'offset' => '',
            'limit' => '',
        );
        return $this->eshop_post('getallproducts', array_merge($defaults, $params));
    }

    public function get_product_stocks($category_id = 0, $listbycategory = 0) {
        return $this->eshop_post('getproductstocks', array(
            'category_id' => $category_id,
            'listbycategory' => $listbycategory,
        ));
    }

    public function get_category_name_by_id($category_id) {
        return $this->eshop_post('getcategorynamebyid', array('category_id' => $category_id));
    }

    public function get_category_id_by_name($category_name) {
        return $this->eshop_post('getcategoryidbyname', array('category_name' => $category_name));
    }
}
