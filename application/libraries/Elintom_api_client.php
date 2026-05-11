<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Elintom_api_client — HTTP client for the WebshopAPI → ElintOm bridge.
 *
 * URLs and keys: application/config/elintom_api.php (`elintom_api_webshop_endpoint_path`,
 * `elintom_api_legacy_endpoint_path`, `elintom_api_base_url`, `elintom_api_private_key`).
 *
 * Primary endpoint  : POST {ElintOm}/webshop_api/index   (new dedicated controller)
 * Legacy endpoint   : POST {ElintOm}/api3/eshop           (kept for catalogue fallback)
 *
 * Authentication    : POST field  privatekey = elintom_api_private_key config value
 *
 * Usage (in a CI3 controller / model):
 *   $this->load->library('elintom_api_client');
 *   $res = $this->elintom_api_client->get_categories();
 */
class Elintom_api_client {

    protected $CI;

    /** Full URL to ElintOm/webshop_api/index */
    protected $endpoint;

    /** Full URL to ElintOm/api3/eshop (legacy catalogue) */
    protected $legacy_endpoint;

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

        $base = rtrim(str_replace('\\', '/', (string) $base), '/');

        $webshop_path = $this->CI->config->item('elintom_api_webshop_endpoint_path', 'elintom_api');
        $legacy_path  = $this->CI->config->item('elintom_api_legacy_endpoint_path', 'elintom_api');
        if ($webshop_path === null || $webshop_path === '') {
            $webshop_path = 'webshop_api/index';
        }
        if ($legacy_path === null || $legacy_path === '') {
            $legacy_path = 'api3/eshop';
        }
        $webshop_path = trim(str_replace('\\', '/', (string) $webshop_path), '/');
        $legacy_path  = trim(str_replace('\\', '/', (string) $legacy_path), '/');

        $this->endpoint        = $base . '/' . $webshop_path;
        $this->legacy_endpoint = $base . '/' . $legacy_path;

