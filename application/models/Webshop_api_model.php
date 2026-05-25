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
#[\AllowDynamicProperties]
class Webshop_api_model extends CI_Model {

    /** @var Elintom_api_client */
    protected $api;

    /** Whether to use API or direct DB */
    protected $api_mode = false;

    /** Whether to fall back to DB if API fails */
    protected $fallback = true;

    /** @var array|null Cached tree from get_categories() within one request */
    protected $_categories_cache = null;

    /** @var bool Memo guard so home_page_data() hits CMS/API at most once per HTTP request */
    protected $_home_page_data_memo_set = false;

    /** @var stdClass|null Memoized return value for home_page_data() */
    protected $_home_page_data_memo = null;

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

    /**
     * Bump when category session cache payload shape or merge rules change so old session blobs are ignored.
     *
     * @return int
     */
    protected function _categories_session_cache_version() {
        return 2;
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
            'ver' => $this->_categories_session_cache_version(),
        ));
    }

    /* ================================================================
     * SETTINGS
     * ================================================================ */

    public function get_settings() {
        $CI = get_instance();
        if (isset($CI->input) && trim((string) $CI->input->get('refresh_settings')) !== '') {
            $CI->load->helper('webshop_helper');
            if (function_exists('webshop_clear_elintom_settings_cache')) {
                webshop_clear_elintom_settings_cache();
            }
        }

        $ttl = $this->_elintom_http_cache_ttl('elintom_http_cache_settings_seconds', 45);
        if ($ttl > 0) {
            if (isset($CI->session)) {
                $row = $CI->session->userdata('elintom_cache_getsettings');
                if (is_array($row) && isset($row['exp'], $row['blob']) && (int) $row['exp'] > time()) {
                    $cached = json_decode($row['blob']);
                    if ($cached !== null && is_object($cached)) {
                        return $this->_filter_storefront_settings_response($cached);
                    }
                }
            }
        }
        $res = $this->api->get_settings();
        $res = $this->_filter_storefront_settings_response($res);
        if ($ttl > 0 && $res && is_object($res) && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            $this->_store_settings_session_cache($res, $ttl);
        }
        return $res;
    }

    /**
     * Strip inactive storefront rows from getsettings (is_active must be exactly 1).
     *
     * @param object|null $res
     * @return object|null
     */
    protected function _filter_storefront_settings_response($res) {
        if (!$res || !is_object($res) || !isset($res->status) || strtoupper((string) $res->status) !== 'SUCCESS') {
            return $res;
        }
        $CI = get_instance();
        $CI->load->helper('webshop_helper');
        if (isset($res->website_setting_sections)) {
            $res->website_setting_sections = webshop_filter_website_setting_sections_object($res->website_setting_sections);
        }
        if (isset($res->website_setting) && is_array($res->website_setting)) {
            $res->website_setting = webshop_filter_active_website_setting_rows($res->website_setting);
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
        if ($apiPage !== null && $this->cms_page_url_matches_requested($apiPage, '/about-us')) {
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
        if ($this->_home_page_data_memo_set) {
            return $this->_home_page_data_memo;
        }

        // ElintOm often registers the storefront home as `/home-page` (see CMS "URL" field), not `/`.
        $candidates = array('/home-page', '/home', '/');
        foreach ($candidates as $path) {
            $apiPage = $this->get_cms_page_content($path);
            if ($apiPage !== null && $this->cms_page_url_matches_requested($apiPage, $path)) {
                $this->_home_page_data_memo = $apiPage;
                $this->_home_page_data_memo_set = true;
                return $this->_home_page_data_memo;
            }
        }
        $o = new stdClass();
        $o->page_key = 'home';
        $o->page_title = '';
        $o->page_text = '';
        $o->meta_tags = '';
        $o->sections = array();
        $o->cms_loaded_from_api = false;
        $o->cms_page_found = false;
        $this->_home_page_data_memo = $o;
        $this->_home_page_data_memo_set = true;
        return $this->_home_page_data_memo;
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
        $seenHome = false;
        foreach ($pages as $row) {
            $a = is_object($row) ? (array) $row : (is_array($row) ? $row : array());
            $url   = isset($a['url']) ? trim((string) $a['url']) : '';
            $title = isset($a['page_name']) ? trim((string) $a['page_name']) : '';
            $status = isset($a['status']) ? strtolower(trim((string) $a['status'])) : 'published';

            if ($url === '' || $title === '' || $status !== 'published') {
                continue;
            }
            $isHome = $this->is_cms_home_storefront_url($url);
            if ($isHome && $seenHome) {
                continue;
            }
            $href = $this->map_cms_url_to_webshop_href($url);
            if ($href === '') {
                continue;
            }
            if ($isHome) {
                $seenHome = true;
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
     * Map CMS admin URL (sma_pages.url) to storefront href.
     * CMS defines e.g. /about-us → webshop/about-us (same slug as in admin panel).
     *
     * @param string $cms_url
     * @return string
     */
    protected function map_cms_url_to_webshop_href($cms_url) {
        $url = '/' . ltrim((string) $cms_url, '/');
        if ($url === '//') {
            $url = '/';
        }
        if ($this->is_cms_home_storefront_url($url)) {
            return base_url('webshop');
        }
        return base_url('webshop/' . ltrim($url, '/'));
    }

    /**
     * CMS admin home URLs that map to storefront index (webshop).
     *
     * @param string $cms_url
     * @return bool
     */
    protected function is_cms_home_storefront_url($cms_url) {
        $slug = strtolower(ltrim(str_replace('_', '-', (string) $cms_url), '/'));
        return in_array($slug, array('', 'home', 'home-page'), true);
    }

    /**
     * Build CMS API URL candidates from a storefront URI segment (webshop/{segment}).
     * Matches ElintOm cms_admin published pages by url field first.
     *
     * @param string $storefrontSlug e.g. about-us, about_us, privacy-policy
     * @return array<int,string> Paths like /about-us
     */
    /**
     * Published CMS nav row for a storefront segment (about_us, about-us, etc.).
     *
     * @param string $storefrontSlug
     * @return array{title:string,url:string,href:string}|null
     */
    public function find_cms_nav_page_by_storefront_slug($storefrontSlug) {
        $storefrontSlug = trim((string) $storefrontSlug, '/');
        if ($storefrontSlug === '') {
            return null;
        }
        $dashSlug = str_replace('_', '-', $storefrontSlug);
        $underSlug = str_replace('-', '_', $storefrontSlug);
        foreach ($this->get_cms_nav_pages() as $nav) {
            if (!isset($nav['url'])) {
                continue;
            }
            $cmsSlug = ltrim((string) $nav['url'], '/');
            if ($cmsSlug === $storefrontSlug || $cmsSlug === $dashSlug || $cmsSlug === $underSlug) {
                return $nav;
            }
        }
        return null;
    }

    /**
     * @param stdClass $page
     * @param string   $requestedPath e.g. /privacy-policy
     * @return bool
     */
    public function cms_page_url_matches_requested($page, $requestedPath) {
        if (!is_object($page)) {
            return false;
        }
        $requested = '/' . ltrim((string) $requestedPath, '/');
        if ($requested === '//') {
            $requested = '/';
        }
        $pageUrl = isset($page->url) ? trim((string) $page->url) : '';
        if ($pageUrl === '') {
            return false;
        }
        $pageUrl = '/' . ltrim($pageUrl, '/');
        if ($pageUrl === $requested) {
            return true;
        }
        $norm = function ($path) {
            return strtolower(str_replace('_', '-', ltrim((string) $path, '/')));
        };
        return $norm($pageUrl) === $norm($requested);
    }

    public function cms_url_candidates_from_storefront_slug($storefrontSlug) {
        $storefrontSlug = trim((string) $storefrontSlug, '/');
        $paths = array();
        if ($storefrontSlug === '') {
            return array('/');
        }

        $dashSlug = str_replace('_', '-', $storefrontSlug);
        $underSlug = str_replace('-', '_', $storefrontSlug);

        // Published pages from CMS admin (source of truth for URL field) — try exact admin URL first.
        foreach ($this->get_cms_nav_pages() as $nav) {
            if (!isset($nav['url'])) {
                continue;
            }
            $cmsUrl = '/' . ltrim((string) $nav['url'], '/');
            $cmsSlug = ltrim($cmsUrl, '/');
            if ($cmsSlug === $storefrontSlug || $cmsSlug === $dashSlug || $cmsSlug === $underSlug) {
                $paths[] = $cmsUrl;
            }
        }

        // Legacy CI method names (about_us) → typical CMS admin paths.
        $legacyMethodMap = array(
            'about_us'             => array('/about-us'),
            'terms_and_conditions' => array('/terms', '/terms-and-conditions'),
            'privacy_policy'       => array('/privacy-policy'),
            'contact_us'           => array('/contact-us'),
        );
        if (isset($legacyMethodMap[$storefrontSlug])) {
            $paths = array_merge($paths, $legacyMethodMap[$storefrontSlug]);
        }

        $paths[] = '/' . $dashSlug;
        if ($underSlug !== $dashSlug) {
            $paths[] = '/' . $underSlug;
        }
        $paths[] = '/' . $storefrontSlug;

        $unique = array();
        foreach ($paths as $p) {
            $norm = '/' . ltrim((string) $p, '/');
            if ($norm === '//') {
                $norm = '/';
            }
            $unique[$norm] = $norm;
        }
        return array_values($unique);
    }

    /**
     * @param string $urlPath CMS path or slug fragment
     * @return array<int,string>
     */
    public function cms_url_candidates_from_path($urlPath) {
        $primary = '/' . ltrim((string) $urlPath, '/');
        if ($primary === '//') {
            $primary = '/';
        }
        if ($primary === '/') {
            return array('/');
        }
        $slug = ltrim($primary, '/');
        return $this->cms_url_candidates_from_storefront_slug($slug);
    }

    /**
     * Resolve a published CMS page via getcmspage (tries each admin URL candidate).
     *
     * @param array<int,string> $urlCandidates
     * @return stdClass|null
     */
    public function find_published_cms_page(array $urlCandidates) {
        foreach ($urlCandidates as $path) {
            $norm = '/' . ltrim((string) $path, '/');
            if ($norm === '//') {
                $norm = '/';
            }
            $page = $this->get_cms_page_content($norm);
            if ($page === null) {
                continue;
            }
            if (!$this->cms_page_url_matches_requested($page, $norm)) {
                continue;
            }
            $status = isset($page->status) ? strtolower(trim((string) $page->status)) : '';
            if ($status !== '' && $status !== 'published') {
                continue;
            }
            if (isset($page->url) && trim((string) $page->url) !== '') {
                $page->url = '/' . ltrim((string) $page->url, '/');
            } else {
                $page->url = $norm;
            }
            return $page;
        }
        return null;
    }

    /**
     * Canonical storefront slug for cms_page() from loaded CMS row.
     *
     * @param stdClass $cmsPage
     * @return string
     */
    public function cms_storefront_slug_from_page($cmsPage) {
        if (!is_object($cmsPage)) {
            return '';
        }
        $url = isset($cmsPage->url) ? trim((string) $cmsPage->url) : '';
        if ($url === '') {
            return isset($cmsPage->page_key) ? trim((string) $cmsPage->page_key) : '';
        }
        return ltrim($url, '/');
    }

    public function terms_conditions($page_key = 'terms_conditions') {
        foreach (array('/terms', '/terms-and-conditions') as $termsPath) {
            $apiPage = $this->get_cms_page_content($termsPath);
            if ($apiPage !== null && $this->cms_page_url_matches_requested($apiPage, $termsPath)) {
                return $apiPage;
            }
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
        if ($apiPage !== null && $this->cms_page_url_matches_requested($apiPage, '/privacy-policy')) {
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
     * Contact page row from CMS admin (/contact-us).
     *
     * @param string $page_key
     * @return stdClass|null
     */
    public function contact_usdata($page_key = 'contact') {
        $apiPage = $this->get_cms_page_content('/contact-us');
        if ($apiPage !== null && $this->cms_page_url_matches_requested($apiPage, '/contact-us')) {
            return $apiPage;
        }
        if ($this->has_local_db()) {
            $fallback = $this->_fallback_webshop_model();
            if (method_exists($fallback, 'contact_usdata')) {
                return $fallback->contact_usdata($page_key);
            }
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
            if ($msgText === '' && method_exists($this->api, 'get_last_error')) {
                $transportErr = (string) $this->api->get_last_error();
                if ($transportErr !== '') {
                    $msgText = $transportErr;
                }
            }
            $logLine = 'Webshop_api_model:get_cms_page_content failed url=' . (string) $url_path
                . ' status=' . $statusText . ' msg=' . $msgText;
            $benignMiss = stripos($msgText, 'not found') !== false;
            log_message($benignMiss ? 'debug' : 'error', $logLine);

            if ($this->should_use_cms_direct_db_fallback($res, $msgText)) {
                $direct = $this->get_cms_page_content_direct_db($url_path);
                if ($direct !== null) {
                    log_message('info', 'Webshop_api_model:get_cms_page_content direct_db ok url=' . (string) $url_path);
                    return $direct;
                }
            }
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
        $o->id = isset($pageArr['id']) ? (int) $pageArr['id'] : (isset($resArr['id']) ? (int) $resArr['id'] : 0);
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
        $apiMetaHtml = $pick_first_string(array($resArr, $pageArr), array('meta_tags_html', 'meta_tags', 'meta'));
        $o->meta_tags = $this->resolve_cms_meta_tags_html($o->meta_tags_raw, $apiMetaHtml, $o->page_title);
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
        $hasRenderableSections = !empty($o->sections) && is_array($o->sections);
        if ($hasRenderableSections) {
            // getcmspage content_html / body_html is buildLayoutSections() output — same as section render.
            $o->page_text = $pick_first_string(
                array($pageArr),
                array('page_text', 'content', 'description', 'page_description')
            );
        } else {
            $o->page_text = $pick_first_string(
                array($resArr, $pageArr),
                array('content_html', 'body_html', 'page_text', 'content', 'description', 'page_description')
            );
            if (trim((string) $o->page_text) === '' && is_object($res) && isset($res->content_html) && trim((string) $res->content_html) !== '') {
                $o->page_text = trim((string) $res->content_html);
            }
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
        $o->page_summary = $pick_first_string(
            array($resArr, $pageArr),
            array('page_summary', 'summary', 'subtitle', 'tagline', 'announcement')
        );
        $o->page_description = $pick_first_string(
            array($resArr, $pageArr),
            array('short_description', 'excerpt', 'strapline')
        );
        // Do not copy html_block section HTML into page_text when sections[] is present:
        // the storefront renders sections via Webshop_section_engine::render_components().
        // Merging section HTML into page_text caused duplicate blocks (e.g. "Category" twice).
        $o->cms_loaded_from_api = true;
        $o->cms_page_found = true;
        return $o;
    }

    /**
     * Prefer storefront-built meta from page_tag_mapping rows; fall back to API HTML.
     *
     * @param mixed  $meta_tags_raw
     * @param string $api_meta_html
     * @param string $page_title
     * @return string
     */
    protected function resolve_cms_meta_tags_html($meta_tags_raw, $api_meta_html, $page_title = '') {
        if (function_exists('webshop_meta_tags_html_from_cms_rows') && is_array($meta_tags_raw) && !empty($meta_tags_raw)) {
            $built = webshop_meta_tags_html_from_cms_rows($meta_tags_raw, array(
                'page_title' => (string) $page_title,
            ));
            if (trim($built) !== '') {
                return $built;
            }
        }
        return trim((string) $api_meta_html);
    }

    /**
     * Fallback when getcmspage HTTP fails (remote 500, wrong API host, etc.).
     * Skipped when ElintOm API explicitly says the page does not exist (empty CMS admin list).
     *
     * @param string $url_path
     * @return stdClass|null
     */
    protected function get_cms_page_content_direct_db($url_path) {
        $this->config->load('elintom_api', true);
        if (!(bool) $this->config->item('elintom_cms_direct_db', 'elintom_api')) {
            return null;
        }
        $CI =& get_instance();
        $CI->load->library('cms_direct_db');
        if (!isset($CI->cms_direct_db) || !$CI->cms_direct_db->is_ready()) {
            return null;
        }
        return $CI->cms_direct_db->get_page_by_url($url_path);
    }

    /**
     * Use local sma_pages only when API did not definitively say the page is missing.
     *
     * @param object|null $res
     * @param string      $msgText
     * @return bool
     */
    protected function should_use_cms_direct_db_fallback($res, $msgText) {
        $this->config->load('elintom_api', true);
        if (!(bool) $this->config->item('elintom_cms_direct_db', 'elintom_api')) {
            return false;
        }
        $msgText = strtolower(trim((string) $msgText));
        $apiResponded = ($res !== null && is_object($res) && isset($res->status));
        $pageMissing = $apiResponded && (
            stripos($msgText, 'not found') !== false
            || stripos($msgText, 'page not found') !== false
        );
        if ($pageMissing && !(bool) $this->config->item('elintom_cms_direct_db_on_api_not_found', 'elintom_api')) {
            return false;
        }
        return true;
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
        if (!function_exists('webshop_enrich_cart_session_variant_labels')) {
            $CI = get_instance();
            if (isset($CI->load)) {
                $CI->load->helper('webshop');
            }
        }
        if (function_exists('webshop_enrich_cart_session_variant_labels')) {
            $data['products'] = webshop_enrich_cart_session_variant_labels($data['products']);
        }
        if (function_exists('webshop_cart_variants_map_from_products')) {
            $data['variants'] = webshop_cart_variants_map_from_products($data['products']);
            foreach ($_SESSION['cart'] as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $vid = isset($line['variant_id']) ? (int) $line['variant_id'] : 0;
                if ($vid < 1 || isset($data['variants'][$vid])) {
                    continue;
                }
                if (!empty($line['variant_name'])) {
                    $data['variants'][$vid] = array(
                        'id'         => $vid,
                        'name'       => trim((string) $line['variant_name']),
                        'product_id' => isset($line['product_id']) ? (int) $line['product_id'] : 0,
                    );
                }
            }
        }
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
    /**
     * Resolve one cart/catalogue row by numeric product id (multiple API strategies).
     *
     * @param  int $product_id
     * @return array Normalized product row or empty array
     */
    /**
     * Bulk resolve cart/catalogue rows (one list API call when possible).
     *
     * @param  int[] $product_ids
     * @return array<int,array> id => normalized row
     */
    public function resolve_product_rows_by_ids(array $product_ids) {
        $want = array();
        foreach ($product_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $want[$id] = $id;
            }
        }
        if ($want === array()) {
            return array();
        }

        $out = array();
        $id_list = implode(',', array_values($want));
        $list = $this->get_products_list('products', $id_list, false, 0, 1);
        if (is_array($list) && !empty($list['items']) && is_array($list['items'])) {
            foreach ($list['items'] as $row) {
                $a = is_array($row) ? $row : (array) $row;
                $pid = isset($a['id']) ? (int) $a['id'] : (isset($a['product_id']) ? (int) $a['product_id'] : 0);
                if ($pid > 0) {
                    $out[$pid] = $this->elintom_response->normalize_product_detail_item($a);
                }
            }
        }

        $missing = array();
        foreach ($want as $pid) {
            if (!isset($out[$pid])) {
                $missing[] = $pid;
            }
        }
        if (!empty($missing)) {
            $legacy = $this->_get_products_list_legacy_by_ids($id_list);
            if (is_array($legacy) && !empty($legacy['items']) && is_array($legacy['items'])) {
                foreach ($legacy['items'] as $row) {
                    $a = is_array($row) ? $row : (array) $row;
                    $pid = isset($a['id']) ? (int) $a['id'] : (isset($a['product_id']) ? (int) $a['product_id'] : 0);
                    if ($pid > 0) {
                        $out[$pid] = $this->elintom_response->normalize_product_detail_item($a);
                    }
                }
            }
        }

        foreach ($want as $pid) {
            if (isset($out[$pid])) {
                continue;
            }
            $row = $this->resolve_product_row_by_id($pid);
            if (!empty($row)) {
                $out[$pid] = $row;
            }
        }

        return $out;
    }

    public function resolve_product_row_by_id($product_id) {
        $pid = (int) $product_id;
        if ($pid < 1) {
            return array();
        }

        $this->load->helper('webshop');
        $item = array();

        $list = $this->get_products_list('products', (string) $pid, false, 0, 1);
        if (is_array($list) && !empty($list['items']) && is_array($list['items'])) {
            foreach ($list['items'] as $row) {
                $a = is_array($row) ? $row : (array) $row;
                $rid = isset($a['id']) ? (int) $a['id'] : (isset($a['product_id']) ? (int) $a['product_id'] : 0);
                if ($rid === $pid) {
                    $item = $this->elintom_response->normalize_product_detail_item($a);
                    break;
                }
            }
            if ($item === array() && !empty($list['items'][0])) {
                $first = is_array($list['items'][0]) ? $list['items'][0] : (array) $list['items'][0];
                $item = $this->elintom_response->normalize_product_detail_item($first);
            }
        }

        $need_detail = ($item === array());
        if (!$need_detail && function_exists('webshop_product_variants_from_row')) {
            $variants = webshop_product_variants_from_row($item);
            $price = isset($item['price']) ? (float) $item['price'] : 0.0;
            $eshop = isset($item['eshop_price']) ? (float) $item['eshop_price'] : -1.0;
            $need_detail = ($variants === array())
                || ($price <= 0 && $eshop <= 0);
            if (!$need_detail && $variants !== array() && function_exists('webshop_product_list_card_pricing')) {
                $card = webshop_product_list_card_pricing($item, null);
                $need_detail = ((float) $card['price'] <= 0);
            }
            if (!$need_detail && isset($item['type']) && strtolower((string) $item['type']) === 'variable' && $variants === array()) {
                $need_detail = true;
            }
        }

        $hash = md5((string) $pid);
        if ($need_detail && $this->use_elintom_api_catalogue()) {
            $res = $this->api->get_product_by_hash($hash, $pid);
            if ($res && $this->elintom_response->api_status_ok($res)) {
                $bundle = $this->elintom_response->product_detail_bundle_from_api_response($res);
                if (is_array($bundle) && isset($bundle['item'])) {
                    $detail = is_array($bundle['item']) ? $bundle['item'] : (array) $bundle['item'];
                    if (!empty($detail)) {
                        $detail = $this->elintom_response->normalize_product_detail_item($detail);
                        if ($item !== array()) {
                            $item = array_merge($item, $detail);
                        } else {
                            $item = $detail;
                        }
                        if (!empty($bundle['variants'])) {
                            $item['variants'] = $bundle['variants'];
                        }
                    }
                }
            }
        }

        if ($item !== array()) {
            return $item;
        }

        $bundle = $this->get_product_by_hash($hash);
        if (is_array($bundle) && isset($bundle['item'])) {
            $a = is_array($bundle['item']) ? $bundle['item'] : (array) $bundle['item'];
            if (!empty($a)) {
                $a = $this->elintom_response->normalize_product_detail_item($a);
                if (!empty($bundle['variants'])) {
                    $a['variants'] = $bundle['variants'];
                }
                return $a;
            }
        }

        $flat = $this->get_product_by_id($pid);
        if (isset($flat[$pid]) && is_array($flat[$pid]) && $flat[$pid] !== array()) {
            return $this->elintom_response->normalize_product_detail_item($flat[$pid]);
        }

        return array();
    }

    protected function _legacy_fetch_products_by_numeric_ids(array $want_ids) {
        return $this->resolve_product_rows_by_ids($want_ids);
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
     * Legacy api3/eshop getallproducts filtered to explicit product ids (cart enrichment).
     *
     * @param string|int|array $byid Comma list or array of numeric ids
     * @return array|null Normalized list payload
     */
    protected function _get_products_list_legacy_by_ids($byid) {
        $want = array();
        if (is_array($byid)) {
            foreach ($byid as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $want[$id] = true;
                }
            }
        } else {
            foreach (preg_split('/\s*,\s*/', (string) $byid, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $want[$id] = true;
                }
            }
        }
        if ($want === array()) {
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
        $normalized = $this->elintom_response->normalize_products_list_payload($raw, 1);
        $items = isset($normalized['items']) && is_array($normalized['items']) ? $normalized['items'] : array();
        if (empty($items)) {
            $items = $this->elintom_response->coerce_associative_product_map_to_rows($raw);
        }
        $filtered = array();
        foreach ($items as $row) {
            $a = is_array($row) ? $row : (array) $row;
            $pid = isset($a['id']) ? (int) $a['id'] : (isset($a['product_id']) ? (int) $a['product_id'] : 0);
            if ($pid > 0 && isset($want[$pid])) {
                $filtered[] = $this->elintom_response->normalize_product_detail_item($a);
            }
        }
        if ($filtered === array()) {
            return null;
        }
        $normalized['items'] = $filtered;
        $normalized['items_total'] = count($filtered);
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
        $cat_ttl = $this->_elintom_http_cache_ttl('elintom_http_cache_categories_seconds', 0);
        if ($cat_ttl > 0 && $this->use_elintom_api_catalogue()) {
            $CI = get_instance();
            if (isset($CI->session)) {
                $row = $CI->session->userdata('elintom_cache_categories');
                $verOk = is_array($row) && isset($row['ver']) && (int) $row['ver'] === $this->_categories_session_cache_version();
                if ($verOk && isset($row['exp'], $row['ser']) && (int) $row['exp'] > time()) {
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
     * Merge sellable quantity into category PLP rows when the list API omits stock fields.
     *
     * @param array $items
     * @param int   $category_id
     * @return array
     */
    public function enrich_product_list_items_with_stock(array $items, $category_id = 0) {
        if ($items === array() || !$this->use_elintom_api_catalogue()) {
            return $items;
        }
        $this->load->helper('webshop');
        $needs = false;
        foreach ($items as $item) {
            $row = is_array($item) ? $item : (array) $item;
            if (function_exists('webshop_row_numeric_stock') && webshop_row_numeric_stock($row) === null) {
                $needs = true;
                break;
            }
        }
        if (!$needs) {
            return $items;
        }
        $stockMap = $this->_fetch_category_product_stock_map((int) $category_id);
        if ($stockMap === array()) {
            return $items;
        }
        foreach ($items as $i => $item) {
            $row = is_array($item) ? $item : (array) $item;
            $pid = isset($row['id']) ? (int) $row['id'] : 0;
            if ($pid < 1 || !isset($stockMap[$pid])) {
                continue;
            }
            if (function_exists('webshop_row_numeric_stock') && webshop_row_numeric_stock($row) !== null) {
                continue;
            }
            $row['quantity'] = $stockMap[$pid];
            $items[$i] = $row;
        }
        return $items;
    }

    /**
     * Attach variant rows (and list pricing fields) to category/search PLP items when the list API omits them.
     *
     * @param array $items
     * @return array
     */
    public function enrich_product_list_items_with_variants(array $items) {
        if ($items === array()) {
            return $items;
        }
        $this->load->helper('webshop');
        foreach ($items as $i => $item) {
            $row = is_array($item) ? $item : (array) $item;
            $pid = isset($row['id']) ? (int) $row['id'] : 0;
            $variants = function_exists('webshop_product_variants_from_row')
                ? webshop_product_variants_from_row($row)
                : array();
            $price = isset($row['price']) ? (float) $row['price'] : 0.0;
            $eshop = isset($row['eshop_price']) ? (float) $row['eshop_price'] : -1.0;
            $needs_full = ($variants === array())
                || ($price <= 0 && $eshop <= 0);
            if (!$needs_full && $variants !== array() && function_exists('webshop_product_list_card_pricing')) {
                $probe = webshop_product_list_card_pricing($row, null);
                $needs_full = ((float) $probe['price'] <= 0);
            }
            if (!$needs_full && isset($row['type']) && strtolower((string) $row['type']) === 'variable' && $variants === array()) {
                $needs_full = true;
            }
            if ($needs_full && $pid > 0) {
                $full = $this->resolve_product_row_by_id($pid);
                if (is_array($full) && !empty($full)) {
                    $row = array_merge($row, $full);
                    if (!empty($full['variants'])) {
                        $row['variants'] = $full['variants'];
                    }
                }
            }
            if (function_exists('webshop_product_list_card_pricing')) {
                $card = webshop_product_list_card_pricing($row, null);
                if ((float) $card['price'] > 0) {
                    $row['list_display_price'] = (float) $card['price'];
                    $row['price'] = (float) $card['price'];
                }
                if (!empty($card['has_variants'])) {
                    $row['list_default_variant_id'] = (int) $card['variant_id'];
                    $row['list_variant_price'] = (float) $card['variant_price'];
                    $row['list_variant_unit_quantity'] = (float) $card['variant_unit_quantity'];
                    $row['list_variant_name'] = (string) $card['variant_name'];
                    $row['list_price_from'] = !empty($card['price_from']);
                    $row['list_price_min'] = isset($card['price_min']) ? (float) $card['price_min'] : 0.0;
                    $row['list_price_max'] = isset($card['price_max']) ? (float) $card['price_max'] : 0.0;
                }
                if (!empty($card['mrp']) && (float) $card['mrp'] > 0) {
                    $row['list_display_mrp'] = (float) $card['mrp'];
                    $row['mrp'] = (float) $card['mrp'];
                }
                if (!empty($card['discount_percent'])) {
                    $row['list_discount_percent'] = (int) $card['discount_percent'];
                }
            }
            $items[$i] = $row;
        }
        return $items;
    }

    /**
     * @param int $category_id
     * @return array<int,float> product_id => sellable qty
     */
    protected function _fetch_category_product_stock_map($category_id) {
        $res = $this->api->get_product_stocks((int) $category_id, 1);
        if (!$res || !$this->elintom_response->api_status_ok($res)) {
            return array();
        }
        $payload = is_object($res) ? (array) $res : (is_array($res) ? $res : array());
        $rows = array();
        foreach (array('stocks', 'product_stocks', 'items', 'data', 'result') as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $rows = $payload[$key];
                break;
            }
        }
        $map = array();
        if ($rows !== array()) {
            foreach ($rows as $row) {
                $r = is_array($row) ? $row : (array) $row;
                $pid = isset($r['product_id']) ? (int) $r['product_id'] : (isset($r['id']) ? (int) $r['id'] : 0);
                if ($pid < 1) {
                    continue;
                }
                $qty = null;
                foreach (array('quantity', 'qty', 'quantity_balance', 'stock', 'available_qty') as $qk) {
                    if (isset($r[$qk]) && is_numeric($r[$qk])) {
                        $qty = max(0.0, (float) $r[$qk]);
                        break;
                    }
                }
                if ($qty === null) {
                    continue;
                }
                if (!isset($map[$pid])) {
                    $map[$pid] = 0.0;
                }
                $map[$pid] += $qty;
            }
            return $map;
        }
        foreach ($payload as $k => $v) {
            if (!is_numeric($k) || !is_array($v)) {
                continue;
            }
            $pid = (int) $k;
            $sum = 0.0;
            foreach ($v as $optQty) {
                if (is_numeric($optQty)) {
                    $sum += max(0.0, (float) $optQty);
                }
            }
            $map[$pid] = $sum;
        }
        return $map;
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
                        if ($by === 'category' && !empty($normalized['items'])) {
                            $catId = (!$hash && is_numeric($byid)) ? (int) $byid : 0;
                            $normalized['items'] = $this->enrich_product_list_items_with_stock($normalized['items'], $catId);
                            $normalized['items'] = $this->enrich_product_list_items_with_variants($normalized['items']);
                        }
                        return $normalized;
                    }
                }
            }

            /* Same pattern as categories: webshop_api may 500 — api3 getallproducts fills category grids */
            if ($by === 'category' && $byid !== null && $byid !== '') {
                $legacyList = $this->_get_products_list_legacy_category($byid, $hash, $limit, $page);
                if ($legacyList !== null && $this->elintom_response->products_list_item_count($legacyList) > 0) {
                    if (!empty($legacyList['items'])) {
                        $catId = (!$hash && is_numeric($byid)) ? (int) $byid : 0;
                        $legacyList['items'] = $this->enrich_product_list_items_with_stock($legacyList['items'], $catId);
                        $legacyList['items'] = $this->enrich_product_list_items_with_variants($legacyList['items']);
                    }
                    return $legacyList;
                }
            }

            if ($by === 'products' && $byid !== null && $byid !== '') {
                $legacyByIds = $this->_get_products_list_legacy_by_ids($byid);
                if ($legacyByIds !== null && $this->elintom_response->products_list_item_count($legacyByIds) > 0) {
                    return $legacyByIds;
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

    /**
     * Aggregate rating for a product (legacy views expect an object with ->average and ->count).
     *
     * @param int|string $product_id
     * @return object { average: float, count: int }
     */
    public function get_product_rating($product_id) {
        $pid = (int) $product_id;
        $out = new stdClass();
        $out->average = 0.0;
        $out->count = 0;
        if ($pid <= 0) {
            return $out;
        }
        if ($this->use_elintom_api_catalogue()) {
            $res = $this->api->get_product_rating($pid);
            if ($res && $this->elintom_response->api_status_ok($res)) {
                if (isset($res->average)) {
                    $out->average = (float) $res->average;
                }
                if (isset($res->count)) {
                    $out->count = (int) $res->count;
                }
            }
            return $out;
        }
        $wm = $this->_fallback_webshop_model();
        if (method_exists($wm, 'get_product_rating')) {
            $r = $wm->get_product_rating($pid);
            if (is_object($r)) {
                return $r;
            }
            if (is_array($r)) {
                $out->average = isset($r['average']) ? (float) $r['average'] : 0.0;
                $out->count = isset($r['count']) ? (int) $r['count'] : 0;
                return $out;
            }
        }
        return $out;
    }

    /**
     * Customer reviews for storefront product detail / product_reviews page.
     * Rows match keys expected by views (reviews_rattings, reviews_title, reviews_details, reviews_date).
     *
     * @param int $product_id
     * @param int $limit
     * @return array
     */
    /**
     * Submit a customer product review through ElintOm.
     *
     * Required keys on $data:
     *   product_id, rating (1-5), review (body), review_title.
     * Optional:
     *   product_name, variant_id, variant_name, customer_id, customer_name.
     *
     * Returns ['status' => 'SUCCESS', 'msg' => ...] or ['status' => 'ERROR', 'msg' => ...].
     * Never throws — transport errors are converted to ERROR responses.
     */
    public function submit_product_review(array $data) {
        $pid    = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        $rating = isset($data['rating']) ? (int) $data['rating'] : 0;
        $review = isset($data['review']) ? trim((string) $data['review']) : '';
        $title  = isset($data['review_title']) ? trim((string) $data['review_title']) : '';
        if ($pid <= 0 || $rating < 1 || $rating > 5 || $review === '') {
            return ['status' => 'ERROR', 'msg' => 'product_id, rating (1-5) and review body are required.'];
        }

        $payload = array(
            'product_id'    => $pid,
            'rating'        => $rating,
            'review'        => $review,
            'review_title'  => $title,
            'product_name'  => isset($data['product_name']) ? (string) $data['product_name'] : '',
            'variant_id'    => isset($data['variant_id']) ? (int) $data['variant_id'] : 0,
            'variant_name'  => isset($data['variant_name']) ? (string) $data['variant_name'] : '',
            'customer_id'   => (isset($data['customer_id']) && (int) $data['customer_id'] > 0) ? (int) $data['customer_id'] : '',
            'customer_name' => isset($data['customer_name']) && $data['customer_name'] !== '' ? (string) $data['customer_name'] : 'Customer',
        );

        try {
            $res = $this->api->add_product_review($payload);
        } catch (Exception $e) {
            log_message('error', 'Webshop_api_model::submit_product_review transport error: ' . $e->getMessage());
            return ['status' => 'ERROR', 'msg' => 'Unable to reach the review service. Please try again.'];
        }

        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            return ['status' => 'SUCCESS', 'msg' => isset($res->message) ? (string) $res->message : (isset($res->msg) ? (string) $res->msg : 'Review saved.')];
        }

        $apiErr = method_exists($this->api, 'get_last_error') ? $this->api->get_last_error() : null;
        if (!$res && $apiErr) {
            log_message('error', 'Webshop_api_model::submit_product_review api error: ' . $apiErr);
        }
        $msg = $res && isset($res->msg) ? (string) $res->msg : 'Could not save the review.';
        return ['status' => 'ERROR', 'msg' => $msg];
    }


    public function get_product_reviews($product_id, $limit = 100) {
        $pid = (int) $product_id;
        if ($pid <= 0) {
            return array();
        }
        if ($this->use_elintom_api_catalogue()) {
            $lim = max(1, min(500, (int) $limit));
            $res = $this->api->get_product_reviews($pid, $lim);
            if (!$res || !$this->elintom_response->api_status_ok($res)) {
                return array();
            }
            $items = array();
            if (isset($res->items) && is_array($res->items)) {
                $items = $res->items;
            }
            $out = array();
            foreach ($items as $row) {
                $a = is_array($row) ? $row : (array) $row;
                $rnum = isset($a['reviews_rattings']) ? (float) $a['reviews_rattings']
                    : (isset($a['reviews_ratings']) ? (float) $a['reviews_ratings']
                    : (isset($a['rating']) ? (float) $a['rating'] : 0));
                $out[] = array(
                    'reviews_rattings' => (int) round($rnum),
                    'reviews_title' => isset($a['reviews_title']) ? (string) $a['reviews_title'] : (isset($a['title']) ? (string) $a['title'] : ''),
                    'reviews_details' => isset($a['reviews_details']) ? (string) $a['reviews_details'] : (isset($a['review']) ? (string) $a['review'] : ''),
                    'reviews_date' => isset($a['reviews_date']) ? (string) $a['reviews_date'] : (isset($a['created_at']) ? (string) $a['created_at'] : ''),
                    'customer_name' => isset($a['customer_name']) ? (string) $a['customer_name'] : (isset($a['user']) ? (string) $a['user'] : 'Customer'),
                );
            }
            return $out;
        }
        $wm = $this->_fallback_webshop_model();
        if (method_exists($wm, 'get_product_reviews')) {
            $r = $wm->get_product_reviews($pid, $limit);
            return is_array($r) ? $r : array();
        }
        return array();
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
            if (function_exists('webshop_forgot_password_log') && isset($filter['phone'])) {
                webshop_forgot_password_log('api_model.get_customer.ok', array(
                    'phone' => $filter['phone'],
                    'customer_id' => isset($res->customer->id) ? $res->customer->id : (isset($res->customer['id']) ? $res->customer['id'] : null),
                ));
            }
            return (array) $res->customer;
        }
        if (function_exists('webshop_forgot_password_log') && isset($filter['phone'])) {
            $apiErr = method_exists($this->api, 'get_last_error') ? $this->api->get_last_error() : null;
            webshop_forgot_password_log('api_model.get_customer.fail', array(
                'phone'      => $filter['phone'],
                'status'     => $res && isset($res->status) ? (string) $res->status : 'null',
                'msg'        => $res && isset($res->msg) ? (string) $res->msg : null,
                'error_code' => $res && isset($res->error_code) ? (int) $res->error_code : null,
                'last_error' => $apiErr,
            ));
        }
        return false;
    }

    /**
     * Resolve customer by phone trying local + international digit variants (register often stores 10-digit local).
     *
     * @param string $raw_phone
     * @param string $dial_code
     * @param int    $local_digits
     * @return array|false Customer row, or false
     */
    public function get_customer_by_phone_variants($raw_phone, $dial_code = '91', $local_digits = 10) {
        $variants = function_exists('webshop_phone_digit_variants')
            ? webshop_phone_digit_variants($raw_phone, $dial_code, $local_digits)
            : array(preg_replace('/\D/', '', (string) $raw_phone));
        foreach ($variants as $phone) {
            if ($phone === '') {
                continue;
            }
            $customer = $this->get_customer(array('phone' => $phone));
            if ($customer) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('api_model.get_customer_by_phone_variants.matched', array(
                        'phone'    => $phone,
                        'variants' => $variants,
                    ));
                }
                return $customer;
            }
        }
        if (function_exists('webshop_forgot_password_log')) {
            webshop_forgot_password_log('api_model.get_customer_by_phone_variants.not_found', array(
                'raw'      => $raw_phone,
                'variants' => $variants,
            ));
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

    /**
     * Forgot-password OTP delivery — WhatsApp leg runs on ElintOm only (passwordotpsend).
     *
     * ElintOm tries WhatsApp (direct text OTP) → SMS → email. Returns delivered.* flags for UI copy.
     * whatsapp_phone: full international digits passed to ElintOm for Cheerio "to" field.
     */
    public function send_password_otp($phone, $otp, $dial_code = '91', $local_digits = 10) {
        $variants = function_exists('webshop_phone_digit_variants')
            ? webshop_phone_digit_variants($phone, $dial_code, $local_digits)
            : array(preg_replace('/\D/', '', (string) $phone));
        if (empty($variants)) {
            $variants = array((string) $phone);
        }

        $last = array(
            'status' => 'ERROR',
            'msg'    => 'OTP delivery failed.',
            'delivered' => array(),
        );

        foreach ($variants as $try_phone) {
            if ($try_phone === '') {
                continue;
            }
            $last = $this->_send_password_otp_once($try_phone, $otp, $dial_code, $local_digits);
            if ($last['status'] === 'SUCCESS') {
                $delivered = isset($last['delivered']) && is_array($last['delivered']) ? $last['delivered'] : array();
                if (!empty($delivered['whatsapp']) || !empty($delivered['sms']) || !empty($delivered['email'])) {
                    return $last;
                }
            }
            $msg = isset($last['msg']) ? strtolower((string) $last['msg']) : '';
            $not_found = (strpos($msg, 'not found') !== false || strpos($msg, 'no account') !== false);
            if (!$not_found) {
                break;
            }
        }

        return $last;
    }

    /**
     * Single passwordotpsend API call.
     *
     * @param string $phone
     * @param string $otp
     * @return array
     */
    protected function _send_password_otp_once($phone, $otp, $dial_code = '91', $local_digits = 10) {
        if (function_exists('webshop_forgot_password_log')) {
            webshop_forgot_password_log('api_model.send_password_otp.request', array('phone' => $phone));
        }
        $whatsapp_phone = $phone;
        if (function_exists('webshop_phone_digit_variants')) {
            foreach (webshop_phone_digit_variants($phone, $dial_code, $local_digits) as $v) {
                if (strlen($v) > (int) $local_digits) {
                    $whatsapp_phone = $v;
                    break;
                }
            }
        }

        try {
            $res = $this->api->send_password_otp($phone, $otp, $whatsapp_phone);
        } catch (Exception $e) {
            log_message('error', 'Webshop_api_model::send_password_otp transport error: ' . $e->getMessage());
            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('api_model.send_password_otp.exception', array(
                    'phone' => $phone,
                    'whatsapp_phone' => $whatsapp_phone,
                    'error' => $e->getMessage(),
                ));
            }
            return array(
                'status' => 'ERROR',
                'msg'    => 'Unable to reach the messaging service. Please try again.',
                'delivered' => array(),
            );
        }
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            $out = array(
                'status' => 'SUCCESS',
                'msg'    => isset($res->msg) ? (string) $res->msg : 'OTP sent.',
                'delivered' => isset($res->delivered) ? (array) $res->delivered : array(),
            );
            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('api_model.send_password_otp.ok', array(
                    'phone'          => $phone,
                    'whatsapp_phone' => $whatsapp_phone,
                    'delivered'      => $out['delivered'],
                    'msg'            => $out['msg'],
                ));
            }
            return $out;
        }
        $apiErr = method_exists($this->api, 'get_last_error') ? $this->api->get_last_error() : null;
        if (function_exists('webshop_forgot_password_log')) {
            webshop_forgot_password_log('api_model.send_password_otp.fail', array(
                'phone'      => $phone,
                'status'     => $res && isset($res->status) ? (string) $res->status : 'null',
                'msg'        => $res && isset($res->msg) ? (string) $res->msg : null,
                'delivered'  => $res && isset($res->delivered) ? (array) $res->delivered : array(),
                'last_error' => $apiErr,
            ));
        }
        if (!$res && $apiErr) {
            log_message('error', 'Webshop_api_model::send_password_otp api error: ' . $apiErr);
        }
        return array(
            'status' => 'ERROR',
            'msg'    => $res && isset($res->msg) ? (string) $res->msg : 'OTP delivery failed.',
            'delivered' => $res && isset($res->delivered) ? (array) $res->delivered : array(),
        );
    }

    /**
     * Update the customer password through ElintOm. Caller must have verified the OTP first.
     * Never throws — transport errors are converted to ERROR responses.
     */
    public function reset_customer_password($phone, $new_password, $dial_code = '91', $local_digits = 10) {
        $variants = function_exists('webshop_phone_digit_variants')
            ? webshop_phone_digit_variants($phone, $dial_code, $local_digits)
            : array(preg_replace('/\D/', '', (string) $phone));
        foreach ($variants as $try_phone) {
            if ($try_phone === '') {
                continue;
            }
            $result = $this->_reset_customer_password_once($try_phone, $new_password);
            if ($result['status'] === 'SUCCESS') {
                return $result;
            }
            $msg = isset($result['msg']) ? strtolower((string) $result['msg']) : '';
            if (strpos($msg, 'not found') === false && strpos($msg, 'no account') === false) {
                return $result;
            }
        }
        return isset($result) ? $result : array('status' => 'ERROR', 'msg' => 'Failed to update password.');
    }

    protected function _reset_customer_password_once($phone, $new_password) {
        if (function_exists('webshop_forgot_password_log')) {
            webshop_forgot_password_log('api_model.reset_customer_password.request', array('phone' => $phone));
        }
        try {
            $res = $this->api->reset_customer_password($phone, $new_password);
        } catch (Exception $e) {
            log_message('error', 'Webshop_api_model::reset_customer_password transport error: ' . $e->getMessage());
            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('api_model.reset_customer_password.exception', array(
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ));
            }
            return ['status' => 'ERROR', 'msg' => 'Service temporarily unavailable. Please try again.'];
        }
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('api_model.reset_customer_password.ok', array('phone' => $phone));
            }
            return ['status' => 'SUCCESS', 'msg' => isset($res->msg) ? (string) $res->msg : 'Password updated.'];
        }
        $apiErr = method_exists($this->api, 'get_last_error') ? $this->api->get_last_error() : null;
        if (function_exists('webshop_forgot_password_log')) {
            webshop_forgot_password_log('api_model.reset_customer_password.fail', array(
                'phone'      => $phone,
                'status'     => $res && isset($res->status) ? (string) $res->status : 'null',
                'msg'        => $res && isset($res->msg) ? (string) $res->msg : null,
                'last_error' => $apiErr,
            ));
        }
        if (!$res && $apiErr) {
            log_message('error', 'Webshop_api_model::reset_customer_password api error: ' . $apiErr);
        }
        return ['status' => 'ERROR', 'msg' => $res && isset($res->msg) ? (string) $res->msg : 'Failed to update password.'];
    }

    /* ================================================================
     * ADDRESSES
     * ================================================================ */

    /**
     * ElintOm returns JSON "addresses" as object or array; json_decode leaves
     * stdClass trees. Views use is_array() — normalize to a PHP array of rows
     * keyed by address id (matches legacy Webshop_model::get_customer_address).
     *
     * @param mixed $raw
     * @return array<int,array<string,mixed>>
     */
    protected function _normalize_customer_address_payload($raw) {
        if ($raw === null || $raw === false) {
            return array();
        }
        if (is_object($raw)) {
            $raw = json_decode(json_encode($raw), true);
        }
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        foreach ($raw as $key => $row) {
            $a = is_object($row) ? (array) $row : $row;
            if (!is_array($a)) {
                continue;
            }
            $id = 0;
            if (isset($a['id'])) {
                $id = (int) $a['id'];
            } elseif (is_int($key) || (is_string($key) && ctype_digit($key))) {
                $id = (int) $key;
            }
            if ($id > 0) {
                $out[$id] = $a;
            } else {
                $out[] = $a;
            }
        }
        return $out;
    }

    public function get_customer_address($customer_id, $address_id = null) {
        $res = $this->api->get_addresses($customer_id, $address_id);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            return $this->_normalize_customer_address_payload(isset($res->addresses) ? $res->addresses : array());
        }
        // Surface auth / transport failures (e.g. "Private key mismatch") instead of silently
        // returning [] — otherwise My Account and checkout show an empty address picker even
        // though the DB has rows, and there is nothing in logs to point at the real cause.
        $this->_log_error('get_addresses');
        return array();
    }

    /**
     * Legacy Webshop_model::set_customer_address — insert row. API mode maps to add_address.
     *
     * @param array $data company_id, address_name, line1, … state_code optional
     * @return int|false New address id
     */
    public function set_customer_address($data) {
        if (!$this->api_mode && $this->has_local_db()) {
            return $this->_fallback_webshop_model()->set_customer_address($data);
        }
        if (!is_array($data)) {
            return false;
        }
        return $this->add_address($data);
    }

    /**
     * @param array    $data Same keys as set_customer_address (without id)
     * @param int|null $id   Address row id
     * @return bool
     */
    public function update_customer_address($data, $id) {
        if (!$this->api_mode && $this->has_local_db()) {
            return $this->_fallback_webshop_model()->update_customer_address($data, $id);
        }
        $aid = (int) $id;
        if ($aid < 1 || !is_array($data)) {
            return false;
        }
        $payload = $data;
        $payload['address_id'] = $aid;
        if (!isset($payload['customer_id']) && isset($payload['company_id'])) {
            $payload['customer_id'] = $payload['company_id'];
        }
        if (!isset($payload['email']) && isset($payload['email_id'])) {
            $payload['email'] = $payload['email_id'];
        }
        $res = $this->api->update_address($payload);
        return ($res && isset($res->status) && $res->status === 'SUCCESS');
    }

    /**
     * @param int $customer_id companies.id
     * @param int $address_id  addresses.id
     * @return bool
     */
    public function set_address_default($customer_id, $address_id) {
        if (!$this->api_mode && $this->has_local_db()) {
            return $this->_fallback_webshop_model()->set_address_default($customer_id, $address_id);
        }
        $cid = (int) $customer_id;
        $aid = (int) $address_id;
        if ($cid < 1 || $aid < 1) {
            return false;
        }
        $res = $this->api->set_address_default(array(
            'customer_id' => $cid,
            'address_id'  => $aid,
        ));
        return ($res && isset($res->status) && $res->status === 'SUCCESS');
    }

    /**
     * @param int      $address_id
     * @param int|null $customer_id When set, ElintOm verifies ownership (recommended).
     * @return bool
     */
    public function delete_address($address_id, $customer_id = null) {
        if (!$this->api_mode && $this->has_local_db()) {
            return $this->_fallback_webshop_model()->delete_address($address_id);
        }
        $aid = (int) $address_id;
        $cid = $customer_id !== null ? (int) $customer_id : 0;
        if ($aid < 1) {
            return false;
        }
        $res = $this->api->delete_address(array(
            'address_id'  => $aid,
            'customer_id' => $cid,
        ));
        return ($res && isset($res->status) && $res->status === 'SUCCESS');
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

    /**
     * Resolves the primary/default address ID for a customer.
     * In API mode, we fetch all addresses and return the one matching the type or the first one.
     */
    public function getAddressDefault($customer_id, $type = 'default') {
        $cid = (int) $customer_id;
        if ($cid < 1) return 0;

        $addresses = $this->get_customer_address($cid);
        if (empty($addresses)) return 0;

        $typeWant = strtolower((string) $type);
        foreach ($addresses as $aid => $addr) {
            $a = (array) $addr;
            if (isset($a['address_type']) && strtolower((string) $a['address_type']) === $typeWant) {
                return (int) $aid;
            }
            if (isset($a['is_default']) && (int) $a['is_default'] === 1) {
                return (int) $aid;
            }
        }

        // Fallback: return the first available address if no specific match is found.
        reset($addresses);
        return (int) key($addresses);
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
     * Build option_id => sellable qty from common API variant shapes (used for cart/checkout stock checks).
     *
     * @param array $raw merged product row
     * @return array<int,float>
     */
    protected function _extract_variant_stock_map(array $raw) {
        $map = array();
        foreach (array('variants', 'product_variants', 'options', 'product_options') as $vk) {
            if (empty($raw[$vk]) || !is_array($raw[$vk])) {
                continue;
            }
            foreach ($raw[$vk] as $idx => $vkrow) {
                $r = is_array($vkrow) ? $vkrow : (is_object($vkrow) ? (array) $vkrow : array());
                $oid = 0;
                foreach (array('id', 'variant_id', 'option_id', 'product_option_id') as $ok) {
                    if (!empty($r[$ok]) && is_numeric($r[$ok])) {
                        $oid = (int) $r[$ok];
                        break;
                    }
                }
                if ($oid < 1 && is_numeric($idx)) {
                    $oid = (int) $idx;
                }
                if ($oid < 1) {
                    continue;
                }
                $q = null;
                foreach (array('quantity', 'qty', 'stock', 'available_qty') as $qk) {
                    if (array_key_exists($qk, $r) && $r[$qk] !== '' && $r[$qk] !== null && is_numeric($r[$qk])) {
                        $q = max(0.0, (float) $r[$qk]);
                        break;
                    }
                }
                if ($q !== null) {
                    $map[$oid] = $q;
                }
            }
        }
        return $map;
    }

    /**
     * Flatten a normalised product array into the flat key names submit_order expects
     * (code, name, sale_unit_id, mrp, tax_id, tax_method, product_type, price, quantity, …).
     *
     * @param  array $a  Normalised product row from the API
     * @return array     Flat product row
     */
    protected function _flatten_product_for_order(array $a) {
        // API normalizer may nest the original data under 'raw' or return it flat.
        $raw = (isset($a['raw']) && is_array($a['raw'])) ? array_merge($a, $a['raw']) : $a;
        $this->load->helper('webshop');
        $parentQty = function_exists('webshop_row_numeric_stock')
            ? webshop_row_numeric_stock($raw)
            : null;
        $variantStock = $this->_extract_variant_stock_map($raw);
        if ($parentQty === null && !empty($variantStock)) {
            $parentQty = (float) array_sum($variantStock);
        }
        $taxRateFlat = null;
        if (isset($raw['tax_rate']) && $raw['tax_rate'] !== '' && is_numeric($raw['tax_rate'])) {
            $taxRateFlat = (float) $raw['tax_rate'];
        } elseif (isset($a['tax_rate']) && $a['tax_rate'] !== '' && is_numeric($a['tax_rate'])) {
            $taxRateFlat = (float) $a['tax_rate'];
        }
        $out = array(
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
        if ($taxRateFlat !== null) {
            $out['tax_rate'] = $taxRateFlat;
        }
        if ($parentQty !== null) {
            $out['quantity'] = max(0.0, (float) $parentQty);
        }
        if (!empty($variantStock)) {
            $out['variant_stock'] = $variantStock;
        }
        return $out;
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
            $ok = $res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS';
            if ($ok) {
                if (isset($res->order_id) && (int) $res->order_id > 0) {
                    return (int) $res->order_id;
                }
                if (isset($res->sale_id) && (int) $res->sale_id > 0) {
                    return (int) $res->sale_id;
                }
                $this->_log_error('add_order');
                return false;
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

    /**
     * WhatsApp order notification — API mode only (no Cheerio calls in this app).
     *
     * HTTP: Elintom_api_client::notify_webshop_order_whatsapp() → ElintOm notifywebshoporderwhatsapp
     * ElintOm resolves billing phone, reads whatsapp_api_key, sends via Whatsapp_model + Cheerio.
     *
     * @param int    $order_id orders.id in ElintOm
     * @param string $flag     'true' = post-checkout placed; 'Ready'|'YES'|'NO' = status templates
     */
    public function notify_order_placed_whatsapp_remote($order_id, $flag = 'true') {
        if (!$this->uses_elintom_api_for_orders()) {
            return null;
        }
        $order_id = (int) $order_id;
        if ($order_id < 1) {
            return null;
        }
        try {
            $res = $this->api->notify_webshop_order_whatsapp($order_id, $flag);
            if ($res === null) {
                $err = method_exists($this->api, 'get_last_error') ? $this->api->get_last_error() : '';
                log_message('error', 'notify_order_placed_whatsapp_remote: API null for order ' . $order_id
                    . ($err !== '' && $err !== null ? ' — ' . $err : ''));
            } elseif (is_object($res) && isset($res->whatsapp_sent) && !$res->whatsapp_sent) {
                $msg = isset($res->msg) ? (string) $res->msg : 'not sent';
                log_message('error', 'notify_order_placed_whatsapp_remote: order ' . $order_id . ' — ' . $msg);
            }
            return $res;
        } catch (Exception $e) {
            log_message('error', 'Webshop_api_model::notify_order_placed_whatsapp_remote: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Best-effort order confirmation email via ElintOm (DB-less / API order mode).
     *
     * @param int $order_id
     * @return mixed null on skip, array on response
     */
    public function notify_order_placed_email_remote($order_id) {
        if (!$this->uses_elintom_api_for_orders()) {
            return null;
        }
        $order_id = (int) $order_id;
        if ($order_id < 1) {
            return null;
        }
        try {
            $res = $this->api->notify_webshop_order_email($order_id);
            if ($res === null) {
                $err = method_exists($this->api, 'get_last_error') ? $this->api->get_last_error() : '';
                log_message('error', 'notify_order_placed_email_remote: API null for order ' . $order_id
                    . ($err !== '' && $err !== null ? ' — ' . $err : ''));
            } elseif (is_object($res) && isset($res->email_sent) && !$res->email_sent) {
                $msg = isset($res->msg) ? (string) $res->msg : 'not sent';
                log_message('error', 'notify_order_placed_email_remote: order ' . $order_id . ' — ' . $msg);
            }
            return $res;
        } catch (Exception $e) {
            log_message('error', 'Webshop_api_model::notify_order_placed_email_remote: ' . $e->getMessage());
            return null;
        }
    }

    /** True when orders/payments must go through ElintOm HTTP API (no local POS DB). */
    public function uses_elintom_api_for_orders() {
        return $this->api_mode || !$this->has_local_db();
    }


    /**
     * Tell ElintOm to record a successful CCAvenue payment (mirrors Webshop_model::CcavenuePayAfterSale there).
     *
     * @return bool
     */
    public function record_ccavenue_payment_remote(array $response_data) {
        $res = $this->api->record_ccavenue_payment($response_data);
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            return true;
        }
        $this->_log_error('record_ccavenue_payment_remote');
        return false;
    }

    /**
     * Cancel an order on ElintOm when payment was aborted or declined at the gateway.
     * Returns true on cancel success (or when the order was already Cancelled).
     */
    public function cancel_order_remote($order_id, $reference_no = '', $reason = '') {
        $oid = (int) $order_id;
        $ref = trim((string) $reference_no);
        if ($oid < 1 && $ref === '') {
            return false;
        }
        $res = $this->api->cancel_order($oid, $ref, (string) $reason);
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            return true;
        }
        $this->_log_error('cancel_order_remote');
        return false;
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
        $raw = trim((string) $order_id);
        if ($raw === '') {
            return null;
        }
        // Non-numeric refs like ES-YYYYMMDD-xxxxxx must not be cast with (int) — that yields 0.
        $cache_key = preg_match('/^ES-/i', $raw) ? $raw : (string) max(0, (int) $raw);
        if ($cache_key === '0') {
            return null;
        }

        if (isset($this->_order_cache[$cache_key])) {
            return $this->_order_cache[$cache_key]['order'];
        }

        $id_num = (int) $raw;
        $ref = null;
        if (preg_match('/^ES-/i', $raw)) {
            $ref = $raw;
            $id_num = 0;
        } elseif ($id_num <= 0) {
            return null;
        }

        $res = $ref !== null ? $this->api->get_order(0, $ref) : $this->api->get_order($id_num);
        $ok = $res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS' && isset($res->order);
        if ($ok) {
            $order_arr = (array) $res->order;
            $items_arr = isset($res->items) ? array_map(function ($i) {
                return (array) $i;
            }, (array) $res->items) : array();
            if (function_exists('webshop_normalize_order_payload')) {
                $normalized = webshop_normalize_order_payload($order_arr, $items_arr);
                $order_arr = $normalized['order'];
                $items_arr = $normalized['items'];
            }
            $this->_order_cache[$cache_key] = array(
                'order' => $order_arr,
                'items' => $items_arr,
            );
            return $this->_order_cache[$cache_key]['order'];
        }
        // Hybrid install: orders may exist only in the local POS DB.
        if ($this->has_local_db()) {
            $local = $this->_fallback_webshop_model()->get_order_by_id($order_id);
            if (!empty($local) && is_array($local)) {
                $this->_order_cache[$cache_key] = array(
                    'order' => $local,
                    'items' => $this->_fallback_webshop_model()->get_order_items_by_order_id($order_id),
                );
                return $local;
            }
        }
        return null;
    }

    public function get_order_items_by_order_id($order_id) {
        $raw = trim((string) $order_id);
        $cache_key = preg_match('/^ES-/i', $raw) ? $raw : (string) max(0, (int) $raw);
        if ($cache_key === '0') {
            return array();
        }
        if (!isset($this->_order_cache[$cache_key])) {
            $this->get_order_by_id($order_id);
        }
        return isset($this->_order_cache[$cache_key]['items']) ? $this->_order_cache[$cache_key]['items'] : array();
    }

    /**
     * Public tracking without login: MD5(id) from WhatsApp or reference_no (ES-…).
     * Logged-in users should use get_order_for_tracking() so they cannot view others' numeric ids.
     */
    public function get_order_for_tracking_public($identifier) {
        $needle = trim((string) $identifier);
        if ($needle === '') {
            return null;
        }

        if (preg_match('/^ES-/i', $needle)) {
            return $this->_tracking_payload_from_api_order($this->api->get_order(0, $needle));
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $needle)) {
            $hash = strtolower($needle);
            if ($this->uses_elintom_api_for_orders()) {
                return $this->_tracking_payload_from_api_order($this->api->get_order_by_track_hash($hash));
            }
            if ($this->has_local_db()) {
                $order = $this->db->query(
                    'SELECT * FROM sma_orders WHERE eshop_sale = 1 AND MD5(id) = ? LIMIT 1',
                    array($hash)
                )->row_array();
                if ($order && !empty($order['id'])) {
                    $oid = (int) $order['id'];
                    return array(
                        'order' => $this->get_order_by_id($oid),
                        'items' => $this->get_order_items_by_order_id($oid),
                    );
                }
            }
        }

        return null;
    }

    /**
     * @param object|null $res ElintOm getorder / getorderbytrackhash response
     * @return array|null
     */
    protected function _tracking_payload_from_api_order($res) {
        if (!$res || !isset($res->status) || strtoupper((string) $res->status) !== 'SUCCESS' || empty($res->order)) {
            return null;
        }
        $order_arr = (array) $res->order;
        $items_arr = isset($res->items) ? array_map(function ($i) {
            return (array) $i;
        }, (array) $res->items) : array();
        if (function_exists('webshop_normalize_order_payload')) {
            $normalized = webshop_normalize_order_payload($order_arr, $items_arr);
            $order_arr = $normalized['order'];
            $items_arr = $normalized['items'];
        }
        return array('order' => $order_arr, 'items' => $items_arr);
    }

    /**
     * Resolve a tracking identifier (numeric id, reference_no, or md5(id)) to an order +
     * its line items for the /webshop/track_order page. Constrained to the logged-in
     * customer so users can only track their own orders.
     *
     * @param string $identifier  Raw URL segment: order id, reference_no, or MD5(id).
     * @param int    $customer_id companies.id from the webshop session.
     * @return array|null         ['order' => array, 'items' => array] or null if not found.
     */
    public function get_order_for_tracking($identifier, $customer_id = 0) {
        $needle = trim((string) $identifier);
        $cid = (int) $customer_id;
        if ($needle === '' || $cid < 1) {
            return null;
        }

        $sales = $this->get_customer_sales($cid);
        if (!is_array($sales) || empty($sales)) {
            return null;
        }

        $needle_lc = strtolower($needle);
        $needle_int = ctype_digit($needle) ? (int) $needle : 0;

        foreach ($sales as $s) {
            $a = is_object($s) ? (array) $s : (array) $s;
            $oid = isset($a['id']) ? (int) $a['id'] : 0;
            if ($oid < 1) {
                continue;
            }
            $ref = isset($a['reference_no']) ? strtolower((string) $a['reference_no']) : '';
            $match = ($needle_int > 0 && $oid === $needle_int)
                || ($ref !== '' && $ref === $needle_lc)
                || (md5((string) $oid) === $needle_lc);

            if ($match) {
                return array(
                    'order' => $a,
                    'items' => $this->get_order_items_by_order_id($oid),
                );
            }
        }
        return null;
    }

    /**
     * Legacy Webshop_model::get_customer_orders() contract for account + order_details.
     * DB-less / API-primary: lists from ElintOm getcustomersales (orders table on backend).
     *
     * @param int         $customer_id companies.id / session user_id
     * @param string|null $order_id      When set, MD5(orders.id) (legacy) or numeric id
     * @return array|false
     */
    public function get_customer_orders($customer_id, $order_id = null) {
        if (!$this->api_mode && $this->has_local_db()) {
            return $this->_fallback_webshop_model()->get_customer_orders($customer_id, $order_id);
        }

        $cid = (int) $customer_id;
        if ($cid < 1) {
            return false;
        }

        $sale_status = '';
        $sales = $this->get_customer_sales($cid, $sale_status);
        if (!is_array($sales)) {
            $sales = array();
        }

        $needle = $order_id !== null && $order_id !== '' ? strtolower(trim((string) $order_id)) : '';
        $match_numeric = ($needle !== '' && ctype_digit($needle)) ? (int) $needle : 0;

        if ($needle === '') {
            $orders = array();
            foreach ($sales as $s) {
                $row = $this->_coerce_api_sale_to_order_list_row($s);
                if ($row !== null) {
                    $orders[] = $row;
                }
            }
            return array('orders' => $orders);
        }

        $matched_id = 0;
        foreach ($sales as $s) {
            $a = is_object($s) ? (array) $s : (array) $s;
            $oid = isset($a['id']) ? (int) $a['id'] : 0;
            if ($oid < 1) {
                continue;
            }
            if ($match_numeric > 0 && $oid === $match_numeric) {
                $matched_id = $oid;
                break;
            }
            if (md5((string) $oid) === $needle) {
                $matched_id = $oid;
                break;
            }
        }

        if ($matched_id < 1) {
            return false;
        }

        $order_arr = $this->get_order_by_id($matched_id);
        if (!is_array($order_arr) || $order_arr === array()) {
            return false;
        }

        $items_arr = $this->get_order_items_by_order_id($matched_id);
        $order_obj = (object) $order_arr;
        $items_by_oid = array();
        foreach ($items_arr as $it) {
            $it_obj = is_object($it) ? $it : (object) $it;
            $items_by_oid[$matched_id][] = $it_obj;
        }

        return array(
            'orders'   => array($order_obj),
            'items'    => $items_by_oid,
            'payments' => array(),
        );
    }

    /**
     * @param mixed $sale row from get_customer_sales (object or array)
     * @return object|null stdClass compatible with my_account / legacy views
     */
    protected function _coerce_api_sale_to_order_list_row($sale) {
        $a = is_object($sale) ? (array) $sale : (array) $sale;
        $id = isset($a['id']) ? (int) $a['id'] : 0;
        if ($id < 1) {
            return null;
        }
        $row = new \stdClass();
        $row->order_id = isset($a['order_id']) ? (int) $a['order_id'] : $id;
        $row->id = $id;
        $row->reference_no = isset($a['reference_no']) ? (string) $a['reference_no'] : '';
        $row->date = isset($a['date']) ? (string) $a['date'] : '';
        $row->sale_status = isset($a['sale_status']) ? (string) $a['sale_status'] : 'pending';
        $row->grand_total = isset($a['grand_total']) ? (float) $a['grand_total'] : 0.0;
        $row->payment_status = isset($a['payment_status']) ? (string) $a['payment_status'] : '';
        $row->total = isset($a['total']) ? (float) $a['total'] : 0.0;
        return $row;
    }

    public function get_customer_sales($customer_id, $sale_status = '') {
        $res = $this->api->get_customer_sales($customer_id, $sale_status);
        if ($res && isset($res->status) && $res->status === 'SUCCESS') {
            if (!isset($res->sales) || $res->sales === null) {
                return array();
            }
            return is_array($res->sales) ? $res->sales : (array) $res->sales;
        }
        return [];
    }

    /* ================================================================
     * COUPONS
     * ================================================================ */

    public function apply_coupon($code, $cart_total = 0) {
        $res = $this->api->apply_coupon($code, $cart_total);
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            if (!isset($res->coupon)) {
                return [];
            }
            $c = $res->coupon;
            if (is_array($c)) {
                return $c;
            }
            return json_decode(json_encode($c), true);
        }
        $this->_log_error('apply_coupon');
        return false;
    }

    /* ================================================================
     * WISHLIST
     * ================================================================ */

    public function get_wishlist($user_id) {
        $res = $this->api->get_wishlist($user_id);
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS' && isset($res->wishlist)) {
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
        if (!$user_id) {
            return 0;
        }
        if (function_exists('webshop_wishlist_normalize_rows')) {
            $norm = webshop_wishlist_normalize_rows($this->get_wishlist($user_id));
            return (int) $norm['count'];
        }
        if ($this->api_mode || !$this->has_local_db()) {
            $res = $this->api->get_wishlist($user_id);
            if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
                if (isset($res->count)) {
                    return (int) $res->count;
                }
                if (isset($res->wishlist)) {
                    $list = is_array($res->wishlist) ? $res->wishlist : (array) $res->wishlist;
                    return count($list);
                }
            }
            return count($this->get_wishlist($user_id));
        }
        return $this->_fallback_webshop_model()->get_wishlist_count($user_id);
    }

    public function add_to_wishlist($user_id, $product_id, $option_id = null) {
        $res = $this->api->add_wishlist($user_id, $product_id, $option_id);
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            return true;
        }
        $this->_log_error('add_to_wishlist');
        if ($res && isset($res->msg)) {
            log_message('error', 'Webshop_api_model::add_to_wishlist API: ' . (string) $res->msg);
        }
        return false;
    }

    public function remove_from_wishlist($user_id, $product_id, $option_id = null) {
        $res = $this->api->remove_wishlist($user_id, $product_id, $option_id);
        if ($res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS') {
            return true;
        }
        $this->_log_error('remove_from_wishlist');
        if ($res && isset($res->msg)) {
            log_message('error', 'Webshop_api_model::remove_from_wishlist API: ' . (string) $res->msg);
        }
        return false;
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
