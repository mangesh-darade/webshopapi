<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Webshop_api_model  —  WebshopAPI storefront side.
 *
 * This model wraps ALL calls to ElintOm via Elintom_api_client.
 * It is the single source of truth for API-mode data in WebshopAPI.
 *
 * Usage in a controller:
 *   $this->load->model('webshop_api_model');
 *   $categories = $this->webshop_api_model->get_categories();
 *
 * The model reads the catalog_source config to decide whether to call
 * the API or fall through to the standard Webshop_model (DB mode).
 */
class Webshop_api_model extends CI_Model {

    /** @var Elintom_api_client */
    protected $api;

    /** Whether to use API or direct DB */
    protected $api_mode = false;

    /** Whether to fall back to DB if API fails */
    protected $fallback = true;

    /** @var array|null Cached tree from get_categories() within one request */
    protected $_categories_cache = null;

    public function __construct() {
        parent::__construct();

        $this->load->library('elintom_api_client');
        $this->api = $this->elintom_api_client;
        $this->load->library('elintom_api_response', null, 'elintom_response');

        $this->config->load('elintom_api', true);

        $source         = $this->config->item('elintom_catalog_source',          'elintom_api');
        $this->api_mode = ($source === 'api');
        $this->fallback = (bool) $this->config->item('elintom_catalog_fallback_database', 'elintom_api');
    }

    /** CodeIgniter DB loaded (shared MySQL with POS). DB-less storefront never has this. */
    protected function has_local_db() {
        return isset(get_instance()->db);
    }

    /** Use ElintOm HTTP API for catalogue when configured, or whenever local DB is unavailable. */
    protected function use_elintom_api_catalogue() {
        return $this->api_mode || !$this->has_local_db();
    }

    public function get_api_client() {
        return $this->api;
    }

    /** @return int seconds; 0 = caching disabled */
    protected function _elintom_http_cache_ttl($config_key, $default_seconds) {
        $this->config->load('elintom_api', true);
        $v = $this->config->item($config_key, 'elintom_api');
        if ($v === null || $v === '') {
            return $default_seconds;
        }
        $n = (int) $v;
        return $n >= 0 ? $n : $default_seconds;
    }

    protected function _store_settings_session_cache($res, $ttl_seconds) {
        if ($ttl_seconds <= 0 || !$res || !is_object($res)) {
            return;
        }
        $CI = get_instance();
        if (!isset($CI->session)) {
            return;
        }
        $blob = json_encode($res);
        if ($blob === false || $blob === '') {
            return;
        }
        $CI->session->set_userdata('elintom_cache_getsettings', array(
            'exp'  => time() + $ttl_seconds,
            'blob' => $blob,
        ));
    }

    protected function _store_categories_session_cache(array $tree, $ttl_seconds) {
        if ($ttl_seconds <= 0) {
            return;
        }
        $ser = serialize($tree);
        if (strlen($ser) > 400000) {
            return;
        }
        $CI = get_instance();
        if (!isset($CI->session)) {
            return;
        }
        $CI->session->set_userdata('elintom_cache_categories', array(
            'exp' => time() + $ttl_seconds,
            'ser' => $ser,
        ));
    }

    /* ================================================================
     * SETTINGS
     * ================================================================ */

    public function get_settings() {
        $ttl = $this->_elintom_http_cache_ttl('elintom_http_cache_settings_seconds', 45);
        if ($ttl > 0) {
            $CI = get_instance();
            if (isset($CI->session)) {
                $row = $CI->session->userdata('elintom_cache_getsettings');
                if (is_array($row) && isset($row['exp'], $row['blob']) && (int) $row['exp'] > time()) {
                    $cached = json_decode($row['blob']);
                    if ($cached !== null && is_object($cached)) {
                        return $cached;
                    }
                }
            }
        }
        $res = $this->api->get_settings();
        if ($ttl > 0 && $res && is_object($res) && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            $this->_store_settings_session_cache($res, $ttl);
        }
        return $res;
    }

    /**
     * E-shop enabled flag from ElintOm: MY_Controller loads get_settings() and
     * _normalize_settings_from_api() sets Settings->active_webshop (0|1).
     *
     * @return int 1 = storefront open, 0 = service_off / inactive
     */
    public function get_active_webshop_flag() {
        $CI = get_instance();
        return (int) $CI->Settings->active_webshop ? 1 : 0;
    }

