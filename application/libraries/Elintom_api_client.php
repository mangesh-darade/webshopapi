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

    /**
     * Validate HTTP_HOST once before using it in outbound context fields.
     */
    protected function _safe_http_host($keep_port = false) {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return '';
        }
        $raw = trim((string) $_SERVER['HTTP_HOST']);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/[\x00-\x1F\x7F]/', '', $raw);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('/^([a-z0-9.-]+)(?::(\d{1,5}))?$/i', $raw, $m)) {
            return '';
        }
        $host = strtolower($m[1]);
        $port = isset($m[2]) ? (int) $m[2] : 0;
        if ($host === '' || $host[0] === '.' || substr($host, -1) === '.') {
            return '';
        }
        if ($port < 0 || $port > 65535) {
            return '';
        }
        return ($keep_port && $port > 0) ? ($host . ':' . $port) : $host;
    }

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
            return $this->_filter_storefront_settings_payload($res);
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
            $out->website_setting_sections = isset($flat->website_setting_sections)
                ? $flat->website_setting_sections
                : (object) array('header' => array(), 'footer' => array());
            $this->last_error      = null;
            return $this->_filter_storefront_settings_payload($out);
        }
        return $res !== null ? $res : $legacy;
    }

    /**
     * Client-side defense: only is_active === 1 header/footer rows reach the storefront.
     *
     * @param object $res getsettings SUCCESS payload
     * @return object
     */
    protected function _filter_storefront_settings_payload($res) {
        if (!$res || !is_object($res)) {
            return $res;
        }
        $this->CI->load->helper('webshop_helper');
        if (isset($res->website_setting_sections)) {
            $res->website_setting_sections = webshop_filter_website_setting_sections_object($res->website_setting_sections);
        }
        if (isset($res->website_setting) && is_array($res->website_setting)) {
            $res->website_setting = webshop_filter_active_website_setting_rows($res->website_setting);
        }
        return $res;
    }

    public function get_next_reference() {
        return $this->post('getnextref');
    }

    public function get_cms_page($url_path) {
        return $this->post('getcmspage', array(
            'url' => $url_path,
        ));
    }

    /**
     * @param string|null $placement header|footer|null (all published static pages)
     */
    public function get_cms_pages($placement = null) {
        $extra = array();
        $placement = strtolower(trim((string) $placement));
        if (in_array($placement, array('header', 'footer'), true)) {
            $extra['placement'] = $placement;
        }
        return $this->post('getcmspages', $extra);
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
        $merged = array_merge($defaults, $params);
        if (isset($merged['byid']) && is_array($merged['byid'])) {
            $ids = array();
            foreach ($merged['byid'] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            $merged['byid'] = $ids === array() ? null : implode(',', $ids);
        }
        return $this->post('getproductslist', $merged);
    }

    public function get_product_by_hash($hash, $product_id = null) {
        $extra = array('product_hash' => (string) $hash);
        $pid = (int) $product_id;
        if ($pid > 0) {
            $extra['product_id'] = $pid;
        }
        return $this->post('getproductbyhash', $extra);
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

    /**
     * Forgot-password OTP — ElintOm action passwordotpsend (WhatsApp + SMS + email on POS side).
     * Optional whatsapp_phone: E.164-style digits for Cheerio when local phone is 10-digit only.
     */
    public function send_password_otp($phone, $otp, $whatsapp_phone = null) {
        $payload = array(
            'phone' => $phone,
            'otp'   => $otp,
        );
        if ($whatsapp_phone !== null && $whatsapp_phone !== '') {
            $payload['whatsapp_phone'] = preg_replace('/\D/', '', (string) $whatsapp_phone);
        }
        return $this->post('passwordotpsend', $payload);
    }

    /**
     * Ask ElintOm to update the customer password after OTP verification.
     * OTP is verified by the storefront against its own session before
     * calling this — ElintOm only writes the new password.
     */
    public function reset_customer_password($phone, $new_password) {
        return $this->post('customerresetpassword', array(
            'phone'        => $phone,
            'new_password' => $new_password,
        ));
    }

    /**
     * Post-checkout WhatsApp — ElintOm action notifywebshoporderwhatsapp.
     * Sends storefront_base_url so receipt/track links point at this shop, not the POS host.
     * Cheerio key and customer phone are read from ElintOm DB/settings, not from webshopapi.
     */
    public function notify_webshop_order_whatsapp($order_id, $flag = 'true') {
        $extra = array(
            'order_id' => (int) $order_id,
            'flag'     => (string) $flag,
        );
        $storefront = rtrim((string) $this->CI->config->item('base_url'), '/');
        if ($storefront !== '') {
            $extra['storefront_base_url'] = $storefront;
        }
        return $this->post('notifywebshoporderwhatsapp', $extra);
    }

    /**
     * Ask ElintOm to send a post-checkout order confirmation email for a sale.
     */
    public function notify_webshop_order_email($order_id) {
        $extra = array(
            'order_id' => (int) $order_id,
        );
        $storefront = rtrim((string) $this->CI->config->item('base_url'), '/');
        if ($storefront !== '') {
            $extra['storefront_base_url'] = $storefront;
        }
        return $this->post('notifywebshoporderemail', $extra);
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

    public function update_address(array $data) {
        return $this->post('updateaddress', $data);
    }

    public function delete_address(array $data) {
        return $this->post('deleteaddress', $data);
    }

    public function set_address_default(array $data) {
        return $this->post('setaddressdefault', $data);
    }

    /* ================================================================
     * ORDERS
     * ================================================================ */

    public function add_order(array $order, array $items) {
        $allowed = function_exists('webshop_elintom_order_item_allowed_keys')
            ? webshop_elintom_order_item_allowed_keys()
            : array(
                'product_id', 'product_code', 'article_code', 'product_name', 'product_type',
                'option_id', 'net_unit_price', 'unit_discount', 'unit_tax', 'invoice_unit_price',
                'invoice_net_unit_price', 'unit_price', 'quantity', 'net_price', 'invoice_total_net_unit_price',
                'warehouse_id', 'item_tax', 'tax_method', 'tax_rate_id', 'tax', 'discount', 'item_discount',
                'subtotal', 'real_unit_price', 'product_unit_id', 'product_unit_code', 'unit_quantity',
                'mrp', 'hsn_code', 'note', 'delivery_status', 'pending_quantity', 'delivered_quantity',
                'gst_rate', 'cgst', 'sgst', 'igst', 'item_weight',
            );
        $allowed_flip = array_flip($allowed);

        $clean_items = array();
        foreach ($items as $item) {
            $row = is_array($item) ? $item : (array) $item;
            if (function_exists('webshop_sanitize_order_line_for_elintom')) {
                $row = webshop_sanitize_order_line_for_elintom($row);
            }
            $pick = array();
            foreach ($row as $k => $v) {
                if (is_string($k) && isset($allowed_flip[$k]) && !is_array($v) && !is_object($v)) {
                    $pick[$k] = $v;
                }
            }
            if (!empty($pick) && (int) (isset($pick['product_id']) ? $pick['product_id'] : 0) > 0) {
                $clean_items[] = $pick;
            }
        }
        $items_json = json_encode($clean_items);
        if ($items_json !== false && (stripos($items_json, 'variant_price') !== false || stripos($items_json, 'variant_id') !== false)) {
            log_message('error', 'Elintom_api_client::add_order — variant_* still in items JSON after sanitize');
            $decoded = json_decode($items_json, true);
            if (is_array($decoded)) {
                $clean_items = array();
                foreach ($decoded as $line) {
                    if (function_exists('webshop_sanitize_order_line_for_elintom')) {
                        $row = webshop_sanitize_order_line_for_elintom(is_array($line) ? $line : (array) $line);
                        if (!empty($row)) {
                            $clean_items[] = $row;
                        }
                    }
                }
                $items_json = json_encode($clean_items);
            }
        }
        return $this->post('addorder', array(
            'order' => json_encode($order),
            'items' => $items_json,
        ));
    }

    public function get_order($order_id, $reference_no = null) {
        $extra = array('order_id' => (int) $order_id);
        if ($reference_no !== null && (string) $reference_no !== '') {
            $extra['reference_no'] = (string) $reference_no;
        }
        return $this->post('getorder', $extra);
    }

    /** Guest tracking URL token (md5 of order id) from WhatsApp / email links. */
    public function get_order_by_track_hash($track_hash) {
        return $this->post('getorderbytrackhash', array(
            'track_hash' => strtolower(trim((string) $track_hash)),
        ));
    }

    /**
     * Persist CCAvenue success on ElintOm (required when the storefront has no local orders DB).
     */
    public function record_ccavenue_payment(array $response_data) {
        return $this->post('recordccavenuepayment', array(
            'response_json' => json_encode($response_data),
        ));
    }

    /**
     * Cancel a webshop order on ElintOm.
     *
     * Used when the buyer aborts at the payment gateway or the gateway declines
     * payment — keeps ElintOm's order list clean of "ghost" rows that the buyer
     * never actually paid for. Only pre-payment orders (payment_status in
     * due/pending/Failed) are eligible — ElintOm rejects calls against paid sales.
     *
     * @param int    $order_id      ElintOm sale id (>0). Pass 0 when only ref is known.
     * @param string $reference_no  Optional reference (ES-YYYYMMDD-XXXXXX).
     * @param string $reason        Optional reason for the audit trail.
     */
    public function cancel_order($order_id, $reference_no = '', $reason = '') {
        $payload = array('order_id' => (int) $order_id);
        if ($reference_no !== null && (string) $reference_no !== '') {
            $payload['reference_no'] = (string) $reference_no;
        }
        if ($reason !== null && (string) $reason !== '') {
            $payload['reason'] = (string) $reason;
        }
        return $this->post('cancelorder', $payload);
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
     * CONTACT / LEADS
     * ================================================================ */

    public function submit_contact_lead(array $data) {
        return $this->post('submitcontactlead', $data);
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
            $this->_curl_apply_options($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
            ));
            $this->_curl_apply_options($ch, $this->_curl_ssl_options($url));
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

        $fp_actions = array('getcustomer', 'passwordotpsend', 'customerresetpassword');
        if (function_exists('webshop_forgot_password_log') && in_array(strtolower((string) $action), $fp_actions, true)) {
            $ok = is_object($decoded) && isset($decoded->status) && strtoupper((string) $decoded->status) === 'SUCCESS';
            if (!$ok) {
                webshop_forgot_password_log('elintom_api.' . $action, array(
                    'endpoint'   => $url,
                    'http'       => isset($http) ? (int) $http : 0,
                    'status'     => is_object($decoded) && isset($decoded->status) ? (string) $decoded->status : 'null',
                    'msg'        => is_object($decoded) && isset($decoded->msg) ? (string) $decoded->msg : null,
                    'error_code' => is_object($decoded) && isset($decoded->error_code) ? (int) $decoded->error_code : null,
                    'last_error' => $this->last_error,
                    'phone'      => isset($extra['phone']) ? $extra['phone'] : null,
                ));
            }
        }

        return $decoded;
    }

    /**
     * Apply cURL options one-by-one (PHP 8+ curl_setopt_array rejects unsupported SSL keys on some builds).
     *
     * @param resource $ch
     * @param array    $options
     */
    protected function _curl_apply_options($ch, array $options) {
        foreach ($options as $option => $value) {
            if (!is_int($option)) {
                continue;
            }
            @curl_setopt($ch, $option, $value);
        }
    }

    /**
     * cURL TLS options for HTTPS ElintOm endpoints.
     * WAMP/Windows often has no curl.cainfo in php.ini — use bundled application/libraries/cacert.pem.
     *
     * Config (elintom_api): elintom_api_ssl_verify (bool), elintom_api_ssl_ca_bundle (path).
     *
     * @param string $url Request URL (SSL options are skipped for http://)
     * @return array<int, mixed>
     */
    protected function _curl_ssl_options($url) {
        $parts = is_string($url) ? parse_url($url) : false;
        $scheme = (is_array($parts) && isset($parts['scheme'])) ? strtolower((string) $parts['scheme']) : '';
        if ($scheme !== 'https') {
            return array();
        }

        if (!defined('CURLOPT_SSL_VERIFYPEER')) {
            return array();
        }

        $this->CI->config->load('elintom_api', true);

        $verify = $this->CI->config->item('elintom_api_ssl_verify', 'elintom_api');
        if ($verify === null) {
            $verify = true;
        } elseif (!is_bool($verify)) {
            $filtered = filter_var($verify, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $verify = ($filtered !== null) ? $filtered : !empty($verify);
        }

        if (!$verify) {
            return array(
                CURLOPT_SSL_VERIFYPEER => false,
            );
        }

        $opts = array(
            CURLOPT_SSL_VERIFYPEER => true,
        );
        if (defined('CURLOPT_SSL_VERIFYHOST')) {
            $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        }

        $bundle = (string) $this->CI->config->item('elintom_api_ssl_ca_bundle', 'elintom_api');
        if ($bundle === '') {
            $bundle = APPPATH . 'libraries' . DIRECTORY_SEPARATOR . 'cacert.pem';
        }
        $bundle = str_replace('\\', '/', $bundle);
        if ($bundle !== '' && is_readable($bundle) && defined('CURLOPT_CAINFO')) {
            $opts[CURLOPT_CAINFO] = $bundle;
            return $opts;
        }

        foreach (array('curl.cainfo', 'openssl.cafile') as $ini_key) {
            $ini_path = ini_get($ini_key);
            if (is_string($ini_path) && $ini_path !== '' && is_readable($ini_path) && defined('CURLOPT_CAINFO')) {
                $opts[CURLOPT_CAINFO] = str_replace('\\', '/', $ini_path);
                return $opts;
            }
        }

        return $opts;
    }

    /**
     * Sent on every POST so ElintOm can resolve multi-tenant shop (getsettings, catalogue, images).
     * ElintOm may use http_host / subdomain to pick sma_settings + assets/mdata/{folder}/.
     */
    protected function _tenant_context_fields() {
        $host = $this->_safe_http_host(false);
        if ($host === '') {
            return array();
        }
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
