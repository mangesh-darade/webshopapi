<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Webshop AI storefront — views copied from ElintOm; data from ElintOm POS Api3 only.
 */
class Webshop extends CI_Controller {

    /** @var array Exposed for views that use $this->data (e.g. nw_theme) */
    public $data = array();

    /** @var object|null Copied to CI_Loader so views can use $this->webshop_settings */
    public $webshop_settings;

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        $key = $this->elintom_config('elintom_api_private_key');
        if ($key === '') {
            $this->load_setup_page(
                'API private key is not set.',
                'Copy application/config/elintom_api.local.example.php to elintom_api.local.php and set elintom_api_private_key (same value as ElintOm Settings - API private key). Or edit application/config/elintom_api.php.'
            );
            return;
        }

        $data = $this->base_layout_data();

        $settings_resp = $this->elintom_api_client->get_store_settings();
        $settings_err = $this->elintom_api_client->get_last_error();
        if ($settings_resp === null && $settings_err) {
            $this->load_setup_page('Cannot reach ElintOm API.', $settings_err);
            return;
        }
        if (is_object($settings_resp) && isset($settings_resp->status) && strtoupper((string) $settings_resp->status) === 'ERROR') {
            $this->load_setup_page('ElintOm API rejected the request.', $settings_err ? $settings_err : 'Check private key and that API access is enabled in ElintOm.');
            return;
        }

        /* CMS rows for nw_theme (sma_website_setting), packaged by Api3::store_settings */
        $data['website_setting'] = $this->extract_setting_payload($settings_resp, 'website_setting');
        $data['webshop_settings'] = $this->map_webshop_settings($settings_resp);
        $data['strip_color'] = !empty($data['webshop_settings']->header_strip_style) ? (int) $data['webshop_settings']->header_strip_style : 1;
        $data['theme_color'] = !empty($data['webshop_settings']->theme_color) ? $data['webshop_settings']->theme_color : 'orange';
        $data['home_page'] = !empty($data['webshop_settings']->home_page) ? $data['webshop_settings']->home_page : 'theme_9';

        /* nw_theme expects custom_pages.header_strip when rendering homepage strips */
        $data['custom_pages'] = array(
            'header_strip' => array(),
        );

        $cat_resp = $this->elintom_api_client->get_parent_categories();
        $data['categories'] = $this->map_categories($cat_resp);
        $data['main_categories'] = isset($data['categories']['main']) ? $data['categories']['main'] : array();

        $data['themeSections'] = array();
        $data['features'] = array();
        $data['recent_viewed'] = array();
        $data['wishlist_count'] = 0;
        $data['cart_items'] = array();
        $data['cart_data'] = array();
        $data['restaurant_is_active'] = false;
        $data['category_brands'] = array();
        $data['all_brands'] = array();
        $data['api_warning'] = '';
        $cat_err = $this->elintom_api_client->get_last_error();
        if (($cat_resp === null || (is_object($cat_resp) && isset($cat_resp->status) && strtoupper((string) $cat_resp->status) === 'ERROR')) && $cat_err) {
            $data['api_warning'] = 'Categories could not be loaded: ' . $cat_err;
        }

        $data['sliders'] = $this->stub_sliders($data);