        $this->private_key = (string) $key;
        $this->last_error  = null;
        $this->last_raw    = null;
    }

    /* ----------------------------------------------------------------
     * Public diagnostics
     * ---------------------------------------------------------------- */

    public function get_last_error()        { return $this->last_error; }
    public function get_last_raw_response() { return $this->last_raw;   }

    protected function _api_success($res) {
        return $res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS';
    }

    /** @return int|null */
    protected function _api_error_code($res) {
        if (!$res || !is_object($res) || !isset($res->error_code)) {
            return null;
        }
        return (int) $res->error_code;
    }

    /* ================================================================
     * CORE HTTP LAYER
     * ================================================================ */

    /**
     * POST to the new Webshop_api endpoint.
     *
     * @param  string $action  Value of POST[action]
     * @param  array  $extra   Additional POST fields
     * @return object|null     Decoded JSON or null on failure
     */
    public function post($action, array $extra = array()) {
        return $this->_post($this->endpoint, $action, $extra);
    }

    /**
     * POST to the legacy api3/eshop endpoint (catalogue actions).
     */
    public function legacy_post($action, array $extra = array()) {
        return $this->_post($this->legacy_endpoint, $action, $extra);
    }

    /* ================================================================
     * STORE SETTINGS
     * ================================================================ */

    public function get_settings() {
        $res = $this->post('getsettings');
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            return $res;
        }
        // Fallback: ElintOm Api3::eshop → getsettings (MY_Controller path). Same auth key.
        $legacy = $this->legacy_post('getsettings');
        if ($legacy && isset($legacy->status) && strtoupper((string) $legacy->status) === 'SUCCESS' && isset($legacy->setting)) {
            $flat = is_object($legacy->setting) ? $legacy->setting : (object) (array) $legacy->setting;
            $out           = new stdClass();
            $out->status   = 'SUCCESS';
            $out->webshop_settings = $flat;
            $out->payment_gateways = new stdClass();
            $out->pos_settings     = $flat;
            $out->pos_config       = $flat;
            $out->website_setting  = isset($flat->website_setting) ? $flat->website_setting : array();
            $this->last_error      = null;
            return $out;
        }
        return $res !== null ? $res : $legacy;
    }

    public function get_next_reference() {
        return $this->post('getnextref');
    }

    public function get_cms_page($url_path) {
        return $this->post('getcmspage', array(
            'url' => $url_path,
        ));
    }

    public function get_cms_pages() {
        return $this->post('getcmspages');
    }

    /* ================================================================
     * CATALOGUE  (webshop_ endpoint, falls back to legacy api3 if needed)
     * ================================================================ */

    public function get_categories($customer_group_id = null) {
        $extra = array();
        if ($customer_group_id !== null && $customer_group_id !== '') {
            $extra['customer_group_id'] = $customer_group_id;
        }
        return $this->post('getcategories', $extra);
    }

    public function get_sliders() {
        return $this->post('getsliders');
    }

    public function get_products_list(array $params = array()) {
        $defaults = array(
            'by'       => null,
            'byid'     => null,
            'use_hash' => 0,
            'limit'    => 0,
            'page'     => 1,
        );
        return $this->post('getproductslist', array_merge($defaults, $params));
    }

    public function get_product_by_hash($hash) {
        return $this->post('getproductbyhash', array('product_hash' => $hash));
    }

    public function get_entity_tags($entity_code, $entity_id) {
        return $this->post('getentitytags', array(
            'entity_code' => $entity_code,
            'entity_id' => (int) $entity_id,
        ));
    }

    public function search_products($keyword, $category_id = null) {
        return $this->post('searchproducts', array(
            'keyword'     => $keyword,
            'category_id' => $category_id,
        ));
    }

    /* ── legacy catalogue helpers (api3/eshop) ── */

    public function get_parent_categories($keyword = '') {
        return $this->legacy_post('getparentcategories', array('keyword' => $keyword));
    }

    public function get_all_categories($keyword = '') {
        return $this->legacy_post('getallcategories', array('keyword' => $keyword));
    }

    public function get_subcategories($parent_id, $keyword = '') {
        return $this->legacy_post('getsubcategories', array(
            'parent_id' => $parent_id,
            'keyword'   => $keyword,
        ));
    }

    public function get_all_products(array $params = array()) {
        $defaults = array('keyword' => '', 'category_id' => '', 'subcategory_id' => '',
                          'offset' => '', 'limit' => '');
        return $this->legacy_post('getallproducts', array_merge($defaults, $params));
    }

    public function get_product_stocks($category_id = 0, $listbycategory = 0) {
        return $this->legacy_post('getproductstocks', array(
            'category_id'    => $category_id,
            'listbycategory' => $listbycategory,
        ));
    }

    /* ================================================================
     * GEO
     * ================================================================ */

    /**
     * Geo lists are implemented on webshop_api/index only (ElintOm Webshop_api.php).
     * api3/eshop does not dispatch getstates/getcountries — it returns error 103 Invalid request.
     * Some ElintOm builds register snake_case actions only; retry on 103.
     */
    public function get_states() {
        $res = $this->post('getstates');
        if ($this->_api_success($res)) {
            return $res;
        }
        if ($res && $this->_api_error_code($res) === 103) {
            $try = $this->post('get_states');
            if ($this->_api_success($try)) {
                return $try;
            }
        }
        $legacy = $this->legacy_post('getstates');
        if ($this->_api_success($legacy)) {
            return $legacy;
        }
        return $res !== null ? $res : $legacy;
    }

    public function get_countries() {
        $res = $this->post('getcountries');
        if ($this->_api_success($res)) {
            return $res;
        }
        if ($res && $this->_api_error_code($res) === 103) {
            $try = $this->post('get_countries');
            if ($this->_api_success($try)) {
                return $try;
            }
        }
        $legacy = $this->legacy_post('getcountries');
        if ($this->_api_success($legacy)) {
            return $legacy;
        }
        return $res !== null ? $res : $legacy;
    }

    /* ================================================================
     * CUSTOMER
     * ================================================================ */

    public function get_customer(array $filter) {
        return $this->post('getcustomer', $filter);
    }

    public function create_customer(array $data) {
        return $this->post('createcustomer', $data);
    }

    public function login_customer($login, $password) {
        return $this->post('logincheck', array('login' => $login, 'password' => $password));
    }

    public function register_check($phone = null, $email = null) {
        return $this->post('registercheck', array('phone' => $phone, 'email' => $email));
    }

    /* ================================================================
     * ADDRESSES
     * ================================================================ */

    public function get_addresses($customer_id, $address_id = null) {
        return $this->post('getaddresses', array(
            'customer_id' => $customer_id,
            'address_id'  => $address_id,
        ));
    }

    public function add_address(array $data) {
        return $this->post('addaddress', $data);
    }

    /* ================================================================
     * ORDERS
     * ================================================================ */

    public function add_order(array $order, array $items) {
        return $this->post('addorder', array(
            'order' => json_encode($order),
            'items' => json_encode($items),
        ));
    }

    public function get_order($order_id) {
        return $this->post('getorder', array('order_id' => (int) $order_id));
    }

    public function get_gateway_credentials() {
        return $this->post('getgatewaycredentials', array());
    }

    public function get_customer_sales($customer_id, $sale_status = '') {
        return $this->post('getcustomersales', array(
            'customer_id' => $customer_id,
            'sale_status' => $sale_status,
        ));
    }

    /* ================================================================
     * COUPONS
     * ================================================================ */

    public function apply_coupon($code, $cart_total = 0) {
        return $this->post('applycoupon', array(
            'coupon_code' => $code,
            'cart_total'  => $cart_total,
        ));
    }

    /* ================================================================
     * WISHLIST
     * ================================================================ */

    public function get_wishlist($user_id) {
        return $this->post('getwishlist', array('user_id' => $user_id));
    }

    public function add_wishlist($user_id, $product_id, $option_id = null) {
        return $this->post('addwishlist', array(
            'user_id'    => $user_id,
            'product_id' => $product_id,
            'option_id'  => $option_id,
        ));
    }

    public function remove_wishlist($user_id, $product_id, $option_id = null) {
        return $this->post('removewishlist', array(
            'user_id'    => $user_id,
            'product_id' => $product_id,
            'option_id'  => $option_id,
        ));
    }

    /* ================================================================
     * REVIEWS
     * ================================================================ */

    public function add_product_review(array $data) {
        return $this->post('submitproductreview', $data);
    }

    public function get_product_reviews($product_id, $limit = 200) {
        $extra = array('product_id' => (int) $product_id);
        if ($limit > 0) {
            $extra['limit'] = (int) $limit;
        }
        return $this->post('getproductreviews', $extra);
    }

    public function get_product_rating($product_id) {
        return $this->post('getproductrating', array('product_id' => $product_id));
    }

    /* ================================================================
     * COMPANY / BILLER
     * ================================================================ */

    public function get_company($biller_id) {
        return $this->post('getcompany', array('biller_id' => $biller_id));
    }

    /* ================================================================
     * PRIVATE HTTP CORE
     * ================================================================ */

    protected function _post($url, $action, array $extra = array()) {
        $this->last_error = null;
        $this->last_raw   = null;

        if (!$url) {
            $this->last_error = 'elintom_api_base_url is not configured';
            return null;
        }
        if ($this->private_key === '') {
            $this->last_error = 'elintom_api_private_key is not configured';
            return null;
        }

        $fields    = array_merge(
            array('privatekey' => $this->private_key, 'action' => $action),
            $this->_tenant_context_fields(),
            $extra
        );
        $post_data = http_build_query($fields);
        $body      = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
            ));
            $body  = curl_exec($ch);
            $errno = curl_errno($ch);
            $err   = curl_error($ch);
            $http  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($errno) {
                $this->last_raw   = $body;
                $this->last_error = 'cURL error: ' . $err;
                return null;
            }
        } else {
            $http = 0;
            $ctx  = stream_context_create(array('http' => array(
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $post_data,
                'timeout'       => 90,
                'ignore_errors' => true,
            )));
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                $this->last_error = 'HTTP request failed (enable php_curl or allow_url_fopen)';
                return null;
            }
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $http = (int) $m[1];
            } else {
                $http = 0;
            }
        }

        $this->last_raw = $body;
        $decoded        = $this->_decode_json($body);

        if ($body === null || $body === '') {
            $code = isset($http) && $http > 0 ? ' HTTP ' . $http : '';
            $hint500 = (isset($http) && $http >= 500)
                ? ' On 500 with no body, open ElintOm `app/models/Webshop_api_model.php` get_settings() and check Apache/PHP error_log for a fatal (often a failed DB query).'
                : '';
            $this->last_error = 'Empty response from ElintOm' . $code
                . ' at ' . $url
                . '. The server returned no body — check that ElintOm is installed, `webshop_api` exists, and the route is not blocked or output-buffered away.'
                . $hint500;
            return null;
        }

        $trimmed = trim((string) $body);
        if ($trimmed === '' || strtolower($trimmed) === 'null') {
            $this->last_error = 'ElintOm returned an empty or JSON-null body. Check private key, API access in ElintOm settings, and Webshop_api getsettings handler.';
            return null;
        }

        if ($decoded === null) {
            $trim = ltrim($body);
            $hint = ($trim !== '' && ($trim[0] === '<' || stripos($trim, '<html') !== false))
                ? ' ElintOm returned HTML — check PHP/DB errors in ElintOm.'
                : ' First bytes: ' . substr(preg_replace('/\s+/', ' ', $body), 0, 200);
            $this->last_error = 'Invalid JSON from ElintOm API.' . $hint;
            return null;
        }

        if (is_object($decoded) && isset($decoded->status) && strtoupper($decoded->status) === 'ERROR') {
            $msg = isset($decoded->msg) ? $decoded->msg : (isset($decoded->mag) ? $decoded->mag : 'API error');
            if (isset($decoded->error_code)) {
                $msg .= ' (code ' . $decoded->error_code . ')';
            }
            $this->last_error = $msg;
        }

        return $decoded;
    }

    /**
     * Sent on every POST so ElintOm can resolve multi-tenant shop (getsettings, catalogue, images).
     * ElintOm may use http_host / subdomain to pick sma_settings + assets/mdata/{folder}/.
     */
    protected function _tenant_context_fields() {
        if (!isset($_SERVER['HTTP_HOST']) || (string) $_SERVER['HTTP_HOST'] === '') {
            return array();
        }
        $host = strtolower(trim((string) $_SERVER['HTTP_HOST']));
        $parts = explode('.', $host);
        $sub = isset($parts[0]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $parts[0]) : '';
        if ($sub === 'www' && isset($parts[1])) {
            $sub = preg_replace('/[^a-zA-Z0-9_-]/', '', $parts[1]);
        }
        return array(
            'http_host'    => $host,
            'shop_host'    => $host,
            'subdomain'    => $sub,
            'shop_subdomain' => $sub,
        );
    }

    /** Decode JSON tolerating UTF-8 BOM and stray output around the JSON object. */
    protected function _decode_json($body) {
        if ($body === null || $body === '') return null;
        $body = (string) $body;
        if (strncmp($body, "\xEF\xBB\xBF", 3) === 0) $body = substr($body, 3);
        $body    = trim($body);
        $decoded = json_decode($body);
        if ($decoded !== null) return $decoded;
        $start = strpos($body, '{');
        $end   = strrpos($body, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($body, $start, $end - $start + 1));
            if ($decoded !== null) return $decoded;
        }
        return null;
    }
}