    /**
     * Base URL for POS mdata (logos, banner, products, thumbs). Priority:
     *   1) config elintom_media_uploads_base_url when non-empty (elintom_api.local.php override / CDN)
     *   2) getsettings: media_uploads_base_url / mdata_url (MY_Controller::$api_media_uploads_base)
     *   3) elintom_api_base_url + folder: MY_Controller::$Customer_assets (subdomain / API) then config fallback
     *      Path shape: …/assets/mdata/{HTTP_HOST}/uploads/ when elintom_mdata_include_http_host_segment is TRUE
     *   4) local assets/mdata/[/host/]uploads/ or assets/mdata/{tenant}/uploads/ when host segment is off
     */
    public function get_media_uploads_base() {
        $CI = get_instance();
        $this->config->load('elintom_api', true);
        /* Non-empty config wins first (elintom_api.local.php / CDN override). */
        $media_base_url_from_config = $this->config->item('elintom_media_uploads_base_url', 'elintom_api');
        if (is_string($media_base_url_from_config) && trim($media_base_url_from_config) !== '') {
            return rtrim(str_replace('\\', '/', $media_base_url_from_config), '/') . '/';
        }
        if (isset($CI->api_media_uploads_base) && is_string($CI->api_media_uploads_base) && trim($CI->api_media_uploads_base) !== '') {
            $api_base = rtrim(str_replace('\\', '/', $CI->api_media_uploads_base), '/') . '/';
            // Keep API-provided base only when it already points to mdata uploads.
            // If API still returns legacy .../assets/uploads/, fall through to dynamic mdata host resolution below.
            if (preg_match('#/assets/mdata/[^/]+/uploads/?$#i', $api_base)) {
                return $api_base;
            }
        }
        $elintom_base_url               = $this->config->item('elintom_api_base_url', 'elintom_api');
        $use_browser_host_for_media_urls = (bool) $this->config->item('elintom_media_use_http_host', 'elintom_api');
        $customer_assets_folder_name    = '';
        if (isset($CI->Customer_assets) && (string) $CI->Customer_assets !== '') {
            $customer_assets_folder_name = trim((string) $CI->Customer_assets, '/');
        }
        if ($customer_assets_folder_name === '') {
            $tenant_folder_from_config = $this->config->item('elintom_customer_assets_folder', 'elintom_api');
            $customer_assets_folder_name = is_string($tenant_folder_from_config) ? trim((string) $tenant_folder_from_config, '/') : 'default';
        }
        if ($customer_assets_folder_name === '') {
            $customer_assets_folder_name = 'default';
        }
        $folder_path_after_mdata = elintom_mdata_uploads_tail_path($customer_assets_folder_name);
        if (is_string($elintom_base_url) && trim($elintom_base_url) !== '') {
            if ($use_browser_host_for_media_urls && isset($_SERVER['HTTP_HOST']) && (string) $_SERVER['HTTP_HOST'] !== '') {
                $parts_of_elintom_base_url     = parse_url($elintom_base_url);
                $path_from_elintom_url         = isset($parts_of_elintom_base_url['path'])
                    ? trim(str_replace('\\', '/', $parts_of_elintom_base_url['path']), '/')
                    : '';
                $slash_path_to_elintom_app     = ($path_from_elintom_url === '') ? '/' : '/' . $path_from_elintom_url . '/';
                $url_scheme_http_or_https      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
                    $url_scheme_http_or_https = 'https';
                }
                return $url_scheme_http_or_https . '://' . $_SERVER['HTTP_HOST'] . $slash_path_to_elintom_app
                    . 'assets/mdata/' . $folder_path_after_mdata;
            }
            $elintom_root_without_trailing_slash = rtrim(str_replace('\\', '/', $elintom_base_url), '/');
            return $elintom_root_without_trailing_slash . '/assets/mdata/' . $folder_path_after_mdata;
        }
        $customer_assets_folder_fallback = isset($CI->Customer_assets) ? (string) $CI->Customer_assets : 'default';
        $folder_path_after_mdata_fallback = elintom_mdata_uploads_tail_path($customer_assets_folder_fallback);
        return rtrim(base_url('assets/mdata/' . $folder_path_after_mdata_fallback), '/') . '/';
    }

    /**
     * Same shape as Webshop_model::get_webshop_settings() — sourced from API payload on MY_Controller.
     */
    public function get_webshop_settings() {
        $CI = get_instance();
        if (isset($CI->webshop_settings) && is_object($CI->webshop_settings)) {
            $ws = $CI->webshop_settings;
            if ((!isset($ws->home_page) || $ws->home_page === '') && isset($CI->Settings->home_page)) {
                $ws->home_page = $CI->Settings->home_page;
            }
            return $ws;
        }
        return (object) array(
            'home_page'       => 'theme_1',
            'theme_color'     => 'orange',
            'header_strip_style' => '1',
            'webshop_theme'   => 'default',
        );
    }

    /**
     * Same fields as Webshop_model::get_webshop_pos_settings() (pos_settings row subset).
     */
    public function get_webshop_pos_settings() {
        $CI = get_instance();
        $row = new stdClass();
        $merge = function ($obj) use (&$row) {
            if (!is_object($obj)) {
                return;
            }
            foreach (get_object_vars($obj) as $k => $v) {
                $row->$k = $v;
            }
        };
        if (isset($CI->pos_settings) && is_object($CI->pos_settings)) {
            $merge($CI->pos_settings);
        }
        if (isset($CI->Settings) && is_object($CI->Settings)) {
            foreach (array('default_eshop_warehouse', 'default_eshop_biller', 'eshop_overselling', 'eshop_active',
                'default_warehouse', 'default_biller') as $k) {
                if (!isset($row->$k) && isset($CI->Settings->$k)) {
                    $row->$k = $CI->Settings->$k;
                }
            }
        }
        if (!isset($row->eshop_active)) {
            $row->eshop_active = isset($CI->Settings->active_webshop) ? $CI->Settings->active_webshop : 1;
        }
        if (!isset($row->eshop_overselling)) {
            $row->eshop_overselling = '0';
        }
        if (!isset($row->default_eshop_warehouse)) {
            $row->default_eshop_warehouse = isset($CI->Settings->default_warehouse) ? $CI->Settings->default_warehouse : '0';
        }
        if (!isset($row->default_eshop_biller)) {
            $row->default_eshop_biller = isset($CI->Settings->default_biller) ? $CI->Settings->default_biller : '0';
        }
        return $row;
    }

    /** NW theme rows from ElintOm getsettings payload (same idea as Webshop_model::get_website_setting). */
    public function get_website_setting() {
        $CI = get_instance();
        if (isset($CI->api_website_setting)) {
            return $CI->api_website_setting;
        }
        return array();
    }

    /**
     * Static CMS row shape (Webshop_model reads sma_webshop_static_pages). DB-less: empty HTML until ElintOm exposes pages API.
     *
     * @param string $page_key e.g. aboutus
     * @return stdClass|null    Legacy DB mode may return null when no row exists.
     */
    public function about_usdata($page_key = 'aboutus') {
        $apiPage = $this->get_cms_page_content('/about-us');
     
        if ($apiPage !== null) {
            return $apiPage;
        }
        if ($this->has_local_db()) {
            return $this->_fallback_webshop_model()->about_usdata($page_key);
        }
        $o = new stdClass();
        $o->page_key = $page_key;
        $o->page_title = '';
        $o->page_text = '';
        $o->meta_tags = '';
        return $o;
    }

    /**
     * Dedicated homepage CMS payload.
     *
     * @return stdClass
     */
    public function home_page_data() {
        $apiPage = $this->get_cms_page_content('/');
        if ($apiPage !== null) {
            return $apiPage;
        }
        $o = new stdClass();
        $o->page_key = 'home';
        $o->page_title = '';
        $o->page_text = '';
        $o->meta_tags = '';
        return $o;
    }

    /**
     * Header/footer CMS static pages from ElintOm CMS tables.
     *
     * @return array<int,array{title:string,url:string,href:string}>
     */
    public function get_cms_nav_pages() {
        $res = $this->api->get_cms_pages();
        if (!$res || !isset($res->status) || strtoupper((string) $res->status) !== 'SUCCESS' || !isset($res->pages)) {
            return array();
        }
        $pages = is_array($res->pages) ? $res->pages : (array) $res->pages;
        $out = array();
        foreach ($pages as $row) {
            $a = is_object($row) ? (array) $row : (is_array($row) ? $row : array());
            $url   = isset($a['url']) ? trim((string) $a['url']) : '';
            $title = isset($a['page_name']) ? trim((string) $a['page_name']) : '';
            $status = isset($a['status']) ? strtolower(trim((string) $a['status'])) : 'published';

            if ($url === '' || $title === '' || $status !== 'published') {
                continue;
            }
            $href = $this->map_cms_url_to_webshop_href($url);
            if ($href === '') {
                continue;
            }
            $out[] = array(
                'title' => $title,
                'url'   => $url,
                'href'  => $href,
            );
        }
        return $out;
    }

    /**
     * Public accessor for any CMS URL path.
     *
     * @param string $url_path
     * @return stdClass|null
     */
    public function get_cms_page_by_url($url_path) {
        return $this->get_cms_page_content($url_path);
    }

    /**
     * Map CMS URLs to existing webshop routes.
     *
     * @param string $cms_url
     * @return string
     */
    protected function map_cms_url_to_webshop_href($cms_url) {
        $url = '/' . ltrim((string) $cms_url, '/');
        if ($url === '//') {
            $url = '/';
        }
        if ($url === '/') {
            return base_url('webshop');
        }
        $slug = ltrim($url, '/');
        $reserved = array(
            'index', 'login', 'register', 'cart', 'checkout', 'cms_page',
            'about_us', 'terms_and_conditions', 'privacy_policy', 'contact_us',
            'product_details', 'category_products', 'search_products'
        );
        if (in_array($slug, $reserved, true)) {
            return base_url('webshop/cms_page/' . $slug);
        }
        return base_url('webshop/' . $slug);
    }

    public function terms_conditions($page_key = 'terms_conditions') {
        $apiPage = $this->get_cms_page_content('/terms');
        if ($apiPage !== null) {
            return $apiPage;
        }
        if ($this->has_local_db()) {
            return $this->_fallback_webshop_model()->terms_conditions($page_key);
        }
        $o = new stdClass();
        $o->page_key = $page_key;
        $o->page_title = '';
        $o->page_text = '';
        $o->meta_tags = '';
        return $o;
    }

    public function privacy_policy($page_key = 'policy') {
        $apiPage = $this->get_cms_page_content('/privacy-policy');
        if ($apiPage !== null) {
            return $apiPage;
        }
        if ($this->has_local_db()) {
            return $this->_fallback_webshop_model()->privacy_policy($page_key);
        }
        $o = new stdClass();
        $o->page_key = $page_key;
        $o->page_title = '';
        $o->page_text = '';
        $o->meta_tags = '';
        return $o;
    }

    /**
     * Pull CMS page HTML from ElintOm API and shape it like static pages row.
     *
     * @param string $url_path CMS URL path (e.g. /about-us)
     * @return stdClass|null
     */
    protected function get_cms_page_content($url_path) {
        $res = $this->api->get_cms_page($url_path);
        if (!$res || !isset($res->status) || strtoupper((string) $res->status) !== 'SUCCESS') {
            $statusText = ($res && isset($res->status)) ? (string) $res->status : 'NULL';
            $msgText = ($res && isset($res->msg)) ? (string) $res->msg : '';
            log_message('error', 'Webshop_api_model:get_cms_page_content failed url=' . (string) $url_path . ' status=' . $statusText . ' msg=' . $msgText);
            return null;
        }
        $pick_first_string = function ($sources, $keys) {
            foreach ($sources as $src) {
                if (!is_array($src)) {
                    continue;
                }
                foreach ($keys as $k) {
                    if (isset($src[$k]) && trim((string) $src[$k]) !== '') {
                        return (string) $src[$k];
                    }
                }
            }
            return '';
        };
        $as_bool_flag = function ($value, $default = true) {
            if ($value === null || $value === '') {
                return (bool) $default;
            }
            return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
        };
        $extract_title_from_meta_html = function ($html) {
            $html = (string) $html;
            if ($html === '') {
                return '';
            }
            $m = array();
            if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $m) && isset($m[1])) {
                return trim(strip_tags((string) $m[1]));
            }
            return '';
        };
        $extract_title_from_meta_raw = function ($raw) {
            $scan = function ($node) use (&$scan) {
                if (is_object($node)) {
                    $node = (array) $node;
                }
                if (!is_array($node)) {
                    return '';
                }

                $nameCandidates = array('name', 'tag_name', 'key', 'label', 'title');
                $valueCandidates = array('value', 'tag_value', 'content', 'text');

                $nameVal = '';
                foreach ($nameCandidates as $nk) {
                    if (isset($node[$nk]) && trim((string) $node[$nk]) !== '') {
                        $nameVal = strtolower(trim((string) $node[$nk]));
                        break;
                    }
                }
                if (in_array($nameVal, array('title', 'seo_title', 'meta_title', 'og:title', 'twitter:title'), true)) {
                    foreach ($valueCandidates as $vk) {
                        if (isset($node[$vk]) && trim((string) $node[$vk]) !== '') {
                            return trim((string) $node[$vk]);
                        }
                    }
                    if (isset($node['title']) && trim((string) $node['title']) !== '' && $nameVal !== '') {
                        return trim((string) $node['title']);
                    }
                }

                if (isset($node['title']) && trim((string) $node['title']) !== '') {
                    return trim((string) $node['title']);
                }

                foreach ($node as $child) {
                    $found = $scan($child);
                    if ($found !== '') {
                        return $found;
                    }
                }
                return '';
            };
            return $scan($raw);
        };
        $resArr = is_object($res) ? (array) $res : (is_array($res) ? $res : array());
        $pageArr = array();
        if (isset($res->page) && is_object($res->page)) {
            $pageArr = (array) $res->page;
        } elseif (isset($res->page) && is_array($res->page)) {
            $pageArr = $res->page;
        }

        $o = new stdClass();
        $o->page_key = ltrim((string) $url_path, '/');
        $o->url = '/' . ltrim((string) $url_path, '/');
        if ($o->url === '//') {
            $o->url = '/';
        }
        $o->page_type = '';
        $o->status = '';
        $o->page_title = '';
        $o->page_text = '';
        $o->meta_tags = '';
        $o->sections = array();
        $o->meta_tags_raw = array();
        $o->header_html = '';
        $o->footer_html = '';
        $o->banner_html = '';
        $o->logo_html = '';
        $o->show_header = true;
        $o->show_footer = true;
        $o->page_banner_image_url = '';
        $o->page_logo_image_url = '';
        $o->page_title = $pick_first_string(array($pageArr, $resArr), array('page_name', 'page_title', 'title', 'name'));
        $resolvedUrl = $pick_first_string(array($pageArr, $resArr), array('url', 'page_url', 'slug'));
        if ($resolvedUrl !== '') {
            $o->url = $resolvedUrl;
        }
        $o->page_type = strtolower(trim($pick_first_string(array($pageArr, $resArr), array('page_type', 'type'))));
        $o->status = strtolower(trim($pick_first_string(array($pageArr, $resArr), array('status', 'page_status'))));
        if (isset($res->sections) && is_array($res->sections)) {
            $o->sections = $res->sections;
        } elseif (isset($res->sections) && is_object($res->sections)) {
            $o->sections = (array) $res->sections;
        }
        // Some API versions wrap sections under page.sections.
        if (empty($o->sections) && isset($res->page) && is_object($res->page) && isset($res->page->sections)) {
            $o->sections = is_array($res->page->sections) ? $res->page->sections : (array) $res->page->sections;
        } elseif (empty($o->sections) && isset($res->page) && is_array($res->page) && isset($res->page['sections'])) {
            $o->sections = is_array($res->page['sections']) ? $res->page['sections'] : (array) $res->page['sections'];
        }
        if (isset($res->meta_tags_raw) && is_array($res->meta_tags_raw)) {
            $o->meta_tags_raw = $res->meta_tags_raw;
        }
        $o->meta_tags = $pick_first_string(array($resArr, $pageArr), array('meta_tags_html', 'meta_tags', 'meta'));
        // Prefer SEO title from meta payload when provided.
        $seoTitle = $extract_title_from_meta_html($o->meta_tags);
        if ($seoTitle === '' && !empty($o->meta_tags_raw)) {
            $seoTitle = $extract_title_from_meta_raw($o->meta_tags_raw);
        }
        if ($seoTitle === '') {
            $seoTitle = $extract_title_from_meta_raw($resArr);
        }
        if ($seoTitle !== '') {
            $o->page_title = $seoTitle;
        }
        $o->page_text = $pick_first_string(
            array($resArr, $pageArr),
            array('content_html', 'body_html', 'page_text', 'content', 'description', 'page_description')
        );
        // Direct property fallback (some JSON decoders keep nested shapes where array cast omits keys).
        if (trim((string) $o->page_text) === '' && is_object($res) && isset($res->content_html) && trim((string) $res->content_html) !== '') {
            $o->page_text = trim((string) $res->content_html);
        }
        $o->header_html = $pick_first_string(array($resArr, $pageArr), array('header_html', 'header', 'header_content'));
        $o->footer_html = $pick_first_string(array($resArr, $pageArr), array('footer_html', 'footer', 'footer_content'));
        $o->banner_html = $pick_first_string(array($resArr, $pageArr), array('banner_html', 'banner', 'banner_content'));
        $o->logo_html = $pick_first_string(array($resArr, $pageArr), array('logo_html', 'logo', 'logo_content'));
        $showHeaderRaw = $pick_first_string(array($resArr, $pageArr), array('show_header', 'header_enabled', 'header'));
        $showFooterRaw = $pick_first_string(array($resArr, $pageArr), array('show_footer', 'footer_enabled', 'footer'));
        $o->show_header = $as_bool_flag($showHeaderRaw, true);
        $o->show_footer = $as_bool_flag($showFooterRaw, true);
        $o->page_banner_image_url = $pick_first_string(
            array($resArr, $pageArr),
            array('page_banner_image_url', 'banner_image_url', 'banner_image')
        );
        $o->page_logo_image_url = $pick_first_string(
            array($resArr, $pageArr),
            array('page_logo_image_url', 'logo_image_url', 'logo_image')
        );
        // Fallback: build static page body from mapped html_block sections.
        if ($o->page_text === '' && !empty($o->sections) && is_array($o->sections)) {
            $chunks = array();
            foreach ($o->sections as $section) {
                $sec = is_object($section) ? (array) $section : (is_array($section) ? $section : array());
                $type = isset($sec['section_type']) ? strtolower(trim((string) $sec['section_type'])) : '';
                if ($type === '' && isset($sec['section_name'])) {
                    $type = strtolower(trim((string) $sec['section_name']));
                }
                if ($type !== 'html_block') {
                    // Some old payloads store raw HTML even for non-html_block typed sections.
                    $rawDirect = $pick_first_string(array($sec), array('html', 'content', 'section_html', 'section_contain'));
                    if ($rawDirect !== '' && strpos(trim($rawDirect), '<') !== false) {
                        $chunks[] = $rawDirect;
                    }
                    continue;
                }
                $cfg = array();
                if (isset($sec['config_json']) && is_array($sec['config_json'])) {
                    $cfg = $sec['config_json'];
                } elseif (isset($sec['config_json']) && is_object($sec['config_json'])) {
                    $cfg = (array) $sec['config_json'];
                } elseif (isset($sec['config_json']) && is_string($sec['config_json']) && trim($sec['config_json']) !== '') {
                    $decoded = json_decode($sec['config_json'], true);
                    if (is_array($decoded)) {
                        $cfg = $decoded;
                    }
                }
                if (isset($cfg['content']) && trim((string) $cfg['content']) !== '') {
                    $chunks[] = (string) $cfg['content'];
                }
                if (isset($cfg['html']) && trim((string) $cfg['html']) !== '') {
                    $chunks[] = (string) $cfg['html'];
                }
                if (empty($cfg) && isset($sec['section_contain']) && is_string($sec['section_contain']) && trim($sec['section_contain']) !== '') {
                    $chunks[] = (string) $sec['section_contain'];
                }
            }
            if (!empty($chunks)) {
                $o->page_text = implode("\n", $chunks);
            }
        }
        return $o;
    }

    /**
     * Delegate unknown methods to legacy Webshop_model when DB is available; otherwise safe stubs for DB-less boot.
     */
    public function __call($name, $arguments) {
        $CI = get_instance();
        if (isset($CI->db)) {
            if (!isset($CI->webshop_model_db)) {
                $CI->load->model('webshop_model', 'webshop_model_db');
            }
            return call_user_func_array(array($CI->webshop_model_db, $name), $arguments);
        }
        return $this->_db_less_stub($name, $arguments);
    }

    protected function _db_less_stub($name, array $arguments) {
        switch ($name) {
            case 'get_category_brands':
            case 'get_all_brands':
                return array();
            case 'get_wishlist_count':
                return 0;
            case 'get_entity_tag_map':
            case 'get_entity_tag_rows':
                return array();
            case 'getCustomPages':
                return array();
            case 'restaurantWorking':
                return false;
            case 'get_recent_viewed_product':
                return array();
            case 'set_recent_viewed_product':
                return false;
            default:
                log_message('error', 'Webshop_api_model: unimplemented DB-less method ' . $name);
                return null;
        }
    }

    /**
     * Session cart enrichment — cart.php expects $cart_data['products'][product_id] with name, image.
     * DB-less stub used to return [] (missing index). Uses API product list + legacy fill for gaps.
     *
     * @return array|false
     */
    public function get_cart_data() {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || $_SESSION['cart'] === array()) {
            return false;
        }
        $data = array();
        if (isset($_SESSION['cart_coupon'])) {
            $data['coupon']['code'] = $_SESSION['cart_coupon']['code'];
            $data['coupon']['discount'] = $_SESSION['cart_coupon']['discount'];
        }
        $product_ids = array();
        foreach ($_SESSION['cart'] as $item) {
            if (!empty($item['product_id'])) {
                $product_ids[] = (int) $item['product_id'];
            }
        }
        $product_ids = array_values(array_unique($product_ids));
        if ($product_ids === array()) {
            return false;
        }
        $cart_sig = md5(json_encode($_SESSION['cart']) . (isset($_SESSION['cart_coupon']) ? json_encode($_SESSION['cart_coupon']) : ''));
        $cart_ttl = $this->_elintom_http_cache_ttl('elintom_http_cache_cart_products_seconds', 60);
        if ($cart_ttl > 0) {
            $CI = get_instance();
            if (isset($CI->session)) {
                $row = $CI->session->userdata('elintom_cache_cart_data');
                if (is_array($row) && isset($row['sig'], $row['exp'], $row['ser'])
                    && (string) $row['sig'] === $cart_sig && (int) $row['exp'] > time()) {
                    $cached = @unserialize($row['ser']);
                    if (is_array($cached) && isset($cached['products']) && is_array($cached['products'])) {
                        return $cached;
                    }
                }
            }
        }
        $products_map = array();
        $list = $this->get_products_list('products', $product_ids, false, 0, 1);
        if (is_array($list) && !empty($list['items']) && is_array($list['items'])) {
            foreach ($list['items'] as $row) {
                $a = is_array($row) ? $row : (array) $row;
                $pid = isset($a['id']) ? (int) $a['id'] : (isset($a['product_id']) ? (int) $a['product_id'] : 0);
                if ($pid > 0) {
                    $products_map[$pid] = $this->elintom_response->normalize_product_detail_item($a);
                }
            }
        }
        $missing = array();
        foreach ($product_ids as $pid) {
            if (!isset($products_map[$pid])) {
                $missing[] = $pid;
            }
        }
        if (!empty($missing)) {
            $extra = $this->_legacy_fetch_products_by_numeric_ids($missing);
            foreach ($extra as $pid => $row) {
                $products_map[(int) $pid] = $row;
            }
        }
        $data['products'] = $products_map;
        if ($cart_ttl > 0) {
            $CI = get_instance();
            if (isset($CI->session)) {
                $CI->session->set_userdata('elintom_cache_cart_data', array(
                    'sig' => $cart_sig,
                    'exp' => time() + $cart_ttl,
                    'ser' => serialize($data),
                ));
            }
        }
        return $data;
    }

    /**
     * Resolve products when get_products_list misses IDs — one lightweight API call per id (not bulk get_all_products).
     *
     * @param int[] $want_ids
     * @return array<int,array> id => normalized row
     */
    protected function _legacy_fetch_products_by_numeric_ids(array $want_ids) {
        $out = array();
        foreach ($want_ids as $id) {
            $pid = (int) $id;
            if ($pid < 1) {
                continue;
            }
            $res = $this->api->get_product_by_hash(md5((string) $pid));
            if (!$res || !$this->elintom_response->api_status_ok($res)) {
                continue;
            }
            $bundle = $this->elintom_response->product_detail_bundle_from_api_response($res);
            if ($bundle === null || !isset($bundle['item'])) {
                continue;
            }
            $item = $bundle['item'];
            $a = is_array($item) ? $item : (array) $item;
            $out[$pid] = $this->elintom_response->normalize_product_detail_item($a);
        }
        return $out;
    }

    /** Load legacy model without overwriting controller alias `$this->webshop_model` → webshop_api_model. */
    protected function _fallback_webshop_model() {
        if (!$this->has_local_db()) {
            log_message('error', 'Webshop_api_model: DB fallback requested but no database is loaded');
            show_error('Catalogue requires ElintOm API or a configured database.', 503);
        }
        $CI = get_instance();
        if (!isset($CI->webshop_model_db)) {
            $CI->load->model('webshop_model', 'webshop_model_db');
        }
        return $CI->webshop_model_db;
    }

    /**
     * Resolve numeric category id when controller passes md5(category_id) as byid.
     *
     * @param string $hash md5 hex
     * @return int|null
     */
    protected function _resolve_category_id_from_md5($hash) {
        $want = strtolower(trim((string) $hash));
        if (strlen($want) !== 32) {
            return null;
        }
        $tree = $this->get_categories();
        if (!is_array($tree)) {
            return null;
        }
        $match = function ($row, $cidKey) use ($want) {
            $id = is_object($row) ? (isset($row->id) ? $row->id : $cidKey) : (isset($row['id']) ? $row['id'] : $cidKey);
            $id = (string) $id;
            return (md5($id) === $want) ? (int) $id : null;
        };
        if (isset($tree['main']) && is_array($tree['main'])) {
            foreach ($tree['main'] as $cid => $row) {
                $m = $match($row, $cid);
                if ($m !== null) {
                    return $m;
                }
            }
        }
        foreach ($tree as $pk => $bucket) {
            if ($pk === 'main' || !is_array($bucket)) {
                continue;
            }
            foreach ($bucket as $cid => $row) {
                $m = $match($row, $cid);
                if ($m !== null) {
                    return $m;
                }
            }
        }
        return null;
    }

    /**
     * Legacy api3/eshop getallproducts for category pages when webshop_api/getproductslist fails or is empty.
     */
    protected function _get_products_list_legacy_category($byid, $hash, $limit, $page) {
        if ($hash) {
            $cid = $this->_resolve_category_id_from_md5($byid);
        } else {
            $cid = (int) $byid;
        }
        if ($cid === null || $cid < 1) {
            return null;
        }
        $row = $this->_find_category_row($cid);
        $parent_id = ($row && isset($row->parent_id)) ? (int) $row->parent_id : 0;

        $params = array(
            'keyword'         => '',
            'limit'           => $limit ? (string) $limit : '',
            'offset'          => ($limit && $page) ? (string) (($page - 1) * $limit) : '',
        );
        if ($parent_id > 0) {
            $params['category_id'] = (string) $parent_id;
            $params['subcategory_id'] = (string) $cid;
        } else {
            $params['category_id'] = (string) $cid;
            $params['subcategory_id'] = '';
        }

        $res = $this->api->get_all_products($params);
        if (!$res || !$this->elintom_response->api_status_ok($res)) {
            return null;
        }
        $raw = $this->elintom_response->unwrap_legacy_products_payload($res);
        if ($raw === null) {
            return null;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        $normalized = $this->elintom_response->normalize_products_list_payload($raw, $page);
        if (isset($res->count) && (int) $res->count > 0 && (!isset($normalized['items_total']) || (int) $normalized['items_total'] === 0)) {
            $normalized['items_total'] = (int) $res->count;
        }
        return $normalized;
    }

    /**
     * @param string|int $categoryId
     * @return object|null
     */
    protected function _find_category_row($categoryId) {
        $tree = $this->get_categories();
        if (!is_array($tree) || empty($tree)) {
            return null;
        }
        $want = (string) $categoryId;
        if (isset($tree['main']) && is_array($tree['main'])) {
            foreach ($tree['main'] as $cid => $row) {
                $rid = isset($row->id) ? (string) $row->id : (string) $cid;
                if ($rid === $want) {
                    return is_object($row) ? $row : (object) $row;
                }
            }
        }
        foreach ($tree as $key => $bucket) {
            if ($key === 'main' || !is_array($bucket)) {
                continue;
            }
            foreach ($bucket as $cid => $row) {
                $rid = isset($row->id) ? (string) $row->id : (string) $cid;
                if ($rid === $want) {
                    return is_object($row) ? $row : (object) $row;
                }
            }
        }
        return null;
    }

    public function get_next_reference() {
        $res = $this->api->get_next_reference();
        return ($res && isset($res->reference)) ? $res->reference : null;
    }

    /* ================================================================
     * CATALOGUE
     * ================================================================ */

    /**
     * Returns categories structured as ElintOm Webshop_model::get_categories().
     * Uses ElintOm API when catalog_source=api OR storefront has no local DB.
     */
    public function get_categories() {
        if ($this->_categories_cache !== null) {
            return $this->_categories_cache;
        }
        $cat_ttl = $this->_elintom_http_cache_ttl('elintom_http_cache_categories_seconds', 120);
        if ($cat_ttl > 0 && $this->use_elintom_api_catalogue()) {
            $CI = get_instance();
            if (isset($CI->session)) {
                $row = $CI->session->userdata('elintom_cache_categories');
                if (is_array($row) && isset($row['exp'], $row['ser']) && (int) $row['exp'] > time()) {
                    $tree = @unserialize($row['ser']);
                    if (is_array($tree)) {
                        $this->_categories_cache = $tree;
                        return $tree;
                    }
                }
            }
        }
        if ($this->use_elintom_api_catalogue()) {
            $res = $this->api->get_categories();

            if ($res && $this->elintom_response->api_status_ok($res)) {
                $raw = $this->elintom_response->unwrap_categories_from_api_response($res);
                $tree = $this->elintom_response->normalize_categories_payload($raw);
                if ($this->elintom_response->categories_main_count($tree) > 0) {
                    $this->_categories_cache = $tree;
                    $this->_store_categories_session_cache($tree, $cat_ttl);
                    return $this->_categories_cache;
                }
            }

            /* Primary webshop_api/getcategories may 500 or return empty — api3/eshop getallcategories uses `category` list */
            $legacy = $this->api->get_all_categories('');

            if ($legacy && $this->elintom_response->api_status_ok($legacy)) {
                $rawL = $this->elintom_response->unwrap_categories_from_api_response($legacy);
                if ($rawL === null && is_object($legacy) && isset($legacy->allcategories)) {
                    $rawL = $legacy->allcategories;
                }
                $treeL = $this->elintom_response->normalize_categories_payload($rawL !== null ? $rawL : $legacy);
                if ($this->elintom_response->categories_main_count($treeL) > 0) {
                    $this->_categories_cache = $treeL;
                    $this->_store_categories_session_cache($treeL, $cat_ttl);
                    return $this->_categories_cache;
                }
            }

            if (!$this->has_local_db()) {
                $this->_categories_cache = array('main' => array());
                $this->_store_categories_session_cache($this->_categories_cache, $cat_ttl);
                return $this->_categories_cache;
            }
            if (!$this->fallback) {
                return false;
            }
        }
        $fb = $this->_fallback_webshop_model()->get_categories();
        $this->_categories_cache = ($fb !== false && is_array($fb)) ? $fb : array('main' => array());
        if ($cat_ttl > 0 && $this->use_elintom_api_catalogue() && is_array($this->_categories_cache)) {
            $this->_store_categories_session_cache($this->_categories_cache, $cat_ttl);
        }
        return $this->_categories_cache;
    }

    public function get_sliders() {
        if ($this->use_elintom_api_catalogue()) {
            $res = $this->api->get_sliders();
            if ($res && $this->elintom_response->api_status_ok($res) && isset($res->sliders)) {
                return (array) $res->sliders;
            }
            if (!$this->has_local_db()) {
                return [];
            }
            if (!$this->fallback) {
                return false;
            }
        }
        return $this->_fallback_webshop_model()->get_sliders();
    }

    /**
     * @param string $by     category|brand|products
     * @param mixed  $byid   id or array of ids
     * @param bool   $hash   use MD5 hash lookup
     * @param int    $limit
     * @param int    $page
     */
    public function get_products_list($by = null, $byid = null, $hash = false, $limit = 0, $page = 1) {
        if ($this->use_elintom_api_catalogue()) {
            $res = $this->api->get_products_list(array(
                'by'       => $by,
                'byid'     => $byid,
                'use_hash' => $hash ? 1 : 0,
                'limit'    => $limit,
                'page'     => $page,
            ));

            if ($res && $this->elintom_response->api_status_ok($res)) {
                $payload = $this->elintom_response->unwrap_elintom_products_response($res);
                if ($payload !== null) {
                    $normalized = $this->elintom_response->normalize_products_list_payload($payload, $page);
                    if ($this->elintom_response->products_list_item_count($normalized) > 0) {
                        return $normalized;
                    }
                }
            }

            /* Same pattern as categories: webshop_api may 500 — api3 getallproducts fills category grids */
            if ($by === 'category' && $byid !== null && $byid !== '') {
                $legacyList = $this->_get_products_list_legacy_category($byid, $hash, $limit, $page);
                if ($legacyList !== null && $this->elintom_response->products_list_item_count($legacyList) > 0) {
                    return $legacyList;
                }
            }

            if (!$this->has_local_db()) {
                return $this->elintom_response->normalize_products_list_payload(null, $page);
            }
            if (!$this->fallback) {
                return false;
            }
        }
        return $this->_fallback_webshop_model()->get_products_list($by, $byid, $hash, $limit, $page);
    }

    /**
     * Same contract as Webshop_model — used by category_products / product_details.
     */
    public function checkIsCategoryActiveForWebshop($categoryId) {
        if (!$this->use_elintom_api_catalogue()) {
            return $this->_fallback_webshop_model()->checkIsCategoryActiveForWebshop($categoryId);
        }
        $row = $this->_find_category_row($categoryId);
        if ($row === null) {
            return false;
        }
        if (isset($row->in_eshop)) {
            $ie = $row->in_eshop;
            if ($ie === '0' || $ie === 0 || $ie === false) {
                return false;
            }
        }
        if (isset($row->is_active)) {
            $ia = $row->is_active;
            if ($ia === '0' || $ia === 0 || $ia === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array Webshop_model shape: [0] => "true"|"false", [1] => info text or array
     */
    public function categoryActive($categoryId) {
        if (!$this->use_elintom_api_catalogue()) {
            return $this->_fallback_webshop_model()->categoryActive($categoryId);
        }
        return array('true', array('All*'));
    }

    /**
     * @return array
     */
    public function productAvailable($productId) {
        if (!$this->use_elintom_api_catalogue()) {
            return $this->_fallback_webshop_model()->productAvailable($productId);
        }
        return array('true', '');
    }

    /** DB-backed specials; not exposed via API catalogue — safe empty for API-only storefronts */
    public function getTodaysSpecialItemsForGivenCategoryDB($categoryId) {
        if (!$this->use_elintom_api_catalogue()) {
            return $this->_fallback_webshop_model()->getTodaysSpecialItemsForGivenCategoryDB($categoryId);
        }
        return false;
    }

    /**
     * Used by Webshop::product_details — guarantees assoc keys views expect (handles object rows).
     *
     * @param mixed $item
     * @return array
     */
    public function normalize_product_detail_item_for_view($item) {
        $a = is_array($item) ? $item : (is_object($item) ? (array) $item : array());
        return $this->elintom_response->normalize_product_detail_item($a);
    }

    public function get_entity_tag_rows($entity_code, $entity_id) {
        $entity_code = strtolower(trim((string) $entity_code));
        $entity_id = (int) $entity_id;
        if ($entity_code === '' || $entity_id <= 0) {
            return array();
        }

        if ($this->use_elintom_api_catalogue()) {
            $res = $this->api->get_entity_tags($entity_code, $entity_id);
            if ($res && $this->elintom_response->api_status_ok($res)) {
                $rows = array();
                if (isset($res->rows) && is_array($res->rows)) {
                    $rows = $res->rows;
                } elseif (isset($res->rows) && is_object($res->rows)) {
                    $rows = (array) $res->rows;
                }
                $out = array();
                foreach ($rows as $row) {
                    $a = is_array($row) ? $row : (array) $row;
                    $property = isset($a['property_name']) ? trim((string) $a['property_name']) : '';
                    $value = isset($a['value']) ? trim((string) $a['value']) : '';
                    if ($property === '' || $value === '') {
                        continue;
                    }
                    $out[] = array(
                        'tag_id' => isset($a['tag_id']) ? (int) $a['tag_id'] : 0,
                        'property_name' => $property,
                        'value' => $value,
                        'tag_name' => isset($a['tag_name']) ? (string) $a['tag_name'] : $property,
                        'category' => isset($a['category']) && trim((string) $a['category']) !== '' ? (string) $a['category'] : 'General',
                    );
                }
                return $out;
            }
            if (!$this->has_local_db()) {
                return array();
            }
        }

        if ($this->has_local_db()) {
            return $this->_fallback_entity_tag_rows_from_db($entity_code, $entity_id);
        }
        return array();
    }

    public function get_entity_tag_map($entity_code, $entity_id) {
        $rows = $this->get_entity_tag_rows($entity_code, $entity_id);
        $mapped = array();
        foreach ($rows as $row) {
            $property_name = isset($row['property_name']) ? trim((string) $row['property_name']) : '';
            $value = isset($row['value']) ? trim((string) $row['value']) : '';
            if ($property_name === '' || $value === '') {
                continue;
            }
            $mapped[$property_name] = $value;
        }
        return $mapped;
    }

    protected function _fallback_entity_tag_rows_from_db($entity_code, $entity_id) {
        $wm = $this->_fallback_webshop_model();
        if (!isset($wm->db)) {
            return array();
        }

        $entities_master_table = $wm->db->table_exists('sma_entities_master') ? 'sma_entities_master' : 'entities_master';
        $entity_tag_map_table = $wm->db->table_exists('sma_entity_tag_mapping') ? 'sma_entity_tag_mapping' : 'entity_tag_mapping';
        $tags_master_table = $wm->db->table_exists('sma_tags_master') ? 'sma_tags_master' : 'tags_master';
        if (
            !$wm->db->table_exists($entities_master_table) ||
            !$wm->db->table_exists($entity_tag_map_table) ||
            !$wm->db->table_exists($tags_master_table)
        ) {
            return array();
        }

        $entity_master = $wm->db
            ->select('id')
            ->from($entities_master_table)
            ->where('entity_code', $entity_code)
            ->where('is_active', 1)
            ->get()
            ->row_array();
        if (empty($entity_master) || empty($entity_master['id'])) {
            return array();
        }

        $rows = $wm->db
            ->select('etm.tag_id, etm.property_name, etm.value, tm.tag_name, tm.category')
            ->from($entity_tag_map_table . ' etm')
            ->join($tags_master_table . ' tm', 'tm.id = etm.tag_id', 'left')
            ->where('etm.entity_master_id', (int) $entity_master['id'])
            ->where('etm.entity_id', (int) $entity_id)
            ->order_by('tm.category', 'ASC')
            ->order_by('tm.tag_name', 'ASC')
            ->get()
            ->result_array();

        return is_array($rows) ? $rows : array();
    }

    /**
     * When getproductbyhash fails or returns an unexpected shape, scan legacy getallproducts for md5(id).
     */
    protected function _get_product_by_hash_legacy_scan($hash) {
        $want = strtolower(trim((string) $hash));
        if (strlen($want) !== 32) {
            return null;
        }
        $res = $this->api->get_all_products(array(
            'keyword'        => '',
            'category_id'    => '',
            'subcategory_id' => '',
            'offset'         => '0',
            'limit'          => '3000',
        ));
        if (!$res || !$this->elintom_response->api_status_ok($res)) {
            return null;
        }
        $raw = $this->elintom_response->unwrap_legacy_products_payload($res);
        if ($raw === null) {
            return null;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        $payload = is_array($raw) ? $raw : array();
        $normalized = $this->elintom_response->normalize_products_list_payload($payload, 1);
        $items = isset($normalized['items']) && is_array($normalized['items']) ? $normalized['items'] : array();
        if (empty($items)) {
            $items = $this->elintom_response->coerce_associative_product_map_to_rows($payload);
        }
        foreach ($items as $row) {
            $a = is_array($row) ? $row : (array) $row;
            if (!isset($a['id']) && isset($a['product_id'])) {
                $a['id'] = $a['product_id'];
            }
            if (!isset($a['id'])) {
                continue;
            }
            if (md5((string) $a['id']) !== $want) {
                continue;
            }
            $item = $this->elintom_response->normalize_product_detail_item($a);
            $images = array();
            if (!empty($item['image'])) {
                $images[] = array('photo' => $item['image']);
            }
            return array(
                'item'     => $item,
                'variants' => array(),
                'images'   => $images,
                'stocks'   => array(),
            );
        }
        return null;
    }

    public function get_product_by_hash($hash) {
        if ($this->use_elintom_api_catalogue()) {
            $res = $this->api->get_product_by_hash($hash);
            if ($res && $this->elintom_response->api_status_ok($res)) {
                $bundle = $this->elintom_response->product_detail_bundle_from_api_response($res);
                if ($bundle !== null) {
                    return $bundle;
                }
            }
            $legacy = $this->_get_product_by_hash_legacy_scan($hash);
            if ($legacy !== null) {
                return $legacy;
            }
            if (!$this->has_local_db()) {
                return false;
            }
            if (!$this->fallback) {
                return false;
            }
        }
        return $this->_fallback_webshop_model()->get_product_by_hash($hash);
    }

    public function search_products($keyword, $category_id = null) {
        if ($this->use_elintom_api_catalogue()) {
            $res = $this->api->search_products($keyword, $category_id);
            if ($res && $this->elintom_response->api_status_ok($res)) {
                return ['items' => (array) $res->items, 'other' => (array) $res->other];
            }
            if (!$this->has_local_db()) {
                return ['items' => [], 'other' => []];
            }
            if (!$this->fallback) {
                return false;
            }
        }
        $wm = $this->_fallback_webshop_model();
        $items = $wm->search_category_products($keyword, $category_id);
        $other = $wm->search_other_products($keyword, $category_id);
        return ['items' => $items ?: [], 'other' => $other ?: []];
    }

    /**
     * Same rows as search_products items — Webshop_model returns arrays of product fields.
     */
    public function search_category_products($keyword, $category = null) {
        if ($this->use_elintom_api_catalogue()) {
            $sp = $this->search_products($keyword, $category);
            if (!is_array($sp) || !isset($sp['items'])) {
                return array();
            }
            return $this->elintom_response->rows_to_assoc_arrays($sp['items']);
        }
        return $this->_fallback_webshop_model()->search_category_products($keyword, $category);
    }

    public function search_other_products($keyword, $category = null) {
        if ($this->use_elintom_api_catalogue()) {
            $sp = $this->search_products($keyword, $category);
            if (!is_array($sp) || !isset($sp['other'])) {
                return array();
            }
            return $this->elintom_response->rows_to_assoc_arrays($sp['other']);
        }
        return $this->_fallback_webshop_model()->search_other_products($keyword, $category);
    }

    /**
     * Homepage section helper — same signature as Webshop_model::get_category_tab_products().
     */
    public function get_category_tab_products($category_tabs) {
        if (!$this->use_elintom_api_catalogue()) {
            return $this->_fallback_webshop_model()->get_category_tab_products($category_tabs);
        }
        if (!is_array($category_tabs)) {
            return false;
        }
        $categoryProducts = array();
        foreach ($category_tabs as $category_id => $subcategoryArr) {
            $categoryProducts[$category_id] = $this->get_products_list('category', $category_id, false, 0, 1);
        }
        return $categoryProducts;
    }

    public function get_tab_products_by_id($tab_products) {
        if (!$this->use_elintom_api_catalogue()) {
            return $this->_fallback_webshop_model()->get_tab_products_by_id($tab_products);
        }
        if (!is_array($tab_products)) {
            return false;
        }
        $categoryTabProducts = array();
        foreach ($tab_products as $category_id => $productsArr) {
            if (!is_array($productsArr)) {
                continue;
            }
            $products = $this->get_products_list('products', $productsArr, false, 0, 1);
            if (!empty($products['items']) && is_array($products['items'])) {
                foreach ($products['items'] as $product) {
                    $categoryTabProducts[$category_id][] = is_array($product) ? $product : (array) $product;
                }
            }
        }
        return $categoryTabProducts;
    }

    public function get_category_product_variants($categories = null) {
        if ($this->use_elintom_api_catalogue()) {
            return array();
        }
        return $this->_fallback_webshop_model()->get_category_product_variants($categories);
    }

    /** DB-backed homepage sections — not on ElintOm catalogue API; themes that need sections require DB or API extension */
    public function get_theme_sections($theme = 'theme_1') {
        if ($this->use_elintom_api_catalogue() && !$this->has_local_db()) {
            return false;
        }
        if (!$this->has_local_db()) {
            return false;
        }
        return $this->_fallback_webshop_model()->get_theme_sections($theme);
    }

    public function get_features() {
        if ($this->use_elintom_api_catalogue() && !$this->has_local_db()) {
            return false;
        }
        if (!$this->has_local_db()) {
            return false;
        }
        return $this->_fallback_webshop_model()->get_features();
    }

    /** Preview theme via ?theme= — persisted only when local sma_webshop_settings exists */
    public function setTheme($theme) {
        if (!$this->has_local_db()) {
            return false;
        }
        return $this->_fallback_webshop_model()->setTheme($theme);
    }

    /**
     * Payment toggles: merge API-loaded pos_settings (MY_Controller) with defaults like Webshop_model::get_payment_gatways().
     */
    public function get_payment_gatways() {
        $gatewayDefaults = array(
            'paypal_pro' => 0,
            'stripe' => 0,
            'authorize' => 0,
            'instamojo' => 0,
            'ccavenue' => 0,
            'paytm' => 0,
            'UPI_QRCODE' => 0,
            'payswiff' => 0,
            'payumoney' => 0,
            'paynear' => 0,
            'razorpay' => 0,
        );
        $CI = get_instance();
        if (isset($CI->pos_settings) && is_object($CI->pos_settings)) {
            $row = (array) $CI->pos_settings;
            if (array_intersect_key($gatewayDefaults, $row)) {
                return (object) array_merge($gatewayDefaults, array_intersect_key($row, $gatewayDefaults));
            }
        }
        if ($this->has_local_db()) {
            return $this->_fallback_webshop_model()->get_payment_gatways();
        }
        return (object) $gatewayDefaults;
    }

    public function getAreaCharges() {
        if (!$this->has_local_db()) {
            return array();
        }
        return $this->_fallback_webshop_model()->getAreaCharges();
    }

    /* ================================================================
     * GEO
     * ================================================================ */

    /**
     * Same as Webshop_model::get_state() — array keyed by state id for dropdowns.
     */
    public function get_state() {
        return $this->elintom_response->normalize_states_map($this->load_state_rows_from_api_or_db());
    }

    /**
     * Same as Webshop_model::getCountry() — array of country row objects.
     */
    public function getCountry() {
        return $this->elintom_response->countries_to_objects($this->load_country_rows_from_api_or_db());
    }

    /** Load state rows: ElintOm API first, then DB model if configured. */
    protected function load_state_rows_from_api_or_db() {
        $response = $this->elintom_response->coerce_geo_api_root($this->api->get_states());
        if ($response && $this->elintom_response->api_geo_payload_usable($response, 'states')) {
            $list = $this->elintom_response->unwrap_geo_list_from_api_response($response, 'states');
            if ($list === null) {
                return array();
            }
            if ($list instanceof Traversable) {
                $list = iterator_to_array($list);
            } elseif (is_object($list)) {
                $list = (array) $list;
            }
            return is_array($list) ? $list : array();
        }
        if (!$this->has_local_db()) {
            return array();
        }
        return $this->_fallback_webshop_model()->get_state();
    }

    /** Load country rows: ElintOm API first, then DB model if configured. */
    protected function load_country_rows_from_api_or_db() {
        $response = $this->elintom_response->coerce_geo_api_root($this->api->get_countries());
        if ($response && $this->elintom_response->api_geo_payload_usable($response, 'countries')) {
            $list = $this->elintom_response->unwrap_geo_list_from_api_response($response, 'countries');
            if ($list === null) {
                return array();
            }
            if ($list instanceof Traversable) {
                $list = iterator_to_array($list);
            } elseif (is_object($list)) {
                $list = (array) $list;
            }
            return is_array($list) ? $list : array();
        }
        if (!$this->has_local_db()) {
            return array();
        }
        return $this->_fallback_webshop_model()->getCountry();
    }

    /* ================================================================
     * CUSTOMER
     * ================================================================ */

    /* ================================================================
     * CUSTOMER / AUTH
     * ================================================================ */

    public function authenticate_user_password($mobile, $password) {
        $res = $this->api->login_customer($mobile, $password);
        if ($res && isset($res->status) && strtoupper($res->status) === 'SUCCESS' && isset($res->customer)) {
            return is_object($res->customer) ? $res->customer : (object) $res->customer;
        }
        return null;
    }

    public function get_customer(array $filter) {
        $res = $this->api->get_customer($filter);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return (array) $res->customer;
        }
        return false;
    }

    public function add_customer(array $data) {
        $res = $this->api->create_customer($data);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return (array) $res->customer;
        }
        $this->_log_error('create_customer');
        return false;
    }

    public function login_customer($login, $password) {
        $res = $this->api->login_customer($login, $password);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return (array) $res->customer;
        }
        return false;
    }

    public function register_check($phone = null, $email = null) {
        $res = $this->api->register_check($phone, $email);
        return $res ? $res : (object)['dup_phone' => false, 'dup_email' => false];
    }

    /* ================================================================
     * ADDRESSES
     * ================================================================ */

    public function get_customer_address($customer_id, $address_id = null) {
        $res = $this->api->get_addresses($customer_id, $address_id);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return $res->addresses;
        }
        return [];
    }

    public function add_address(array $data) {
        // ElintOm's addaddress handler reads 'customer_id' from POST, but the webshop
        // controller builds address arrays with 'company_id'. Normalise here so the
        // API receives the field name it expects.
        if (!isset($data['customer_id']) && isset($data['company_id'])) {
            $data['customer_id'] = $data['company_id'];
            unset($data['company_id']);
        }
        // ElintOm stores the address email column as 'email', but the webshop array
        // uses 'email_id' (matching the local DB column name). Map it here.
        if (!isset($data['email']) && isset($data['email_id'])) {
            $data['email'] = $data['email_id'];
            unset($data['email_id']);
        }

        $res = $this->api->add_address($data);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return isset($res->address_id) ? $res->address_id : true;
        }
        $this->_log_error('add_address');
        return false;
    }

    public function getAddressDefault($customer_id, $addressType) {
        $addresses = $this->get_customer_address($customer_id);
        if (empty($addresses)) return false;
        foreach ($addresses as $addr) {
            if (isset($addr->address_type) && strtolower($addr->address_type) == strtolower($addressType)) {
                return $addr->id;
            }
        }
        // fallback to first if none matches explicitly
        return isset($addresses[0]->id) ? $addresses[0]->id : false;
    }

    /* ================================================================
     * PRODUCT — individual lookup (used by submit_order)
     * ================================================================ */

    /**
     * Fetch a single product by numeric ID.
     *
     * submit_order() calls: $data = get_product_by_id($id, $select)
     * and expects:          $data[$id] = array of product fields
     *
     * We first try the catalogue API (get_products_list with byid=[id]),
     * then fall back to the legacy hash-based endpoint.
     * In DB-less mode the $select string is ignored — we return whatever
     * the API provides, and submit_order already has isset() guards for
     * every field it reads.
     *
     * @param  int|string $product_id  Numeric product ID
     * @param  string     $select      Ignored in API mode
     * @return array<int,array>        [$product_id => product_row] or [$product_id => []]
     */
    public function get_product_by_id($product_id, $select = '') {
        $pid = (int) $product_id;
        if ($pid < 1) {
            return array($pid => array());
        }

        // Try bulk list API first (same path used by the cart enrichment).
        $list = $this->get_products_list('products', array($pid), false, 1, 1);
        if (is_array($list) && !empty($list['items'])) {
            foreach ($list['items'] as $row) {
                $a  = is_array($row) ? $row : (array) $row;
                $id = isset($a['id']) ? (int) $a['id'] : 0;
                if ($id === $pid) {
                    return array($pid => $this->_flatten_product_for_order($a));
                }
            }
        }

        // Fall back to hash-based single-product endpoint.
        $by_hash = $this->_legacy_fetch_products_by_numeric_ids(array($pid));
        if (!empty($by_hash[$pid])) {
            return array($pid => $this->_flatten_product_for_order($by_hash[$pid]));
        }

        // Product not found — return empty row; submit_order's isset() guards handle missing fields.
        log_message('error', 'Webshop_api_model::get_product_by_id — product ' . $pid . ' not found via API.');
        return array($pid => array());
    }

    /**
     * Flatten a normalised product array into the flat key names submit_order expects
     * (code, name, sale_unit_id, mrp, tax_id, tax_method, product_type, price, …).
     *
     * @param  array $a  Normalised product row from the API
     * @return array     Flat product row
     */
    protected function _flatten_product_for_order(array $a) {
        // API normalizer may nest the original data under 'raw' or return it flat.
        $raw = (isset($a['raw']) && is_array($a['raw'])) ? array_merge($a, $a['raw']) : $a;
        return array(
            'id'           => isset($raw['id'])           ? $raw['id']          : (isset($a['id'])           ? $a['id']          : 0),
            'code'         => isset($raw['code'])         ? $raw['code']        : (isset($a['code'])         ? $a['code']        : ''),
            'article_code' => isset($raw['article_code']) ? $raw['article_code'] : '',
            'name'         => isset($raw['name'])         ? $raw['name']        : (isset($a['name'])         ? $a['name']        : ''),
            'price'        => isset($raw['price'])        ? $raw['price']       : (isset($a['eshop_price'])  ? $a['eshop_price'] : (isset($a['price']) ? $a['price'] : 0)),
            'mrp'          => isset($raw['mrp'])          ? $raw['mrp']         : (isset($a['mrp'])          ? $a['mrp']         : 0),
            'tax_id'       => isset($raw['tax_id'])       ? $raw['tax_id']      : (isset($a['tax_rate'])     ? $a['tax_rate']    : null),
            'tax_method'   => isset($raw['tax_method'])   ? $raw['tax_method']  : (isset($a['tax_method'])   ? $a['tax_method']  : 0),
            'product_type' => isset($raw['product_type']) ? $raw['product_type'] : (isset($a['type'])        ? $a['type']        : ''),
            'sale_unit_id' => isset($raw['sale_unit_id']) ? $raw['sale_unit_id'] : (isset($a['sale_unit'])   ? $a['sale_unit']   : null),
            'hsn_code'     => isset($raw['hsn_code'])     ? $raw['hsn_code']    : '',
            'promotion'    => isset($raw['promotion'])    ? $raw['promotion']   : 0,
            'promo_price'  => isset($raw['promo_price'])  ? $raw['promo_price'] : 0,
            'weight'       => isset($raw['weight'])       ? $raw['weight']      : 0,
            'storage_type' => isset($raw['storage_type']) ? $raw['storage_type'] : '',
        );
    }

    /**
     * Return a units map keyed by unit_id.
     * In DB-less/API mode the unit code is not critical for order placement,
     * so return an empty array — submit_order falls back to '' for the code.
     *
     * @return array
     */
    public function get_units() {
        if ($this->has_local_db()) {
            return $this->_fallback_webshop_model()->get_units();
        }
        return array();
    }

    /* ================================================================
     * ORDERS
     * ================================================================ */

    /**
     * Create an order in ElintOm via API.
     *
     * @param  array $order   Order data
     * @param  array $items   Order items
     * @return int|false      sale_id or false
     */
    public function add_order(array $order, array $items) {
        if ($this->api_mode || !$this->has_local_db()) {
            $res = $this->api->add_order($order, $items);
            if ($res && isset($res->status) && $res->status === 'SUCCESS') {
                return isset($res->order_id) ? (int) $res->order_id : true;
            }
            if (!$this->has_local_db()) {
                $this->_log_error('add_order');
                return false;
            }
            if (!$this->fallback) {
                $this->_log_error('add_order');
                return false;
            }
        }
        return $this->_fallback_webshop_model()->add_order($order, $items);
    }

    /** In-request cache for gateway credentials (one API call per request). */
    private $_gateway_credentials_cache = null;

    /**
     * Fetch payment gateway credentials + enabled flags from ElintOm.
     * Returns an array keyed by gateway name, each with 'enabled' and credential keys.
     */
    public function get_gateway_credentials() {
        if ($this->_gateway_credentials_cache !== null) {
            return $this->_gateway_credentials_cache;
        }
        $res = $this->api->get_gateway_credentials();
        if ($res && isset($res->status) && $res->status === 'SUCCESS' && isset($res->gateways)) {
            $this->_gateway_credentials_cache = json_decode(json_encode($res->gateways), true);
        } else {
            $this->_gateway_credentials_cache = array();
        }
        return $this->_gateway_credentials_cache;
    }

    /** Cache to avoid double API calls for order + items in the same request. */
    private $_order_cache = array();

    public function get_order_by_id($order_id) {
        $id = (int) $order_id;
        if (isset($this->_order_cache[$id])) {
            return $this->_order_cache[$id]['order'];
        }
        $res = $this->api->get_order($id);
        if ($res && isset($res->status) && $res->status === 'SUCCESS' && isset($res->order)) {
            $this->_order_cache[$id] = array(
                'order' => (array) $res->order,
                'items' => isset($res->items) ? array_map(function($i){ return (array) $i; }, (array) $res->items) : array(),
            );
            return $this->_order_cache[$id]['order'];
        }
        return null;
    }

    public function get_order_items_by_order_id($order_id) {
        $id = (int) $order_id;
        if (!isset($this->_order_cache[$id])) {
            $this->get_order_by_id($id); // populates the cache
        }
        return isset($this->_order_cache[$id]['items']) ? $this->_order_cache[$id]['items'] : array();
    }

    public function get_customer_sales($customer_id, $sale_status = '') {
        $res = $this->api->get_customer_sales($customer_id, $sale_status);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return $res->sales;
        }
        return [];
    }

    /* ================================================================
     * COUPONS
     * ================================================================ */

    public function apply_coupon($code, $cart_total = 0) {
        $res = $this->api->apply_coupon($code, $cart_total);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return (array) $res->coupon;
        }
        $this->_log_error('apply_coupon');
        return false;
    }

    /* ================================================================
     * WISHLIST
     * ================================================================ */

    public function get_wishlist($user_id) {
        $res = $this->api->get_wishlist($user_id);
        if ($res && isset($res->status) && $res->status === 'SUCCESS' && isset($res->wishlist)) {
            $list = is_array($res->wishlist) ? $res->wishlist : (array) $res->wishlist;
            $out = [];
            foreach ($list as $item) {
                $o = is_object($item) ? $item : (object) $item;
                // Coerce standard fields if they are missing but aliases exist
                if (!isset($o->product_id) && isset($o->id)) $o->product_id = $o->id;
                if (!isset($o->option_id)) $o->option_id = 0;
                $out[] = $o;
            }
            return $out;
        }
        return [];
    }

    public function get_wishlist_count($user_id) {
        if ($this->api_mode || !$this->has_local_db()) {
            if (!$user_id) return 0;
            $res = $this->get_wishlist($user_id);
            return count($res);
        }
        return $this->_fallback_webshop_model()->get_wishlist_count($user_id);
    }

    public function add_to_wishlist($user_id, $product_id, $option_id = null) {
        $res = $this->api->add_wishlist($user_id, $product_id, $option_id);
        return $res && isset($res->status) && $res->status === 'SUCCESS';
    }

    public function remove_from_wishlist($user_id, $product_id, $option_id = null) {
        $res = $this->api->remove_wishlist($user_id, $product_id, $option_id);
        return $res && isset($res->status) && $res->status === 'SUCCESS';
    }

    /* ================================================================
     * COMPANY
     * ================================================================ */

    public function get_company_by_id($biller_id) {
        $res = $this->api->get_company($biller_id);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return (array) $res->company;
        }
        return [];
    }

    /* ================================================================
     * PRIVATE
     * ================================================================ */

    protected function _log_error($method) {
        $err = $this->api->get_last_error();
        if ($err) {
            log_message('error', 'Webshop_api_model::' . $method . ' — ' . $err);
        }
    }
}