        $wt = $this->resolve_storefront_theme_slug($data['webshop_settings']);
        $data['webshop_settings']->webshop_theme = $wt;
        if ($wt === 'restaurant' && is_file(APPPATH . 'views/webshop/webshop_restaurant_t1/index.php')) {
            $this->load_view('webshop_restaurant_t1/index', $data);
        } elseif ($wt === 'nw' && is_file(APPPATH . 'views/webshop/nw_theme/index.php')) {
            $this->load_view('nw_theme/index', $data);
        } else {
            $home = isset($data['webshop_settings']->home_page) ? $data['webshop_settings']->home_page : 'theme_9';
            $home = preg_replace('/[^a-zA-Z0-9_\-]/', '', $home);
            if ($home === '' || !is_file(APPPATH . 'views/webshop/' . $home . '.php')) {
                $data['webshop_settings']->home_page = 'theme_9';
            } else {
                $data['webshop_settings']->home_page = $home;
            }
            $this->load_view('index', $data);
        }
    }

    public function service_off() {
        $data = $this->base_layout_data();
        $data['webshop_settings'] = $this->default_webshop_settings();
        $this->load_view('service_off', $data);
    }

    /**
     * Shown when API key is missing or store settings cannot be loaded.
     */
    protected function load_setup_page($title, $detail = '') {
        $this->load->view('webshop/setup_required', array(
            'title' => $title,
            'detail' => $detail,
            'base_url' => site_url(),
        ));
    }

    /**
     * Read elintom_api config (section or flat merge fallback).
     */
    protected function elintom_config($name) {
        $v = $this->config->item($name, 'elintom_api');
        if ($v !== null && $v !== '') {
            return is_string($v) ? trim($v) : $v;
        }
        $v = $this->config->item($name);
        return is_string($v) ? trim((string) $v) : $v;
    }

    protected function base_layout_data() {
        $uploads = $this->elintom_config('elintom_media_uploads_base_url');
        $uploads = $uploads ? rtrim($uploads, '/') . '/' : base_url();
        $tenant = $this->elintom_config('elintom_customer_assets_folder');

        $data = array();
        $data['assets'] = base_url('assets/webshop/');
        $data['uploads'] = $uploads;
        $data['thumbs'] = $uploads . 'thumbs/';
        $data['images'] = $uploads . 'images/';
        $data['Customer_assets'] = $tenant ? $tenant : 'default';
        $data['is_admin_login'] = false;
        $data['strip_color'] = 1;
        $data['theme_color'] = 'orange';
        $data['home_page'] = 'theme_9';
        $data['active_webshop'] = true;
        $data['website_setting'] = array();

        return $data;
    }

    /**
     * Pull nested payload from Api3 getsettings (setting may be object or array after decode).
     *
     * @param string $key e.g. website_setting
     * @return array List of stdClass rows (empty if missing)
     */
    protected function extract_setting_payload($resp, $key) {
        $out = array();
        if (!is_object($resp) || !isset($resp->status) || strtoupper((string) $resp->status) !== 'SUCCESS') {
            return $out;
        }
        if (!isset($resp->setting)) {
            return $out;
        }
        $s = $resp->setting;
        if (is_object($s) && isset($s->$key)) {
            $raw = $s->$key;
        } elseif (is_array($s) && isset($s[$key])) {
            $raw = $s[$key];
        } else {
            return $out;
        }
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $row) {
            if (is_array($row)) {
                $out[] = (object) $row;
            } elseif (is_object($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * Final layout theme: driven by sma_webshop_settings.webshop_theme merged into Api3 setting (ElintOm POS).
     */
    protected function resolve_storefront_theme_slug(stdClass $ws) {
        $wt = isset($ws->webshop_theme) ? strtolower(trim((string) $ws->webshop_theme)) : '';
        if ($wt === '') {
            return 'classic';
        }
        static $aliases = array(
            'default' => 'classic',
            'retail' => 'classic',
            'ecommerce' => 'classic',
            'shop' => 'classic',
        );
        if (isset($aliases[$wt])) {
            $wt = $aliases[$wt];
        }
        if (in_array($wt, array('classic', 'nw', 'restaurant'), true)) {
            return $wt;
        }
        return 'classic';
    }

    protected function default_webshop_settings() {
        $o = new stdClass();
        $o->home_page = 'theme_9';
        $o->theme_color = 'orange';
        $o->header_style = 'header_theme_default';
        $o->header_strip_style = 1;
        $o->favicon = 'favicon.ico';
        $o->webshop_theme = 'classic';
        $o->overselling = 0;
        return $o;
    }

    /**
     * Flatten Api3 getsettings payload.setting into the shape classic/nw/restaurant views expect.
     * Values originate from ElintOm DB (sma_webshop_settings merged in Api3::store_settings).
     */
    protected function map_webshop_settings($resp) {
        $o = $this->default_webshop_settings();
        if (!is_object($resp) || !isset($resp->status)) {
            return $o;
        }
        if (strtoupper((string) $resp->status) !== 'SUCCESS') {
            return $o;
        }
        if (!isset($resp->setting)) {
            return $o;
        }
        $s = $resp->setting;
        if (is_array($s)) {
            $s = json_decode(json_encode($s));
        }
        if (!is_object($s)) {
            return $o;
        }
        foreach (get_object_vars($s) as $k => $v) {
            $o->$k = $v;
        }
        /* Large sibling payloads — not part of webshop_settings row shape used by classic headers */
        if (isset($o->website_setting)) {
            unset($o->website_setting);
        }
        return $this->apply_webshop_theme_defaults($o);
    }

    /**
     * Normalize theme slug and homepage layout name from DB / API.
     */
    protected function apply_webshop_theme_defaults(stdClass $o) {
        if (empty($o->home_page)) {
            $o->home_page = 'theme_9';
        } else {
            $hp = strtolower(trim((string) $o->home_page));
            $hp = preg_replace('/[^a-z0-9_\-]/', '', $hp);
            if ($hp !== '' && strpos($hp, 'theme_') !== 0) {
                $hp = 'theme_' . preg_replace('/^theme_?/', '', $hp);
            }
            $o->home_page = $hp !== '' ? $hp : 'theme_9';
        }
        if (empty($o->theme_color)) {
            $o->theme_color = 'orange';
        }
        if (empty($o->header_style)) {
            $o->header_style = 'header_theme_default';
        }
        if ($o->header_strip_style === null || $o->header_strip_style === '') {
            $o->header_strip_style = 1;
        }
        $o->webshop_theme = $this->resolve_storefront_theme_slug($o);
        if (empty($o->favicon)) {
            $o->favicon = 'favicon.ico';
        }
        if (!isset($o->overselling)) {
            $o->overselling = 0;
        }
        return $o;
    }

    protected function map_categories($resp) {
        $out = array('main' => array());
        if (!is_object($resp) || empty($resp->status) || strtoupper((string) $resp->status) !== 'SUCCESS') {
            return $out;
        }
        if (empty($resp->data) || !is_array($resp->data)) {
            return $out;
        }
        foreach ($resp->data as $row) {
            if (is_array($row)) {
                $row = (object) $row;
            }
            if (!isset($row->id)) {
                continue;
            }
            $row->categoryActive = true;
            $row->categoryInfoText = '';
            $out['main'][$row->id] = $row;
        }
        return $out;
    }

    protected function stub_sliders($data) {
        $name = $this->config->item('webshop_ai_name');
        return array(
            'SLIDE_1' => array(
                'background_image' => '',
                'slide_image' => '',
                'title' => $name ? $name : 'Webshop AI',
                'sub_title' => 'Connected to ElintOm POS API',
                'button_caption' => '',
                'button_link' => '',
                'bottom_caption' => '',
                'is_active' => 1,
                'title_color' => '#222222',
                'subtitle_color' => '#444444',
            ),
            'SLIDE_2' => array(
                'background_image' => '',
                'slide_image' => '',
                'title' => '',
                'sub_title' => '',
                'button_caption' => '',
                'button_link' => '',
                'bottom_caption' => '',
                'is_active' => 0,
                'title_color' => '#222222',
                'subtitle_color' => '#444444',
            ),
        );
    }

    protected function load_view($method, $data = array()) {
        $this->data = $data;
        $this->webshop_settings = isset($data['webshop_settings']) ? $data['webshop_settings'] : $this->default_webshop_settings();
        $this->load->view('webshop/' . $method, $data);
    }
}
