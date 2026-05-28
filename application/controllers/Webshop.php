<?php
defined('BASEPATH') OR exit('No direct script access allowed');

#[\AllowDynamicProperties]
class Webshop extends MY_Controller
{

    public $assets;
    public $viewpath;
    public $data;
    public $webshop_settings;
    public $is_admin_login;
    private $dynamicRenderProbe = false;
    private $themePageSeoStore = APPPATH . 'cache/theme_page_seo.json';
    private $seoExtendedStore = APPPATH . 'cache/seo_extended_settings.json';

    /**
     * Minimal storefront bootstrap: skip heavy catalog/CMS/cart API prep for standalone pages.
     * Improves TTFB/LCP on order success, payment declined/cancel, and service_off.
     *
     * @var bool
     */
    private $webshop_lightweight_bootstrap = false;

    public function __construct()
    {
        parent::__construct();

        $this->load->library('sma');

        // $this->load->model('site'); // Disabled in DB-less mode

        $this->load->model('webshop_api_model');
        $this->webshop_model = $this->webshop_api_model;
        $this->load->model('webshop_dynamic_model');
        $this->load->library('webshop_render_engine');
        $this->load->library('webshop_section_engine');
        $this->load->library('webshop_action_engine');

        $this->load->helper('webshop_helper');

        $this->webshop_lightweight_bootstrap = in_array((string) $this->uri->segment(2), array(
            'cart',
            'order_success',
            'payment_declined',
            'payment_cancel',
            'service_off',
            'search_suggest',
            'get_cart_json',
            'mini_cart',
            'webshop_request',
        ), true);

        $this->webshop_catalog_bootstrap = in_array((string) $this->uri->segment(2), array(
            'category_products',
            'product_details',
            'product_reviews',
            'search_products',
        ), true);

        $mediaBase = $this->webshop_api_model->get_media_uploads_base();
        $this->data['uploads'] = $mediaBase;
        $this->data['thumbs'] = $mediaBase . 'thumbs/';
        $this->data['images'] = $mediaBase . 'images/';

        $this->config->load('elintom_api', true);
        $storefrontAssetsDir = trim((string) $this->config->item('elintom_theme_assets_directory', 'elintom_api'));
        $webshopThemeKey = (isset($this->webshop_settings) && is_object($this->webshop_settings) && isset($this->webshop_settings->webshop_theme))
            ? trim((string) $this->webshop_settings->webshop_theme)
            : '';
        if ($storefrontAssetsDir !== '' || in_array($webshopThemeKey, array('gulfpharmacy', 'nw'), true)) {
            $this->data['assets'] = rtrim(base_url('assets/webshop/'), '/') . '/';
        } elseif (function_exists('webshop_theme_assets_base_url')) {
            $this->data['assets'] = webshop_theme_assets_base_url();
        } elseif (!isset($this->data['assets']) || (string) $this->data['assets'] === '') {
            $this->data['assets'] = base_url('assets/webshop/');
        }
        $this->assets = $this->data['assets'];
        if (function_exists('webshop_theme_assets_directory_name')) {
            $this->data['Assets_directory_name'] = webshop_theme_assets_directory_name();
        }

        $this->data['is_admin_login'] = $this->is_admin_login = ($this->loggedIn && ($this->Owner || $this->Admin)) ? true : false;

        $this->active_webshop = $this->webshop_api_model->get_active_webshop_flag();
       
        if (!$this->active_webshop && $this->uri->segment(2) != 'service_off') {
            redirect('webshop/service_off');
        }
       
        if ($this->webshop_lightweight_bootstrap && isset($this->webshop_settings) && is_object($this->webshop_settings)) {
            $this->data['webshop_settings'] = $this->webshop_settings;
        } else {
            $this->data['webshop_settings'] = $this->webshop_settings = $this->webshop_model->get_webshop_settings();
        }

        $this->data['home_page'] = $this->webshop_settings->home_page;

        $this->data['theme_color'] = !empty($this->webshop_settings->theme_color) ? $this->webshop_settings->theme_color : 'orange';

        $this->data['strip_color'] = !empty($this->webshop_settings->header_strip_style) ? $this->webshop_settings->header_strip_style : 1;

        if (!$this->webshop_lightweight_bootstrap) {
            $this->data['categories'] = $this->webshop_model->get_categories();
            if (!is_array($this->data['categories'])) {
                $this->data['categories'] = ['main' => []];
            }
            $this->data['main_categories'] = isset($this->data['categories']['main']) && is_array($this->data['categories']['main']) ? $this->data['categories']['main'] : [];

            if (!$this->webshop_catalog_bootstrap) {
                $this->data['webshop_pos_settings'] = $this->webshop_model->get_webshop_pos_settings();
            } else {
                $this->data['webshop_pos_settings'] = new stdClass();
            }

            $catidParam = $this->input->get('catid');
            $category_id = ($catidParam !== null && $catidParam !== '')
                ? $catidParam
                : (($this->uri->segment(2) == 'category_products' && !empty($this->uri->segment(3))) ? $this->uri->segment(3) : null);

            if ($this->webshop_catalog_bootstrap && $this->uri->segment(2) === 'category_products' && !empty($this->uri->segment(3))) {
                $this->data['category_brands'] = $this->webshop_model->get_category_brands($category_id);
                if (!empty($this->data['category_brands'])) {
                    $this->data['brands_list'] = $this->get_brand_list($this->data['category_brands']);
                }
                $this->data['all_brands'] = array();
            } elseif (!$this->webshop_catalog_bootstrap) {
                $this->data['category_brands'] = $this->webshop_model->get_category_brands($category_id);

                if (!empty($this->data['category_brands'])) {
                    $this->data['brands_list'] = $this->get_brand_list($this->data['category_brands']);
                }

                $this->data['all_brands'] = $this->webshop_model->get_all_brands();
            } else {
                $this->data['category_brands'] = array();
                $this->data['all_brands'] = array();
            }

            $this->data['cart_items'] = [];
            $this->data['cart_data'] = [];
            if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                $this->data['cart_items'] = $_SESSION['cart'];
                $raw_cd = $this->webshop_model->get_cart_data();
                $this->data['cart_data'] = is_array($raw_cd) ? $raw_cd : array();
            }

            $ws_sess = $this->session->userdata('webshop');
            $webshopUserId = null;
            if ($ws_sess) {
                $webshopUserId = is_object($ws_sess) ? (isset($ws_sess->user_id) ? $ws_sess->user_id : null) : (isset($ws_sess['user_id']) ? $ws_sess['user_id'] : null);
            }
            $this->data['wishlist_count'] = $this->webshop_model->get_wishlist_count($webshopUserId);
            $this->_apply_wishlist_lookup_for_user($webshopUserId);

            $this->data['custom_pages'] = $this->webshop_model->getCustomPages();
            $this->data['cms_nav_pages'] = $this->webshop_model->get_cms_nav_pages('header');
            $this->data['cms_footer_nav_pages'] = $this->webshop_model->get_cms_nav_pages('footer');
            $this->data['header_theme_pages'] = $this->get_theme_navigation_pages('header');
            $this->data['footer_theme_pages'] = $this->get_theme_navigation_pages('footer');
            $this->data['has_active_blogs'] = $this->has_active_blogs();

            $this->data['restaurant_is_active'] = $this->webshop_model->restaurantWorking();

            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            $setting_map = [];
            if (is_array($this->data['website_setting']) || is_object($this->data['website_setting'])) {
                foreach ($this->data['website_setting'] as $row) {
                    $setting_map[$row->fields] = $row->value;
                }
            }
            $this->data['setting_map'] = $setting_map;

        } else {
            $this->data['categories'] = ['main' => []];
            $this->data['main_categories'] = [];
            $this->data['webshop_pos_settings'] = new stdClass();
            $this->data['category_brands'] = [];
            $this->data['all_brands'] = [];
            $this->data['cart_items'] = [];
            $this->data['cart_data'] = [];
            if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                $this->data['cart_items'] = $_SESSION['cart'];
            }
            $ws_sess = $this->session->userdata('webshop');
            $webshopUserId = null;
            if ($ws_sess) {
                $webshopUserId = is_object($ws_sess) ? (isset($ws_sess->user_id) ? $ws_sess->user_id : null) : (isset($ws_sess['user_id']) ? $ws_sess['user_id'] : null);
            }
            $this->data['wishlist_count'] = $this->webshop_model->get_wishlist_count($webshopUserId);
            $this->_apply_wishlist_lookup_for_user($webshopUserId);
            $this->data['custom_pages'] = [];
            // Header/sidebar nav (CMS pages) — still required on cart/checkout-light pages.
            $this->data['cms_nav_pages'] = $this->webshop_model->get_cms_nav_pages('header');
            $this->data['cms_footer_nav_pages'] = $this->webshop_model->get_cms_nav_pages('footer');
            if (!is_array($this->data['cms_nav_pages'])) {
                $this->data['cms_nav_pages'] = [];
            }
            if (!is_array($this->data['cms_footer_nav_pages'])) {
                $this->data['cms_footer_nav_pages'] = [];
            }
            $this->data['header_theme_pages'] = [];
            $this->data['footer_theme_pages'] = [];
            $this->data['has_active_blogs'] = false;
            $this->data['restaurant_is_active'] = false;
            $this->data['website_setting'] = [];
            $this->data['setting_map'] = [];
        }

        $this->dynamicRenderProbe = (bool) $this->config->item('webshop_dynamic_render_probe', 'elintom_api');
        $this->data['api_website_setting_sections'] = $this->api_website_setting_sections;
        // $this->data['custom_pages'] = $this->webshop_model->get_custom_pages();
    }

    public function service_off()
    {
        $this->load_view("service_off", $this->data);
    }

    public function sitemap()
    {
        $urls = $this->get_sitemap_urls();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . rtrim(base_url(), '/') . '/sitemap.xsl"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $item) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($item['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= '    <lastmod>' . htmlspecialchars($item['lastmod'], ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
            $xml .= '    <changefreq>' . htmlspecialchars($item['changefreq'], ENT_QUOTES, 'UTF-8') . "</changefreq>\n";
            $xml .= '    <priority>' . htmlspecialchars($item['priority'], ENT_QUOTES, 'UTF-8') . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        $this->send_xml_response_and_exit($xml);
    }

    public function sitemap_index()
    {
        $today = date('Y-m-d');
        $base = rtrim(base_url(), '/');
        $sitemaps = [
            $base . '/sitemap-pages.xml',
            $base . '/sitemap-categories.xml',
            $base . '/sitemap-products.xml',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . rtrim(base_url(), '/') . '/sitemap.xsl"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($sitemaps as $loc) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= '    <lastmod>' . $today . "</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }
        $xml .= '</sitemapindex>';

        $this->send_xml_response_and_exit($xml);
    }

    public function sitemap_pages()
    {
        $this->render_sitemap_xml($this->get_sitemap_page_urls());
    }

    public function sitemap_categories()
    {
        $this->render_sitemap_xml($this->get_sitemap_category_urls());
    }

    public function sitemap_products()
    {
        $this->render_sitemap_xml($this->get_sitemap_product_urls());
    }

    public function sitemap_xsl()
    {
        $xsl = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9">
    <xsl:output method="html" encoding="UTF-8" indent="yes"/>
    <xsl:template match="/">
        <html>
        <head>
            <title>Sitemap</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #111827; }
                h1 { margin-bottom: 8px; }
                .meta { color: #6b7280; margin-bottom: 18px; }
                table { border-collapse: collapse; width: 100%; font-size: 14px; }
                th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
                th { background: #f9fafb; }
                a { color: #2563eb; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <h1>XML Sitemap</h1>
            <div class="meta">Generated for search engines and SEO audits.</div>

            <xsl:choose>
                <xsl:when test="sm:sitemapindex">
                    <table>
                        <tr><th>Sitemap</th><th>Last Modified</th></tr>
                        <xsl:for-each select="sm:sitemapindex/sm:sitemap">
                            <tr>
                                <td><a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a></td>
                                <td><xsl:value-of select="sm:lastmod"/></td>
                            </tr>
                        </xsl:for-each>
                    </table>
                </xsl:when>
                <xsl:otherwise>
                    <table>
                        <tr><th>URL</th><th>Last Modified</th><th>Changefreq</th><th>Priority</th></tr>
                        <xsl:for-each select="sm:urlset/sm:url">
                            <tr>
                                <td><a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a></td>
                                <td><xsl:value-of select="sm:lastmod"/></td>
                                <td><xsl:value-of select="sm:changefreq"/></td>
                                <td><xsl:value-of select="sm:priority"/></td>
                            </tr>
                        </xsl:for-each>
                    </table>
                </xsl:otherwise>
            </xsl:choose>
        </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
XSL;

        $this->send_xml_response_and_exit($xsl);
    }

    public function robots()
    {
        $sitemapUrl = rtrim(base_url(), '/') . '/sitemap-index.xml';
        $host = parse_url(base_url(), PHP_URL_HOST);
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /logout',
            'Disallow: /register',
            'Disallow: /forgot_password',
            'Disallow: /webshop/login',
            'Disallow: /webshop/logout',
            'Disallow: /webshop/register',
            'Disallow: /webshop/forgot_password',
            'Disallow: /webshop/cart',
            'Disallow: /webshop/checkout',
            'Disallow: /webshop/your_account',
            'Disallow: /webshop/your_orders',
            'Disallow: /webshop/your_tracking',
            'Disallow: /webshop/webshop_request',
            'Allow: /assets/',
            'Allow: /themes/',
            'Sitemap: ' . $sitemapUrl,
        ];
        if (!empty($host)) {
            $lines[] = 'Host: ' . $host;
        }

        $this->send_text_response_and_exit(implode("\n", $lines) . "\n");
    }

    private function render_sitemap_xml($urls)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . rtrim(base_url(), '/') . '/sitemap.xsl"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($urls as $item) {
            $loc = htmlspecialchars($item['loc'], ENT_QUOTES, 'UTF-8');
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $loc . "</loc>\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="en" href="' . $loc . "\" />\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $loc . "\" />\n";
            $xml .= '    <lastmod>' . htmlspecialchars($item['lastmod'], ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
            $xml .= '    <changefreq>' . htmlspecialchars($item['changefreq'], ENT_QUOTES, 'UTF-8') . "</changefreq>\n";
            $xml .= '    <priority>' . htmlspecialchars($item['priority'], ENT_QUOTES, 'UTF-8') . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        $this->send_xml_response_and_exit($xml);
    }

    private function send_xml_response_and_exit($xml)
    {
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
        exit;
    }

    private function send_text_response_and_exit($text)
    {
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/plain; charset=UTF-8');
        echo $text;
        exit;
    }

    /**
     * Resolve storefront view from canonical webshop view tree.
     */
    private function resolve_webshop_view_path($method)
    {
        $method = function_exists('webshop_normalize_webshop_view_method')
            ? webshop_normalize_webshop_view_method($method)
            : trim((string) $method, '/');
        if ($method === '') {
            return 'webshop/index';
        }

        $theme = (isset($this->webshop_settings) && is_object($this->webshop_settings) && isset($this->webshop_settings->webshop_theme))
            ? (string) $this->webshop_settings->webshop_theme : '';

        $theme_folders = array(
            'nw'           => 'webshop/nw_theme',
            'gulfpharmacy' => 'webshop/gulfpharmacy_theme',
            'restaurant'   => 'webshop/webshop_restaurant_t1',
        );

        // 1. Prefer plane_vanila_theme for shared storefront pages
        // (login/register/cart/checkout/product pages) so component flow stays consistent.
        $planeVanilaPath = $this->resolve_plane_vanila_view_path($method);
        if ($planeVanilaPath !== '') {
            return $planeVanilaPath;
        }

        $this->config->load('elintom_api', true);
        $switchViewFolder = trim((string) $this->config->item('elintom_theme_view_folder', 'elintom_api'));
        $preferPlaneVanila = ($switchViewFolder !== '')
            || in_array($theme, array('gulfpharmacy', 'nw', 'herbinnwellness'), true);

        // 2. Legacy webshop/{theme}_theme/*.php — skip when host profile uses plane_vanila_theme (CMS storefront).
        if (!$preferPlaneVanila && $theme !== '' && isset($theme_folders[$theme])) {
            $folder = $theme_folders[$theme];
            $candidate = $folder . '/' . $method . '.php';
            if (is_file(VIEWPATH . $candidate)) {
                return $folder . '/' . $method;
            }
        }

        // 3. Fallback to default webshop/ directory
        $path = VIEWPATH . 'webshop/' . $method . '.php';
        if (is_file($path)) {
            return 'webshop/' . $method;
        }

        // 4. Active plane_vanila theme components (folder from elintom_api_switch)
        if (function_exists('webshop_plane_vanila_view')) {
            $theme_comp = webshop_plane_vanila_view('components/' . $method);
            if (is_file(VIEWPATH . $theme_comp . '.php')) {
                return $theme_comp;
            }
        }

        // 6. Fallback to default webshop/components/ (if it existed)
        $compPath = VIEWPATH . 'webshop/components/' . $method . '.php';
        if (is_file($compPath)) {
            return 'webshop/components/' . $method;
        }

        return 'webshop/' . $method;
    }

    /**
     * Prefer plane_vanila_theme for storefront landing pages when available.
     * This keeps all controller data mapping intact and only changes the view file source.
     */
    private function resolve_plane_vanila_view_path($method)
    {
        $method = trim((string) $method, '/');
        if ($method === '') {
            return '';
        }

        $themeFolder = function_exists('webshop_plane_vanila_theme_folder')
            ? webshop_plane_vanila_theme_folder()
            : '';

        // 1. Direct path check within plane_vanila_theme
        $directPath = VIEWPATH . 'plane_vanila_theme/' . $method . '.php';
        if (is_file($directPath)) {
            return 'plane_vanila_theme/' . $method;
        }

        // 2. Theme-specific component resolution
        if ($themeFolder !== '') {
            // Handle both "components/foo" and just "foo"
            $baseName = str_replace('components/', '', $method);
            
            // Prefer full page shells (pages/foo) over naked components so routes like
            // wishlist get header/footer from theme_loader (page_open/page_close).
            $candidates = [
                $themeFolder . '/pages/' . $baseName,
                $themeFolder . '/components/' . $baseName,
                $themeFolder . '/' . $baseName
            ];

            foreach ($candidates as $cand) {
                $path = VIEWPATH . 'plane_vanila_theme/' . $cand . '.php';
                if (is_file($path)) {
                    return 'plane_vanila_theme/' . $cand;
                }
            }
        }

        // 3. Legacy allowed list check
        $allowed = array(
            'category_products',
            'cart',
            'login',
            'register',
            'checkout',
            'payments',
            'order_success',
            'forgot_password',
            'payment_declined',
            'payment_success',
        );
        if (in_array($method, $allowed, true)) {
            $path = VIEWPATH . 'plane_vanila_theme/' . $method . '.php';
            if (is_file($path)) {
                return 'plane_vanila_theme/' . $method;
            }
        }

        return '';
    }

    /**
     * Merge layout globals ($assets, Settings, webshop_settings, cart, …) into a view payload.
     * Payment callbacks must use this or CSS/header partials render unstyled (broken relative URLs / missing $assets).
     *
     * @param array $extra View-specific variables.
     * @return array
     */
    private function theme_view_data(array $extra = array())
    {
        return array_merge($this->data, $extra);
    }

    /**
     * Storefront header logo URL for preload/LCP hints (uploads + API website_setting logo_image).
     *
     * @param array $data View data (may be partial; falls back to $this->data).
     * @return string Absolute or root-relative URL, or empty.
     */
    private function resolve_storefront_header_logo_url_for_preload(array $data)
    {
        if (!function_exists('webshop_resolve_header_logo_url')) {
            return '';
        }
        $uploads = '';
        if (isset($data['uploads']) && (string) $data['uploads'] !== '') {
            $uploads = (string) $data['uploads'];
        } elseif (isset($this->data['uploads'])) {
            $uploads = (string) $this->data['uploads'];
        }
        $ws = null;
        if (isset($data['webshop_settings'])) {
            $ws = $data['webshop_settings'];
        } elseif (isset($this->data['webshop_settings'])) {
            $ws = $this->data['webshop_settings'];
        }

        return webshop_resolve_header_logo_url(
            $uploads,
            isset($this->Settings) ? $this->Settings : null,
            $ws,
            ''
        );
    }

    /**
     * When views do not set gp_header_logo_fetchpriority, default it on so header logo paints sooner (LCP).
     *
     * @param array $data
     * @return array
     */
    private function apply_storefront_logo_lcp_hints(array $data, $method = '')
    {
        if (!is_array($data)) {
            return $data;
        }
        $method_lc = strtolower(trim((string) $method));
        if (in_array($method_lc, array('index', 'cart'), true)) {
            if (!array_key_exists('gp_header_logo_fetchpriority', $data)) {
                $data['gp_header_logo_fetchpriority'] = false;
            }
            return $data;
        }
        $logo = $this->resolve_storefront_header_logo_url_for_preload($data);
        if ($logo !== '' && !array_key_exists('gp_header_logo_fetchpriority', $data)) {
            $data['gp_header_logo_fetchpriority'] = true;
        }

        return $data;
    }

    /**
     * Insert early logo preload in HTML head when missing (category/product shells, etc.).
     * Skips index (hero vs logo handled in the template) and pages that already preload an image.
     *
     * @param string $html
     * @param array  $data
     * @param string $method View stem passed to load_view.
     * @return string
     */
    private function inject_storefront_logo_preload_into_head($html, array $data, $method)
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }
        if (!preg_match('#<head\b#i', $html)) {
            return $html;
        }
        $methodNorm = strtolower(trim((string) $method));
        if ($methodNorm === 'index') {
            return $html;
        }
        $headEnd = stripos($html, '</head>');
        $head = $headEnd !== false ? substr($html, 0, $headEnd) : $html;
        if ((stripos($head, 'rel="preload"') !== false || stripos($head, "rel='preload'") !== false)
            && (stripos($head, 'as="image"') !== false || stripos($head, "as='image'") !== false)) {
            return $html;
        }
        $preloadUrl = '';
        if (!empty($data['page_banner_image_url']) && is_string($data['page_banner_image_url'])) {
            $preloadUrl = trim($data['page_banner_image_url']);
        }
        if ($preloadUrl === '') {
            $preloadUrl = $this->resolve_storefront_header_logo_url_for_preload($data);
        }
        if ($preloadUrl === '') {
            return $html;
        }
        $href = htmlspecialchars($preloadUrl, ENT_QUOTES, 'UTF-8');
        $preconnect = '';
        if (function_exists('webshop_external_origin_preconnect_tag')) {
            $preconnect = webshop_external_origin_preconnect_tag($preloadUrl);
        }
        $snippet = $preconnect . "\n<link rel=\"preload\" as=\"image\" href=\"" . $href . "\" fetchpriority=\"high\">\n";
        if (preg_match('#<head\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $tag = $m[0][0];
            $pos = $m[0][1] + strlen($tag);

            return substr($html, 0, $pos) . $snippet . substr($html, $pos);
        }

        return $html;
    }

    /**
     * Hint the browser to open a connection to the logo host when it differs from the shop origin.
     *
     * @param string $logoUrl
     */
    private function emit_storefront_logo_origin_hint_headers($logoUrl)
    {
        if ($logoUrl === '' || !preg_match('#^https?://#i', $logoUrl)) {
            return;
        }
        $host = (string) parse_url($logoUrl, PHP_URL_HOST);
        if ($host === '') {
            return;
        }
        $reqHost = (string) parse_url(base_url(), PHP_URL_HOST);
        if ($reqHost !== '' && strcasecmp($host, $reqHost) === 0) {
            return;
        }
        $scheme = strtolower((string) parse_url($logoUrl, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            $scheme = 'https';
        }
        $port = parse_url($logoUrl, PHP_URL_PORT);
        $origin = $scheme . '://' . $host . ($port ? ':' . (int) $port : '');
        $this->output->set_header('Link: <' . $origin . '>; rel=preconnect', false);
    }

    /**
     * Minimal HTML when a theme view is missing or rendered empty (avoids blank 200 responses).
     *
     * @param string $title
     * @param string $detail
     * @return string
     */
    private function render_storefront_view_failure_page($title, $detail)
    {
        $this->output->set_status_header(500);
        $safeTitle = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
        $safeDetail = htmlspecialchars((string) $detail, ENT_QUOTES, 'UTF-8');
        $host = isset($_SERVER['HTTP_HOST']) ? htmlspecialchars((string) $_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8') : '';

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $safeTitle . '</title>'
            . '<style>body{font-family:system-ui,sans-serif;margin:40px auto;max-width:640px;line-height:1.6;color:#1f2937}'
            . 'h1{color:#0f766e;font-size:1.35rem}code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.9em}</style></head><body>'
            . '<h1>' . $safeTitle . '</h1><p>' . $safeDetail . '</p>'
            . ($host !== '' ? '<p><small>Host: ' . $host . '</small></p>' : '')
            . '<p><small>Check <code>application/logs/</code> on the server. Nginx/Plesk: ensure <code>application/views/plane_vanila_theme/</code> is deployed and PHP OPcache is cleared.</small></p>'
            . '</body></html>';
    }

    public function load_view($method = '', $data = array())
    {
        $data = $this->resolve_dynamic_runtime_data($method, $data);
        $data = $this->apply_storefront_logo_lcp_hints(is_array($data) ? $data : array(), $method);
        $seoKey = $this->get_theme_page_seo_key($method);
        $data['page_seo'] = $this->get_theme_page_seo($seoKey);
        $this->log_dynamic_render_probe($method, $data);
        // Inactive SEO row must not blank the storefront (theme_page_seo.json on server).
        if (isset($data['page_seo']['is_active']) && (int) $data['page_seo']['is_active'] === 0) {
            $data['page_seo'] = array();
        }
        $logoForHints = $this->resolve_storefront_header_logo_url_for_preload($data);
        $this->emit_storefront_logo_origin_hint_headers($logoForHints);
        $viewPath = $this->resolve_webshop_view_path($method);
        $viewFile = VIEWPATH . str_replace('/', DIRECTORY_SEPARATOR, $viewPath) . '.php';
        if (!is_file($viewFile)) {
            log_message('error', 'Webshop view file missing path=' . $viewPath . ' file=' . $viewFile);
            $this->output->set_output($this->render_storefront_view_failure_page(
                'Theme view file is missing on the server.',
                'Expected: application/views/' . $viewPath . '.php — redeploy the full webshopapi package (including plane_vanila_theme).'
            ));
            return;
        }
        $html = $this->load->view($viewPath, $data, true);
        if (!is_string($html) || trim($html) === '') {
            log_message('error', 'Webshop view rendered empty path=' . $viewPath . ' file=' . $viewFile);
            $this->output->set_output($this->render_storefront_view_failure_page(
                'Homepage rendered empty output.',
                'Check application/logs on the server for PHP errors. Ensure plane_vanila_theme views are deployed and PHP is 8.0+.'
            ));
            return;
        }
        $html = $this->inject_storefront_logo_preload_into_head($html, $data, $method);
        $html = $this->inject_page_seo($html, $data['page_seo'], $data, $method);
        if (!is_string($html) || trim($html) === '') {
            log_message('error', 'Webshop SEO inject returned empty path=' . $viewPath);
            $this->output->set_output($this->render_storefront_view_failure_page(
                'Page HTML was lost during SEO processing.',
                'See application/logs. Try removing or fixing application/cache/theme_page_seo.json on the server.'
            ));
            return;
        }
        $this->output->set_output($html);
    }

    private function resolve_dynamic_runtime_data($method, $data)
    {
        if (!isset($this->webshop_render_engine) || !is_array($data)) {
            return $data;
        }
        try {
            $resolved = $this->webshop_render_engine->resolve_runtime_render($method, $data);
            if (is_array($resolved) && !empty($resolved['applied']) && isset($resolved['data']) && is_array($resolved['data'])) {
                if ($this->dynamicRenderProbe) {
                    $inspection = isset($resolved['inspection']) && is_array($resolved['inspection']) ? $resolved['inspection'] : array();
                    $slug = isset($inspection['slug']) ? (string) $inspection['slug'] : '';
                    $sectionCount = isset($inspection['sections']) && is_array($inspection['sections']) ? count($inspection['sections']) : 0;
                    $sectionTypes = (isset($inspection['section_summary']['types']) && is_array($inspection['section_summary']['types']))
                        ? json_encode($inspection['section_summary']['types']) : '{}';
                    log_message(
                        'debug',
                        'DynamicRuntimeResolver: applied method=' . trim((string) $method, '/')
                        . ' slug=' . $slug
                        . ' sections=' . $sectionCount
                        . ' section_types=' . $sectionTypes
                    );
                }
                return $resolved['data'];
            }
            if ($this->dynamicRenderProbe && is_array($resolved)) {
                $inspection = isset($resolved['inspection']) && is_array($resolved['inspection']) ? $resolved['inspection'] : array();
                $slug = isset($inspection['slug']) ? (string) $inspection['slug'] : '';
                log_message(
                    'debug',
                    'DynamicRuntimeResolver: skipped reason=' . (isset($resolved['reason']) ? (string) $resolved['reason'] : 'unknown')
                    . ' method=' . trim((string) $method, '/')
                    . ' slug=' . $slug
                );
            }
        } catch (Exception $e) {
            log_message('error', 'DynamicRuntimeResolver error: ' . $e->getMessage());
        }
        return $data;
    }

    private function log_dynamic_render_probe($method, $data)
    {
        if (!$this->dynamicRenderProbe || !isset($this->webshop_render_engine)) {
            return;
        }

        try {
            $inspection = $this->webshop_render_engine->inspect_page_render_context($method, $data);
            log_message(
                'debug',
                'DynamicRenderProbe: slug=' . (isset($inspection['slug']) ? $inspection['slug'] : '')
                . ' theme=' . (isset($inspection['theme']) ? $inspection['theme'] : '')
                . ' enabled=' . (isset($inspection['enabled']) && $inspection['enabled'] ? '1' : '0')
                . ' sections=' . (isset($inspection['sections']) && is_array($inspection['sections']) ? count($inspection['sections']) : 0)
                . ' section_types=' . (isset($inspection['section_summary']['types']) && is_array($inspection['section_summary']['types'])
                    ? json_encode($inspection['section_summary']['types']) : '{}')
            );
        } catch (Exception $e) {
            log_message('error', 'DynamicRenderProbe error: ' . $e->getMessage());
        }
    }

    /**
     * Safe rating fields for views (handles null / array API shapes and list-row fallbacks).
     *
     * @param int|string $productId
     * @param array      $rowFallback Optional product row with ratings_avarage / ratings_count
     * @return array     [ ratings_average_for_display, ratings_count ]
     */
    private function resolve_product_rating_fields($productId, array $rowFallback = array())
    {
        if ($rowFallback !== array()) {
            $avgFromRow = null;
            $cntFromRow = null;
            if (isset($rowFallback['ratings_avarage']) && $rowFallback['ratings_avarage'] !== '' && $rowFallback['ratings_avarage'] !== null) {
                $avgFromRow = (float) $rowFallback['ratings_avarage'];
            } elseif (isset($rowFallback['ratings_average'])) {
                $avgFromRow = (float) $rowFallback['ratings_average'];
            }
            if (isset($rowFallback['ratings_count'])) {
                $cntFromRow = (int) $rowFallback['ratings_count'];
            }
            if ($avgFromRow !== null || ($cntFromRow !== null && $cntFromRow > 0)) {
                return array(
                    $avgFromRow !== null ? $avgFromRow : 0.0,
                    $cntFromRow !== null ? $cntFromRow : 0,
                );
            }
        }

        if ($this->uses_elintom_catalog_api()) {
            return array(0.0, 0);
        }

        $ratingInfo = $this->webshop_model->get_product_rating($productId);
        $avg = 0.0;
        $cnt = 0;
        if (is_object($ratingInfo)) {
            $avg = isset($ratingInfo->average) ? (float) $ratingInfo->average : 0.0;
            $cnt = isset($ratingInfo->count) ? (int) $ratingInfo->count : 0;
        } elseif (is_array($ratingInfo)) {
            $avg = isset($ratingInfo['average']) ? (float) $ratingInfo['average'] : 0.0;
            $cnt = isset($ratingInfo['count']) ? (int) $ratingInfo['count'] : 0;
        }
        if ($avg == 0.0 && $cnt === 0 && $rowFallback !== array()) {
            if (isset($rowFallback['ratings_avarage']) && $rowFallback['ratings_avarage'] !== '' && $rowFallback['ratings_avarage'] !== null) {
                $avg = (float) $rowFallback['ratings_avarage'];
            }
            if (isset($rowFallback['ratings_count'])) {
                $cnt = (int) $rowFallback['ratings_count'];
            }
        }
        return array($avg, $cnt);
    }

    /**
     * True when catalogue is served via ElintOm HTTP API (remote round-trips dominate TTFB).
     *
     * @return bool
     */
    private function uses_elintom_catalog_api()
    {
        return method_exists($this->webshop_model, 'uses_elintom_catalog_api')
            && $this->webshop_model->uses_elintom_catalog_api();
    }

    public function _remap($method, $params = [])
    {
        // Real storefront actions (cart, your_account, wishlist, …) must run before CMS slug
        // resolution — otherwise /webshop/your_account renders index.php with an empty CMS body.
        if ($method !== '_remap' && method_exists($this, $method) && $this->method_has_enough_uri_params($method, $params)) {
            return call_user_func_array([$this, $method], $params);
        }

        if (empty($params) && $this->render_dynamic_cms_slug_page($method)) {
            return;
        }

        if (empty($params) && $this->render_theme_slug_page($method)) {
            return;
        }

        show_404();
    }

    /**
     * True when the active host profile uses plane_vanila_theme (herbinnwellness, gulfpharmacy, …).
     *
     * @return bool
     */
    private function is_plane_vanila_storefront()
    {
        $this->config->load('elintom_api', true);
        if (trim((string) $this->config->item('elintom_theme_view_folder', 'elintom_api')) !== '') {
            return true;
        }
        $theme = (isset($this->webshop_settings) && is_object($this->webshop_settings) && isset($this->webshop_settings->webshop_theme))
            ? trim((string) $this->webshop_settings->webshop_theme)
            : '';
        return in_array($theme, array('gulfpharmacy', 'nw', 'herbinnwellness'), true);
    }

    /**
     * URI segment count must satisfy required controller method parameters.
     * Prevents /webshop/page (legacy custom page route) from calling page() with zero args.
     *
     * @param string $method
     * @param array  $params
     * @return bool
     */
    private function method_has_enough_uri_params($method, $params)
    {
        if (!method_exists($this, $method)) {
            return false;
        }
        try {
            $ref = new ReflectionMethod($this, $method);
        } catch (ReflectionException $e) {
            return !empty($params);
        }

        $required = 0;
        foreach ($ref->getParameters() as $parameter) {
            if (!$parameter->isOptional()) {
                $required++;
            }
        }

        return count($params) >= $required;
    }

    private function render_dynamic_cms_slug_page($method)
    {
        $slug = trim((string) $method);
        if ($slug === '') {
            return false;
        }
        // Never treat a real controller method as a CMS slug (your_account, wishlist, search_suggest, …).
        if ($slug !== '_remap' && method_exists($this, $slug)) {
            return false;
        }
        // Legacy explicit block list (kept for clarity).
        if (in_array($slug, array(
            'index', 'login', 'register', 'cart', 'checkout', 'cms_page',
            'wishlist', 'your_account', 'your_orders', 'your_tracking', 'your_address',
            'logout', 'forgot_password', 'search_products', 'search_suggest', 'track_order',
            'webshop_request', 'get_cart_json', 'mini_cart',
        ), true)) {
            return false;
        }

        return $this->_render_cms_storefront_page($slug);
    }

    private function render_theme_slug_page($method)
    {
        $raw = trim((string) $method, '/');
        if ($raw === '' || in_array($raw, ['index', 'login', 'register', 'cart', 'checkout'], true)) {
            return false;
        }

        $slugCandidates = array($raw);
        $underscored = str_replace('-', '_', $raw);
        if ($underscored !== $raw) {
            $slugCandidates[] = $underscored;
        }
        $alnumOnly = preg_replace('/[^a-z0-9_]/i', '', $raw);
        if ($alnumOnly !== '' && $alnumOnly !== $raw && $alnumOnly !== $underscored) {
            $slugCandidates[] = $alnumOnly;
        }

        $themeFolder = $this->resolve_theme_folder();
        $fullPath = null;
        $candidate = '';
        foreach ($slugCandidates as $slug) {
            if ($slug === '' || !preg_match('/^[a-z0-9_]+$/i', $slug)) {
                continue;
            }
            $candidate = ($themeFolder ? $themeFolder . '/' : '') . $slug;
            $flatPath = VIEWPATH . 'webshop/' . $candidate . '.php';
            if (is_file($flatPath)) {
                $fullPath = $flatPath;
                break;
            }
        }
        if ($fullPath === null || $candidate === '') {
            return false;
        }
        $seoData = $this->get_theme_page_seo($candidate . '.php');
        if (isset($seoData['is_active']) && (int)$seoData['is_active'] === 0) {
            return false;
        }

        $this->load_view($candidate, $this->data);
        return true;
    }

    private function get_theme_page_seo_key($method)
    {
        $method = trim((string)$method, '/');
        if ($method === '') {
            return 'default/index.php';
        }

        return $method . '.php';
    }

    private function get_sitemap_urls()
    {
        return array_merge(
            $this->get_sitemap_page_urls(),
            $this->get_sitemap_category_urls(),
            $this->get_sitemap_product_urls()
        );
    }

    private function get_sitemap_page_urls()
    {
        $today = date('Y-m-d');
        $urls = [];
        $seen = [];

        $addUrl = function ($path, $changefreq, $priority, $lastmod) use (&$urls, &$seen) {
            $loc = rtrim(base_url(), '/') . '/' . ltrim($path, '/');
            if (isset($seen[$loc])) {
                return;
            }
            $seen[$loc] = true;
            $urls[] = [
                'loc' => $loc,
                'lastmod' => $lastmod ?: date('Y-m-d'),
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };

        // Core indexable pages
        $addUrl('webshop', 'daily', '1.0', $today);
        $addUrl('webshop/about_us', 'monthly', '0.8', $today);
        $addUrl('webshop/contact_us', 'monthly', '0.7', $today);
        // Keep legal pages out of sitemap because some active themes mark them as noindex.

        // Active database custom pages
        $customPages = $this->webshop_model->getCustomPages();
        if (is_array($customPages)) {
            foreach ($customPages as $section => $sectionPages) {
                if (!is_array($sectionPages)) {
                    continue;
                }
                foreach ($sectionPages as $page) {
                    $pageKey = isset($page['page_key']) ? trim((string)$page['page_key']) : '';
                    $isActive = isset($page['is_active']) ? (int)$page['is_active'] : 0;
                    if ($pageKey === '' || $isActive !== 1) {
                        continue;
                    }
                    $lastmod = !empty($page['updated_at']) ? date('Y-m-d', strtotime($page['updated_at'])) : $today;
                    $addUrl('webshop/' . $pageKey, 'weekly', '0.6', $lastmod);
                }
            }
        }

        // Active theme pages managed via admin JSON SEO store
        $themeFolder = $this->resolve_theme_folder();
        $prefix = ($themeFolder ? $themeFolder : 'default') . '/';
        if (is_file($this->themePageSeoStore)) {
            $json = file_get_contents($this->themePageSeoStore);
            $store = json_decode((string)$json, true);
            if (is_array($store)) {
                foreach ($store as $seoKey => $seoData) {
                    if (!is_array($seoData) || strpos($seoKey, $prefix) !== 0) {
                        continue;
                    }
                    $isActive = isset($seoData['is_active']) ? (int)$seoData['is_active'] : 1;
                    if ($isActive !== 1) {
                        continue;
                    }
                    $file = substr($seoKey, strlen($prefix));
                    $slug = preg_replace('/\.php$/i', '', $file);
                    if ($slug === '' || in_array($slug, ['index', 'cart', 'checkout', 'login', 'register'], true)) {
                        continue;
                    }
                    $lastmod = !empty($seoData['updated_at']) ? date('Y-m-d', strtotime($seoData['updated_at'])) : $today;
                    $addUrl('webshop/' . $slug, 'weekly', '0.7', $lastmod);
                }
            }
        }

        return $urls;
    }

    private function get_sitemap_category_urls()
    {
        $today = date('Y-m-d');
        $urls = [];
        $seen = [];
        $addUrl = function ($path, $changefreq, $priority, $lastmod) use (&$urls, &$seen) {
            $loc = rtrim(base_url(), '/') . '/' . ltrim($path, '/');
            if (isset($seen[$loc])) {
                return;
            }
            $seen[$loc] = true;
            $urls[] = [
                'loc' => $loc,
                'lastmod' => $lastmod ?: date('Y-m-d'),
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };

        $categories = $this->webshop_model->get_categories();
        if (is_array($categories) && isset($categories['main']) && is_array($categories['main'])) {
            foreach ($categories['main'] as $category) {
                if (!isset($category->id)) {
                    continue;
                }
                $addUrl('webshop/category_products/' . (int)$category->id, 'weekly', '0.8', $today);
            }
        }

        return $urls;
    }

    private function get_sitemap_product_urls()
    {
        $today = date('Y-m-d');
        $urls = [];
        $seen = [];
        $addUrl = function ($path, $changefreq, $priority, $lastmod) use (&$urls, &$seen) {
            $loc = rtrim(base_url(), '/') . '/' . ltrim($path, '/');
            if (isset($seen[$loc])) {
                return;
            }
            $seen[$loc] = true;
            $urls[] = [
                'loc' => $loc,
                'lastmod' => $lastmod ?: date('Y-m-d'),
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };

        $products = $this->db
            ->select('id, updated_at')
            ->where('in_eshop', 1)
            ->where('is_active', 1)
            ->get('products')
            ->result();

        if (is_array($products)) {
            foreach ($products as $product) {
                if (!isset($product->id)) {
                    continue;
                }
                $hashId = md5((string)$product->id);
                $lastmodRaw = !empty($product->updated_at) ? $product->updated_at : '';
                $lastmod = $lastmodRaw ? date('Y-m-d', strtotime($lastmodRaw)) : $today;
                $addUrl('webshop/product_details/' . $hashId, 'weekly', '0.9', $lastmod);
            }
        }

        return $urls;
    }

    private function resolve_theme_folder()
    {
        if (function_exists('webshop_plane_vanila_theme_folder')) {
            return webshop_plane_vanila_theme_folder();
        }
        $theme = isset($this->webshop_settings->webshop_theme) ? $this->webshop_settings->webshop_theme : '';
        if ($theme === 'restaurant') {
            return 'restaurant';
        }
        if ($theme === 'nw') {
            return 'nw_theme';
        }
        if ($theme === 'gulfpharmacy') {
            return 'gulfpharmacy_theme';
        }
        return '';
    }

    private function get_theme_navigation_pages($placement)
    {
        if (!is_file($this->themePageSeoStore)) {
            return [];
        }

        $json = file_get_contents($this->themePageSeoStore);
        $store = json_decode((string)$json, true);
        if (!is_array($store)) {
            return [];
        }

        $themeFolder = $this->resolve_theme_folder();
        $prefix = ($themeFolder ? $themeFolder : 'default') . '/';
        $pages = [];

        foreach ($store as $seoKey => $seoData) {
            if (!is_array($seoData) || strpos($seoKey, $prefix) !== 0) {
                continue;
            }

            $pagePlacement = isset($seoData['page_placement']) ? (string)$seoData['page_placement'] : 'none';
            $isActive = isset($seoData['is_active']) ? (int)$seoData['is_active'] : 1;
            if ($isActive !== 1) {
                continue;
            }
            if ($placement === 'header' && !in_array($pagePlacement, ['header', 'both'], true)) {
                continue;
            }
            if ($placement === 'footer' && !in_array($pagePlacement, ['footer', 'both'], true)) {
                continue;
            }

            $file = substr($seoKey, strlen($prefix));
            $slug = preg_replace('/\.php$/i', '', $file);
            if ($slug === '' || in_array($slug, ['index', 'about_us', 'contact_us', 'terms_and_conditions', 'privacy_policy'], true)) {
                continue;
            }

            $title = isset($seoData['meta_title']) && trim((string)$seoData['meta_title']) !== ''
                ? trim((string)$seoData['meta_title'])
                : ucwords(str_replace('_', ' ', $slug));

            $pages[] = [
                'slug' => $slug,
                'title' => $title,
            ];
        }

        return $pages;
    }

    private function get_theme_page_seo($seoKey)
    {
        if (!is_file($this->themePageSeoStore)) {
            return [];
        }

        $json = file_get_contents($this->themePageSeoStore);
        if ($json === false || $json === '') {
            return [];
        }

        $store = json_decode($json, true);
        if (!is_array($store)) {
            return [];
        }

        return (isset($store[$seoKey]) && is_array($store[$seoKey])) ? $store[$seoKey] : [];
    }

    private function inject_page_seo($html, $page_seo, array $data = [], $view_method = '')
    {
        if (!is_string($html) || $html === '') {
            return is_string($html) ? $html : '';
        }
        if (!is_array($page_seo)) {
            $page_seo = [];
        }

        $metaTitle = isset($page_seo['meta_title']) ? trim((string)$page_seo['meta_title']) : '';
        $metaDescription = isset($page_seo['meta_description']) ? trim((string)$page_seo['meta_description']) : '';
        $metaKeywords = isset($page_seo['meta_keywords']) ? trim((string)$page_seo['meta_keywords']) : '';
        $extraMetaTags = isset($page_seo['meta_tags']) ? trim((string)$page_seo['meta_tags']) : '';

        $titleUpdated = false;
        if ($metaTitle !== '') {
            $safeTitle = htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8');
            if (preg_match('/<title[^>]*>.*?<\/title>/is', $html)) {
                $replaced = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>' . $safeTitle . '</title>', $html, 1);
                if ($replaced !== null) {
                    $html = $replaced;
                }
                $titleUpdated = true;
            }
        }

        $seoBlock = '';
        if ($metaTitle !== '' && !$titleUpdated) {
            $seoBlock .= "\n<title>" . htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') . "</title>";
        }
        if ($metaDescription !== '') {
            $seoBlock .= "\n<meta name=\"description\" content=\"" . htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') . "\">";
        }
        if ($metaKeywords !== '') {
            $seoBlock .= "\n<meta name=\"keywords\" content=\"" . htmlspecialchars($metaKeywords, ENT_QUOTES, 'UTF-8') . "\">";
        }
        if ($extraMetaTags !== '') {
            $seoBlock .= "\n" . $extraMetaTags;
        }

        if ($seoBlock !== '' && stripos($html, '</head>') !== false) {
            $replaced = preg_replace('/<\/head>/i', $seoBlock . "\n</head>", $html, 1);
            if ($replaced !== null) {
                $html = $replaced;
            }
        }

        $ws = isset($data['webshop_settings']) && is_object($data['webshop_settings'])
            ? $data['webshop_settings']
            : $this->webshop_settings;

        return $this->inject_global_head_metadata($html, $page_seo, $data, (string)$view_method, $ws);
    }

    private function get_seo_extended_defaults()
    {
        return [
            'meta_robots_default' => 'index,follow',
            'hreflang_enabled' => 0,
            'hreflang_codes' => 'en,x-default',
            'geo_region' => '',
            'geo_position' => '',
            'geo_icbm' => '',
            'meta_copyright' => '',
            'meta_theme_color' => '',
            'og_type_default' => 'website',
            'inject_global_schema' => 1,
            'homepage_pharmacy_schema' => '',
            'blog_faq_schema' => '',
            'enable_product_jsonld' => 1,
            'enable_article_jsonld' => 1,
            'enable_rss_link' => 1,
            'rss_feed_title' => 'Blog',
            'ai_entity' => '',
            'ai_summary' => '',
            'ai_category' => '',
            'ai_industry' => '',
            'ai_brand' => '',
            'ai_purpose' => '',
            'ai_keyphrase' => '',
            'ai_context' => '',
        ];
    }

    private function get_seo_extended_settings()
    {
        if (!is_file($this->seoExtendedStore)) {
            return $this->get_seo_extended_defaults();
        }
        $json = file_get_contents($this->seoExtendedStore);
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return $this->get_seo_extended_defaults();
        }
        return array_merge($this->get_seo_extended_defaults(), $decoded);
    }

    private function inject_global_head_metadata($html, array $page_seo, array $data, $view_method, $ws)
    {
        if (!is_string($html) || $html === '') {
            return is_string($html) ? $html : '';
        }
        $ext = $this->get_seo_extended_settings();
        $this->load->helper('url');

        $canonical = $this->build_seo_canonical_url($ws);
        $uploads = isset($data['uploads']) ? rtrim((string)$data['uploads'], '/') . '/' : rtrim(base_url(), '/') . '/';

        $ogTitle = $metaTitle = isset($page_seo['meta_title']) ? trim((string)$page_seo['meta_title']) : '';
        if ($ogTitle === '' && !empty($ws->og_title)) {
            $ogTitle = trim((string)$ws->og_title);
        }
        if ($ogTitle === '' && !empty($ws->meta_title)) {
            $ogTitle = trim((string)$ws->meta_title);
        }
        if ($ogTitle === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $ogTitle = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }

        $ogDesc = isset($page_seo['meta_description']) ? trim((string)$page_seo['meta_description']) : '';
        if ($ogDesc === '' && !empty($ws->og_description)) {
            $ogDesc = trim((string)$ws->og_description);
        }
        if ($ogDesc === '' && !empty($ws->meta_description)) {
            $ogDesc = trim((string)$ws->meta_description);
        }

        $ogType = trim((string)$ext['og_type_default']);
        if (strpos($view_method, 'product_details') !== false) {
            $ogType = 'product';
        } elseif (strpos($view_method, 'blog_detail') !== false || strpos($view_method, '/blogs') !== false) {
            $ogType = 'article';
        }

        $ogImage = '';
        if (!empty($ws->og_image)) {
            $ogImage = trim((string)$ws->og_image);
            if ($ogImage !== '' && !preg_match('#^https?://#i', $ogImage)) {
                $ogImage = base_url(ltrim($ogImage, '/'));
            }
        }
        if ($ogImage === '' && !empty($data['blog']['image'])) {
            $bi = trim((string)$data['blog']['image']);
            if ($bi !== '') {
                $ogImage = preg_match('#^https?://#i', $bi) ? $bi : base_url(ltrim($bi, '/'));
            }
        }
        if ($ogImage === '' && !empty($data['product']['image'])) {
            $ogImage = $uploads . ltrim((string)$data['product']['image'], '/');
        }

        $robots = '';
        if (!empty($page_seo['meta_robots'])) {
            $robots = trim((string)$page_seo['meta_robots']);
        }
        if ($robots === '') {
            $robots = trim((string)$ext['meta_robots_default']);
        }

        // Before stripping og:* from the rendered HTML, salvage any values already placed
        // there by entity/CMS meta_tags so they can serve as fallbacks below.
        if ($ogTitle === '' && preg_match('/<meta\s[^>]*\bproperty\s*=\s*["\']og:title["\'][^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $ogm)) {
            $ogTitle = html_entity_decode(trim($ogm[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($ogTitle === '' && preg_match('/<meta\s[^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*\bproperty\s*=\s*["\']og:title["\'][^>]*>/i', $html, $ogm)) {
            $ogTitle = html_entity_decode(trim($ogm[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($ogDesc === '' && preg_match('/<meta\s[^>]*\bproperty\s*=\s*["\']og:description["\'][^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $ogm)) {
            $ogDesc = html_entity_decode(trim($ogm[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($ogDesc === '' && preg_match('/<meta\s[^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*\bproperty\s*=\s*["\']og:description["\'][^>]*>/i', $html, $ogm)) {
            $ogDesc = html_entity_decode(trim($ogm[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($ogImage === '' && preg_match('/<meta\s[^>]*\bproperty\s*=\s*["\']og:image["\'][^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $ogm)) {
            $ogImage = html_entity_decode(trim($ogm[1]), ENT_QUOTES, 'UTF-8');
        }
        if ($ogImage === '' && preg_match('/<meta\s[^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*\bproperty\s*=\s*["\']og:image["\'][^>]*>/i', $html, $ogm)) {
            $ogImage = html_entity_decode(trim($ogm[1]), ENT_QUOTES, 'UTF-8');
        }

        foreach (array(
            '/<link\s+[^>]*\brel\s*=\s*["\']canonical["\'][^>]*>/i',
            '/<meta\s+[^>]*\bname\s*=\s*["\']robots["\'][^>]*>/i',
            '/<meta\s+[^>]*\bproperty\s*=\s*["\']og:[a-z_:]+["\'][^>]*>/i',
        ) as $pattern) {
            $replaced = preg_replace($pattern, '', $html);
            if ($replaced !== null) {
                $html = $replaced;
            }
        }

        $block = "\n<!--seo:elintom-->\n";
        $block .= '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        if ($robots !== '') {
            $block .= '<meta name="robots" content="' . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($ogType !== '') {
            $block .= '<meta property="og:type" content="' . htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($ogTitle !== '') {
            $block .= '<meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($ogDesc !== '') {
            $block .= '<meta property="og:description" content="' . htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        $block .= '<meta property="og:url" content="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        if ($ogImage !== '') {
            $block .= '<meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        $themeColor = trim((string)$ext['meta_theme_color']);
        if ($themeColor !== '') {
            $block .= '<meta name="theme-color" content="' . htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        if (!empty($ext['hreflang_enabled'])) {
            $codes = preg_split('/\s*,\s*/', (string)$ext['hreflang_codes'], -1, PREG_SPLIT_NO_EMPTY);
            foreach ($codes as $code) {
                $code = trim($code);
                if ($code === '') {
                    continue;
                }
                $block .= '<link rel="alternate" hreflang="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
                    . '" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }

        foreach (['geo_region' => 'geo.region', 'geo_position' => 'geo.position', 'geo_icbm' => 'ICBM'] as $key => $name) {
            $val = trim((string)$ext[$key]);
            if ($val !== '') {
                $block .= '<meta name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" content="'
                    . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }

        $lastMod = $this->resolve_last_modified_http($data);
        if ($lastMod !== '') {
            $block .= '<meta http-equiv="last-modified" content="' . htmlspecialchars($lastMod, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        $copy = trim((string)$ext['meta_copyright']);
        if ($copy !== '') {
            $block .= '<meta name="copyright" content="' . htmlspecialchars($copy, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        $aiMap = [
            'ai_entity' => 'AI-Entity',
            'ai_summary' => 'AI-Summary',
            'ai_category' => 'AI-Category',
            'ai_industry' => 'AI-Industry',
            'ai_brand' => 'AI-Brand',
            'ai_purpose' => 'AI-Purpose',
            'ai_keyphrase' => 'AI-Keyphrase',
            'ai_context' => 'AI-Context',
        ];
        foreach ($aiMap as $ek => $metaName) {
            $val = trim((string)$ext[$ek]);
            if ($val !== '') {
                $block .= '<meta name="' . htmlspecialchars($metaName, ENT_QUOTES, 'UTF-8') . '" content="'
                    . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }
        }

        $isBlogIndex = (bool)preg_match('/(^|\/)blogs$/', str_replace('.php', '', $view_method));
        if (!empty($ext['enable_rss_link']) && (strpos($view_method, 'blog_detail') !== false || $isBlogIndex)) {
            $rssUrl = site_url('blog-rss.xml');
            $rssTitle = trim((string)$ext['rss_feed_title']) ?: 'Blog';
            $block .= '<link rel="alternate" type="application/rss+xml" title="'
                . htmlspecialchars($rssTitle, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($rssUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        if (!empty($ext['inject_global_schema']) && !empty($ws->schema_markup)) {
            $raw = trim((string)$ws->schema_markup);
            if ($raw !== '' && $this->json_ld_is_valid_object($raw)) {
                $block .= '<script type="application/ld+json">' . $raw . '</script>' . "\n";
            }
        }

        $isHome = (bool)preg_match('/(^|\/)index$/', str_replace('.php', '', $view_method));
        if ($isHome && trim((string)$ext['homepage_pharmacy_schema']) !== '') {
            $ph = trim((string)$ext['homepage_pharmacy_schema']);
            if ($this->json_ld_is_valid_object($ph)) {
                $block .= '<script type="application/ld+json">' . $ph . '</script>' . "\n";
            }
        }

        if (!empty($ext['enable_product_jsonld']) && !empty($data['product']) && is_array($data['product'])) {
            $pj = $this->build_product_json_ld($data['product'], $canonical, $uploads);
            if ($pj !== '') {
                $block .= '<script type="application/ld+json">' . $pj . '</script>' . "\n";
            }
        }

        if (!empty($ext['enable_article_jsonld']) && !empty($data['blog']) && is_array($data['blog'])) {
            $aj = $this->build_article_json_ld($data['blog'], $canonical, $ogImage);
            if ($aj !== '') {
                $block .= '<script type="application/ld+json">' . $aj . '</script>' . "\n";
            }
        }

        if (strpos($view_method, 'blog_detail') !== false && trim((string)$ext['blog_faq_schema']) !== '') {
            $fq = trim((string)$ext['blog_faq_schema']);
            if ($this->json_ld_is_valid_object($fq)) {
                $block .= '<script type="application/ld+json">' . $fq . '</script>' . "\n";
            }
        }

        $block .= "<!--/seo:elintom-->\n";

        if (stripos($html, '</head>') !== false) {
            $replaced = preg_replace('/<\/head>/i', $block . '</head>', $html, 1);
            return ($replaced !== null) ? $replaced : $html;
        }
        return $html;
    }

    private function build_seo_canonical_url($ws)
    {
        $this->load->helper('url');
        $uri = trim((string)$this->uri->uri_string(), '/');
        $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        $canonicalBase = (is_object($ws) && isset($ws->canonical_url)) ? trim((string)$ws->canonical_url) : '';
        if ($canonicalBase !== '') {
            $base = rtrim($canonicalBase, '/');
            return $base . ($uri === '' ? '' : '/' . $uri) . $qs;
        }
        return current_url();
    }

    private function resolve_last_modified_http(array $data)
    {
        if (!empty($data['blog']['updated_at'])) {
            $t = strtotime((string)$data['blog']['updated_at']);
            return $t ? gmdate('D, d M Y H:i:s \G\M\T', $t) : '';
        }
        if (!empty($data['product']['updated_at'])) {
            $t = strtotime((string)$data['product']['updated_at']);
            return $t ? gmdate('D, d M Y H:i:s \G\M\T', $t) : '';
        }
        return '';
    }

    private function json_ld_is_valid_object($json)
    {
        $d = json_decode($json, true);
        return is_array($d) && json_last_error() === JSON_ERROR_NONE;
    }

    private function build_product_json_ld(array $product, $canonical, $uploads)
    {
        $name = isset($product['name']) ? (string)$product['name'] : '';
        if ($name === '') {
            return '';
        }
        $sku = isset($product['code']) ? (string)$product['code'] : '';
        $desc = isset($product['product_details']) ? strip_tags((string)$product['product_details']) : '';
        $desc = mb_substr(trim(preg_replace('/\s+/', ' ', $desc)), 0, 5000);
        $price = isset($product['promo_price']) && (float)$product['promo_price'] > 0
            ? (float)$product['promo_price']
            : (isset($product['price']) ? (float)$product['price'] : 0);
        $img = '';
        if (!empty($product['image'])) {
            $img = preg_match('#^https?://#i', (string)$product['image'])
                ? (string)$product['image']
                : rtrim($uploads, '/') . '/' . ltrim((string)$product['image'], '/');
        }
        $brand = isset($product['brand_name']) ? (string)$product['brand_name'] : '';
        $inStock = !isset($product['product_is_active']) || !empty($product['product_is_active']);
        $availability = $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

        $obj = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'sku' => $sku,
            'description' => $desc,
            'url' => $canonical,
        ];
        if ($img !== '') {
            $obj['image'] = [$img];
        }
        if ($brand !== '') {
            $obj['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }
        $currency = $this->get_default_currency_code_for_schema();
        $obj['offers'] = [
            '@type' => 'Offer',
            'url' => $canonical,
            'priceCurrency' => $currency,
            'price' => $price > 0 ? round($price, 2) : 0,
            'availability' => $availability,
        ];
        return json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * ISO currency code from sma_settings.default_currency (settings table + dbprefix).
     */
    private function get_default_currency_code_for_schema()
    {
        $fallback = 'INR';
        $CI = get_instance();
        if (!isset($CI->db) || !is_object($CI->db)) {
            return (isset($this->Settings->default_currency) && trim((string) $this->Settings->default_currency) !== '')
                ? trim((string) $this->Settings->default_currency)
                : $fallback;
        }
        if (!$CI->db->table_exists('settings')) {
            return (isset($this->Settings->default_currency) && trim((string) $this->Settings->default_currency) !== '')
                ? trim((string) $this->Settings->default_currency)
                : $fallback;
        }
        $row = $CI->db->select('default_currency')
            ->where('setting_id', '1')
            ->get('settings', 1)
            ->row();
        if ($row && isset($row->default_currency) && trim((string)$row->default_currency) !== '') {
            return trim((string)$row->default_currency);
        }
        return $fallback;
    }

    private function build_article_json_ld(array $blog, $canonical, $imageUrl)
    {
        $title = isset($blog['title']) ? (string)$blog['title'] : '';
        if ($title === '') {
            return '';
        }
        $strip = isset($blog['content']) ? strip_tags((string)$blog['content']) : '';
        $strip = mb_substr(trim(preg_replace('/\s+/', ' ', $strip)), 0, 5000);
        $obj = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'url' => $canonical,
        ];
        if ($strip !== '') {
            $obj['articleBody'] = $strip;
        }
        if (!empty($blog['updated_at'])) {
            $obj['dateModified'] = date('c', strtotime((string)$blog['updated_at']));
        }
        if ($imageUrl !== '') {
            $obj['image'] = [$imageUrl];
        }
        return json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function webshop_request()
    {
        $postData = $this->input->post(null, true);
        if (!is_array($postData)) {
            $postData = array();
        }
        $action = isset($postData['action']) ? trim((string) $postData['action']) : '';
        if ($action === '') {
            $this->json_response(array(
                'status' => 'FAIL',
                'error' => 'Invalid request action',
            ));
            return;
        }

        switch ($action) {
            case "get_section":

                $this->get_sections($postData);

                break;

            case "get_product_images":

                $this->set_product_gallery($postData);

                break;

            case "add_to_cart":

                $this->add_to_cart($postData);

                break;

            case "buy_now":

                $this->buy_now($postData);

                break;

            case "update_cart":

                $this->update_cart($postData);

                break;

            case "add_to_wishlist":

                $this->add_to_wishlist($postData);

                break;

            case "remove_from_wishlist":

                $this->remove_from_wishlist($postData);

                break;

            case "load_header_cart":

                $this->load_header_cart_items();

                break;

            case "remove_cart_item":

                $this->remove_cart_item($postData);

                break;

            case "mini_cart":

                $this->mini_cart();

                break;

            case "mini_cart_remove":

                $this->mini_cart_remove($postData);

                break;

            case "account_panel_data":

                $this->account_panel_data();
                break;

            case "manage_address_webshop":

                $this->manage_address_webshop($postData);
                break;

            case "profile_update_webshop":

                $this->profile_update_webshop($postData);
                break;

            case "apply_coupon":

                $this->apply_coupon($postData);

                break;

            case "manage_address":

                $this->manage_address($postData);

                break;

            case "manage_eshop_category":
                // Legacy action kept for backward compatibility.
                // The handler no longer exists, so return a clean API error instead of fataling.
                $this->json_response(array(
                    'status' => 'FAIL',
                    'error' => 'manage_eshop_category is not supported',
                ));
                break;

            default:
                $this->json_response(array(
                    'status' => 'FAIL',
                    'error' => 'Unknown request action',
                ));
                break;
        } //end switch.
    }

    public function index()
    {
        if (!$this->active_webshop) {
            $this->load_view("service_off", $this->data);
        } else {
            $this->data['home_page_cms'] = $this->webshop_model->home_page_data();
            $activeTheme = isset($this->webshop_settings->webshop_theme) ? (string) $this->webshop_settings->webshop_theme : '';
            $cmsOnlyHomeTheme = in_array($activeTheme, array('gulfpharmacy', 'nw', 'herbinnwellness'), true)
                || $this->is_plane_vanila_storefront();
            $cmsHomePublished = is_object($this->data['home_page_cms'])
                && !empty($this->data['home_page_cms']->cms_page_found);
            $this->data['cms_home_published'] = $cmsHomePublished;

            $this->data['home_section_html_block'] = '';
            $this->data['home_has_category_grid'] = !$cmsOnlyHomeTheme;
            $this->data['home_has_product_grid'] = !$cmsOnlyHomeTheme;
            $this->data['home_has_header_section'] = true;
            $this->data['home_has_footer_section'] = true;
            $this->data['cms_header_sections_html'] = '';
            $this->data['cms_footer_sections_html'] = '';
            $this->data['page_banner_image_url'] = '';
            $this->data['page_logo_image_url'] = '';
            $this->data['home_category_grid_title'] = '';
            $this->data['home_product_grid_title'] = '';
            $cmsSections = array();

            if ($cmsHomePublished) {
                $this->data['page_title'] = isset($this->data['home_page_cms']->page_title) ? $this->data['home_page_cms']->page_title : '';
                $this->data['meta_tags'] = $this->resolve_cms_page_head_meta($this->data['home_page_cms']);
                $this->data['home_has_header_section'] = isset($this->data['home_page_cms']->show_header) ? (bool) $this->data['home_page_cms']->show_header : true;
                $this->data['home_has_footer_section'] = isset($this->data['home_page_cms']->show_footer) ? (bool) $this->data['home_page_cms']->show_footer : true;
                $this->data['cms_header_sections_html'] = '';
                $this->data['cms_footer_sections_html'] = '';
                $this->data['page_banner_image_url'] = isset($this->data['home_page_cms']->page_banner_image_url) ? (string) $this->data['home_page_cms']->page_banner_image_url : '';
                $this->data['page_logo_image_url'] = isset($this->data['home_page_cms']->page_logo_image_url) ? (string) $this->data['home_page_cms']->page_logo_image_url : '';
                $cmsSections = isset($this->data['home_page_cms']->sections) && is_array($this->data['home_page_cms']->sections)
                    ? $this->data['home_page_cms']->sections : array();
                // CMS plane themes: body comes only from mapped sections (no legacy catalog strips).
                $this->data['home_has_category_grid'] = false;
                $this->data['home_has_product_grid'] = false;
                $this->apply_cms_sections_to_view_data($cmsSections, true);
                $pageBodyHtml = isset($this->data['home_page_cms']->page_text)
                    ? trim((string) $this->data['home_page_cms']->page_text)
                    : '';
                $bodyForRender = $this->filter_body_sections($cmsSections);
                $localBodyHtml = trim($this->webshop_section_engine->render_components($bodyForRender, $this->data));
                $composedHomeHtml = function_exists('webshop_compose_cms_body_html')
                    ? webshop_compose_cms_body_html($pageBodyHtml, $localBodyHtml, $cmsSections)
                    : trim($pageBodyHtml . ($pageBodyHtml !== '' && $localBodyHtml !== '' ? "\n" : '') . $localBodyHtml);
                if ($composedHomeHtml !== '') {
                    $this->data['home_section_html_block'] = $composedHomeHtml;
                }
            } elseif ($cmsOnlyHomeTheme) {
                $this->data['page_title'] = '';
            } elseif (!$cmsOnlyHomeTheme) {
                // Legacy themes without a CMS home still use catalog strips until migrated.
            }
            // Gulf homepage uses CMS sections and custom blocks; skip heavy legacy payload fetches.
            if (!in_array($activeTheme, array('gulfpharmacy', 'herbinnwellness'), true) && !$this->is_plane_vanila_storefront()) {
                $this->data['themeSections'] = $themeSections = $this->webshop_model->get_theme_sections($this->webshop_settings->home_page);
                $this->set_theme_sections_data($themeSections);
                $this->data['sliders'] = $this->webshop_model->get_sliders();
                $this->data['features'] = $this->webshop_model->get_features();
                $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
            } else {
                $this->data['themeSections'] = array();
                $this->data['sliders'] = array();
                $this->data['features'] = array();
                $this->data['recent_viewed'] = array();
            }
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            // Legacy catalog fallback removed for plane_vanila CMS themes — home content is section-driven only.
            $theme = $this->input->get('theme');
            if ($theme) {
                $this->webshop_model->setTheme($theme);
            }

            if ($this->webshop_settings->webshop_theme == 'restaurant') {
                $this->load_view("restaurant/index", $this->data);
            } else if ($this->webshop_settings->webshop_theme == 'nw') {
                $this->load_view("nw_theme/index", $this->data);
            } else if ($this->webshop_settings->webshop_theme == 'gulfpharmacy') {
                $this->load_view("index", $this->data);
            } else {
                $this->load_view("index", $this->data);
            }
        }
    }

    /**
     * Apply CMS sections to theme flags/content in current storefront structure.
     */
    private function apply_cms_sections_to_view_data($sections, $reset_visibility = false)
    {
        if (!is_array($sections)) {
            return;
        }

        $seed = array(
            'home_has_header_section' => isset($this->data['home_has_header_section']) ? (bool) $this->data['home_has_header_section'] : true,
            'home_has_category_grid' => isset($this->data['home_has_category_grid']) ? (bool) $this->data['home_has_category_grid'] : true,
            'home_has_product_grid' => isset($this->data['home_has_product_grid']) ? (bool) $this->data['home_has_product_grid'] : true,
            'home_has_footer_section' => isset($this->data['home_has_footer_section']) ? (bool) $this->data['home_has_footer_section'] : true,
            'home_section_html_block' => isset($this->data['home_section_html_block']) ? (string) $this->data['home_section_html_block'] : '',
            'home_category_grid_title' => isset($this->data['home_category_grid_title']) ? (string) $this->data['home_category_grid_title'] : '',
            'home_product_grid_title' => isset($this->data['home_product_grid_title']) ? (string) $this->data['home_product_grid_title'] : '',
        );
        $patch = $this->webshop_section_engine->map_sections_to_home_patch($sections, (bool) $reset_visibility, $seed);
        if (!is_array($patch)) {
            return;
        }
        foreach ($patch as $k => $v) {
            $this->data[$k] = $v;
        }
    }

    /**
     * plane_vanila_theme (gulfpharmacy / nw): CMS home often ships banner/html only and disables native grids,
     * while home_product_grid_items was never populated. Re-enable default blocks when CMS did not map catalog sections.
     *
     * @param array $cmsSections
     */
    private function ensure_plane_theme_home_catalog_data(array $cmsSections)
    {
        $block = isset($this->data['home_section_html_block']) ? trim((string) $this->data['home_section_html_block']) : '';
        $hasCatSec = $this->cms_section_list_includes_types($cmsSections, array('category_grid', 'category_carousel'));
        $hasProdSec = $this->cms_section_list_includes_types($cmsSections, array('product_grid', 'product_carousel'));
        $cmsCatalogMapped = $hasCatSec || $hasProdSec;
        $cmsCatalogRendered = $block !== '' && (
            strpos($block, 'home-cms-section--category_grid') !== false
            || strpos($block, 'home-cms-section--category_carousel') !== false
            || strpos($block, 'home-cms-section--product_grid') !== false
            || strpos($block, 'home-cms-section--product_carousel') !== false
            || strpos($block, 'dynamic-product-grid') !== false
            || strpos($block, 'gp-category-grid') !== false
        );
        // When CMS returned body HTML and catalog blocks actually rendered, do not stack legacy strips.
        if ($block !== '' && (!$cmsCatalogMapped || $cmsCatalogRendered)) {
            return;
        }
        // Mapped catalog sections that produced no HTML (empty API rows, missing local DB config) → legacy fallback.
        if ($cmsCatalogMapped && !$cmsCatalogRendered) {
            $hasCatSec = false;
            $hasProdSec = false;
        }
        $mainCats = isset($this->data['main_categories']) && is_array($this->data['main_categories'])
            ? $this->data['main_categories'] : array();
        if (!$hasCatSec && !empty($mainCats)) {
            $this->data['home_has_category_grid'] = true;
        }
        if (!$hasProdSec) {
            $this->data['home_has_product_grid'] = true;
        }
        if (!empty($this->data['home_has_product_grid'])
            && (empty($this->data['home_product_grid_items']) || !is_array($this->data['home_product_grid_items']))) {
            $this->data['home_product_grid_items'] = $this->fetch_home_featured_product_items();
        }
    }

    /**
     * Featured home products for plane_vanila themes — first products from top-level categories.
     *
     * @return array
     */
    private function fetch_home_featured_product_items()
    {
        $out = array();
        $seen = array();
        $mainCats = isset($this->data['main_categories']) && is_array($this->data['main_categories'])
            ? $this->data['main_categories'] : array();
        foreach ($mainCats as $cidKey => $row) {
            if (count($out) >= 16) {
                break;
            }
            $cid = 0;
            if (is_object($row)) {
                $cid = isset($row->id) ? (int) $row->id : 0;
            } elseif (is_array($row)) {
                $cid = isset($row['id']) ? (int) $row['id'] : 0;
            }
            if ($cid < 1 && is_numeric($cidKey)) {
                $cid = (int) $cidKey;
            }
            if ($cid < 1) {
                continue;
            }
            $hash = md5((string) $cid);
            $list = $this->webshop_model->get_products_list('category', $hash, true, 16, 1);
            if (!is_array($list) || empty($list['items']) || !is_array($list['items'])) {
                $list = $this->webshop_model->get_products_list('category', $cid, false, 16, 1);
            }
            if (!is_array($list) || empty($list['items']) || !is_array($list['items'])) {
                continue;
            }
            foreach ($list['items'] as $p) {
                $a = is_array($p) ? $p : (array) $p;
                $pid = isset($a['id']) ? (int) $a['id'] : (isset($a['product_id']) ? (int) $a['product_id'] : 0);
                if ($pid < 1 || isset($seen[$pid])) {
                    continue;
                }
                $seen[$pid] = true;
                $out[] = $a;
                if (count($out) >= 16) {
                    if (method_exists($this->webshop_model, 'enrich_product_list_items_with_stock')) {
                        return $this->webshop_model->enrich_product_list_items_with_stock($out, 0);
                    }
                    return $out;
                }
            }
        }
        if (!empty($out) && method_exists($this->webshop_model, 'enrich_product_list_items_with_stock')) {
            return $this->webshop_model->enrich_product_list_items_with_stock($out, 0);
        }
        return $out;
    }

    /**
     * Keep only body sections for legacy home patch mapping.
     * Header/footer/banner/logo flagged sections are rendered via dedicated API html blocks.
     */
    private function filter_body_sections($sections)
    {
        if (!is_array($sections)) {
            return array();
        }
        $filtered = array();
        foreach ($sections as $section) {
            $sec = is_object($section) ? (array) $section : (is_array($section) ? $section : array());
            $raw = isset($sec['section_contain']) ? $sec['section_contain'] : (isset($sec['config_json']) ? $sec['config_json'] : '');
            $cfg = array();
            if (is_array($raw)) {
                $cfg = $raw;
            } elseif (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $cfg = $decoded;
                }
            }

            $is_true = function ($value) {
                return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
            };
            $in_header = (isset($sec['header']) && $is_true($sec['header'])) || (isset($cfg['show_header']) && $is_true($cfg['show_header']));
            $in_footer = (isset($sec['footer']) && $is_true($sec['footer'])) || (isset($cfg['show_footer']) && $is_true($cfg['show_footer']));
            $in_banner = (isset($sec['banner']) && $is_true($sec['banner'])) || (isset($cfg['show_banner']) && $is_true($cfg['show_banner']));
            $in_logo = (isset($sec['logo']) && $is_true($sec['logo'])) || (isset($cfg['show_logo']) && $is_true($cfg['show_logo']));

            // All sections are now treated as body sections for the linear layout.
            $filtered[] = $section;
        }
        return $filtered;
    }

    /**
     * @param array $sections CMS section rows (may be body-filtered).
     * @param array $types    Normalized section_type values e.g. category_grid.
     */
    private function cms_section_list_includes_types(array $sections, array $types)
    {
        foreach ($sections as $section) {
            $sec = is_object($section) ? (array) $section : (is_array($section) ? $section : array());
            $t = $this->webshop_section_engine->normalized_section_type($sec);
            if (in_array($t, $types, true)) {
                return true;
            }
        }
        return false;
    }

    public function get_brand_list($category_brands)
    {

        if (is_array($category_brands)) {
            foreach ($category_brands as $brands) {
                foreach ($brands as $brand) {
                    $data[] = $brand;
                }
            }
            return $data;
        }
        return false;
    }

    public function products()
    {
        // Herbinn / Gulf CMS catalog lives at /products — prefer CMS when published.
        if ($this->is_plane_vanila_storefront() && $this->_render_cms_storefront_page('products', 'products')) {
            return;
        }

        $page = (int) $this->input->get('page', true);
        if ($page < 1) {
            $page = 1;
        }
        $limit = 12;
        $idHash = '';
        $q = $this->input->get('q', true);

        if ($q == "cetegory") {
            $idHash = $this->input->get('id', true);
            $data = $this->webshop_model->get_products_list('category', $idHash, $usedHash = TRUE, $limit, $page);

            $this->data['items_total'] = $data['items_total'];
            $this->data['listItems'] = $data['items'];
            //$this->data['product_variants']   = $data['product_variants'];
        }

        if ($q == "brand") {
            $idHash = $this->input->get('id', true);
            $data = $this->webshop_model->get_products_list('brand', $idHash, $usedHash = TRUE, $limit, $page);

            $this->data['items_total'] = $data['items_total'];
            $this->data['listItems'] = $data['items'];
        }

        $this->data['idHash'] = $idHash;
        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $this->load_view("products", $this->data);
    }

    /**
     * Generic CMS page renderer for URLs defined in ElintOm CMS tables.
     * Example: /webshop/cms_page/contact-us, /webshop/cms_page/health-blog
     */
    public function cms_page($slug = '')
    {
        $parts = func_get_args();
        if (!empty($parts)) {
            $slug = implode('/', array_map('trim', $parts));
        }
        $urlPath = '/' . ltrim((string) $slug, '/');
        if ($urlPath === '//') {
            $urlPath = '/';
        }

        $candidates = $this->webshop_model->cms_url_candidates_from_path($urlPath);
        $cmsPage = $this->webshop_model->find_published_cms_page($candidates);
        if (!is_object($cmsPage)) {
            show_404();
            return;
        }
        if (isset($cmsPage->url) && trim((string) $cmsPage->url) !== '') {
            $urlPath = '/' . ltrim((string) $cmsPage->url, '/');
        }

        $this->data['page_title'] = isset($cmsPage->page_title) ? $cmsPage->page_title : '';
        $this->data['meta_tags'] = $this->resolve_cms_page_head_meta($cmsPage);

        // Entity tag mapping (products/categories) — only when CMS page tags are empty.
        $pageEntityId = isset($cmsPage->id) ? (int) $cmsPage->id : 0;
        if ($pageEntityId > 0 && trim((string) $this->data['meta_tags']) === ''
            && method_exists($this->webshop_model, 'get_entity_tag_map')) {
            $pageTagMap = $this->webshop_model->get_entity_tag_map('page', $pageEntityId);
            if (is_array($pageTagMap) && !empty($pageTagMap)) {
                $pageEntityMetaTags = $this->build_entity_meta_tags($pageTagMap);
                if ($pageEntityMetaTags !== '') {
                    $this->data['meta_tags'] = $pageEntityMetaTags;
                }
                $entityMetaTitle = $this->resolve_entity_meta_title($pageTagMap);
                if ($entityMetaTitle !== '') {
                    $this->data['page_title'] = $entityMetaTitle;
                }
            }
            $pageTagRows = $this->webshop_model->get_entity_tag_rows('page', $pageEntityId);
            $this->data['entity_tag_groups'] = $this->group_entity_tags_for_view(
                is_array($pageTagRows) ? $pageTagRows : array()
            );
        }
        if (empty($this->data['entity_tag_groups']) && is_object($cmsPage)
            && isset($cmsPage->meta_tags_raw) && is_array($cmsPage->meta_tags_raw) && !empty($cmsPage->meta_tags_raw)) {
            $cmsTagRows = array();
            foreach ($cmsPage->meta_tags_raw as $rawTag) {
                $a = is_object($rawTag) ? (array) $rawTag : (is_array($rawTag) ? $rawTag : array());
                if (!isset($a['value']) || trim((string) $a['value']) === '') {
                    continue;
                }
                $cmsTagRows[] = array(
                    'tag_name' => isset($a['tag_name']) ? (string) $a['tag_name'] : (isset($a['property_name']) ? (string) $a['property_name'] : ''),
                    'property_name' => isset($a['property_name']) ? (string) $a['property_name'] : '',
                    'value' => (string) $a['value'],
                    'category' => isset($a['category']) ? (string) $a['category'] : 'SEO',
                );
            }
            $this->data['entity_tag_groups'] = $this->group_entity_tags_for_view($cmsTagRows);
        }

        $this->data['is_dynamic_cms_page'] = true;
        $this->data['dynamic_cms_slug'] = $urlPath;
        $this->data['dynamic_cms_page_type'] = isset($cmsPage->page_type) ? (string) $cmsPage->page_type : '';
        $this->data['home_page_cms'] = $cmsPage;
        $this->data['home_section_html_block'] = '';
        $pageType = isset($cmsPage->page_type) ? strtolower(trim((string) $cmsPage->page_type)) : '';
        $isHomeType = $pageType === 'home' || $urlPath === '/';
        $this->data['home_has_category_grid'] = $isHomeType;
        $this->data['home_has_product_grid'] = $isHomeType;
        $this->data['home_has_header_section'] = isset($cmsPage->show_header) ? (bool) $cmsPage->show_header : true;
        $this->data['home_has_footer_section'] = isset($cmsPage->show_footer) ? (bool) $cmsPage->show_footer : true;
        $this->data['home_category_grid_title'] = '';
        $this->data['home_product_grid_title'] = '';
        $this->data['page_banner_image_url'] = isset($cmsPage->page_banner_image_url) ? (string) $cmsPage->page_banner_image_url : '';
        $this->data['page_logo_image_url'] = isset($cmsPage->page_logo_image_url) ? (string) $cmsPage->page_logo_image_url : '';
        $this->data['cms_header_sections_html'] = isset($cmsPage->header_html) ? (string) $cmsPage->header_html : '';
        $this->data['cms_footer_sections_html'] = isset($cmsPage->footer_html) ? (string) $cmsPage->footer_html : '';

        $sections = isset($cmsPage->sections) && is_array($cmsPage->sections) ? $cmsPage->sections : array();
        // Same as index(): map flags from all section rows (header/footer/category/product types), not body-only.
        $this->apply_cms_sections_to_view_data($sections, false);

        $composedCmsHtml = '';
        if (!empty($cmsPage->logo_html)) {
            $composedCmsHtml .= (string) $cmsPage->logo_html;
        }
        if (!empty($cmsPage->banner_html)) {
            $composedCmsHtml .= (string) $cmsPage->banner_html;
        }

        $pageBodyHtml = isset($cmsPage->page_text) ? trim((string) $cmsPage->page_text) : '';
        $bodyForRender = $this->filter_body_sections($sections);
        $localBodyHtml = trim($this->webshop_section_engine->render_components($bodyForRender, $this->data));
        $composedBodyHtml = function_exists('webshop_compose_cms_body_html')
            ? webshop_compose_cms_body_html($pageBodyHtml, $localBodyHtml, $sections)
            : trim($pageBodyHtml . ($pageBodyHtml !== '' && $localBodyHtml !== '' ? "\n" : '') . $localBodyHtml);
        if ($composedBodyHtml !== '') {
            $this->data['home_section_html_block'] = $composedCmsHtml . $composedBodyHtml;
            $this->data['home_has_category_grid'] = false;
            $this->data['home_has_product_grid'] = false;
        } elseif ($composedCmsHtml !== '') {
            $this->data['home_section_html_block'] = $composedCmsHtml . (string) $this->data['home_section_html_block'];
        }
        if ($pageBodyHtml === '' && $localBodyHtml === ''
            && $this->cms_section_list_includes_types($bodyForRender, array('category_grid', 'category_carousel'))
            && empty($this->data['main_categories'])) {
            $this->data['home_has_category_grid'] = false;
        }

        // Expose body HTML for the dedicated cms_page.php view
        $this->data['cms_body_html'] = isset($this->data['home_section_html_block'])
            ? (string)$this->data['home_section_html_block'] : '';
        $this->data['cms_page'] = $cmsPage;
        $this->data['cms_page_load_error'] = (trim((string) $this->data['cms_body_html']) === '');

        // Reuse already-rendered section HTML; avoid rendering each section again.
        $this->data['cms_page_sections'] = $localBodyHtml !== '' ? array($localBodyHtml) : array();

        $this->config->load('elintom_api', true);
        $activeTheme = isset($this->webshop_settings->webshop_theme)
            ? trim((string) $this->webshop_settings->webshop_theme)
            : '';

        // plane_vanila CMS: home + all dynamic CMS pages use theme index (header/footer + cms body).
        // Legacy webshop/cms_page.php is only for old non-plane themes (e.g. default/orange).
        $usePlaneVanilaIndex = in_array($activeTheme, array('gulfpharmacy', 'nw', 'herbinnwellness'), true)
            || trim((string) $this->config->item('elintom_theme_view_folder', 'elintom_api')) !== '';

        if ($activeTheme === 'restaurant') {
            $this->load_view('restaurant/index', $this->data);
        } elseif ($activeTheme === 'nw') {
            $this->load_view('nw_theme/index', $this->data);
        } elseif ($usePlaneVanilaIndex || $isHomeType) {
            $this->load_view('index', $this->data);
        } else {
            $this->load_view('cms_page', $this->data);
        }
    }

    private function hydrate_static_cms_payload($slug, $target_key)
    {
        $urlPath = '/' . ltrim((string) $slug, '/');
        if ($urlPath === '//') {
            $urlPath = '/';
        }
        $cmsPage = $this->webshop_model->get_cms_page_by_url($urlPath);
        if (!is_object($cmsPage)) {
            return null;
        }
        $this->data[$target_key] = $cmsPage;
        if (!empty($cmsPage->page_title)) {
            $this->data['page_title'] = (string) $cmsPage->page_title;
        }
        $hydratedMeta = $this->resolve_cms_page_head_meta($cmsPage);
        if ($hydratedMeta !== '') {
            $this->data['meta_tags'] = $hydratedMeta;
        }
        if (!isset($this->data['home_section_html_block'])) {
            $this->data['home_section_html_block'] = '';
        }
        if (!isset($this->data['cms_header_sections_html'])) {
            $this->data['cms_header_sections_html'] = '';
        }
        if (!isset($this->data['cms_footer_sections_html'])) {
            $this->data['cms_footer_sections_html'] = '';
        }
        $this->data['home_has_header_section'] = isset($cmsPage->show_header) ? (bool) $cmsPage->show_header : true;
        $this->data['home_has_footer_section'] = isset($cmsPage->show_footer) ? (bool) $cmsPage->show_footer : true;
        $this->data['cms_header_sections_html'] = '';
        $this->data['cms_footer_sections_html'] = '';
        $sections = isset($cmsPage->sections) && is_array($cmsPage->sections) ? $cmsPage->sections : array();
        if (!empty($sections)) {
            $this->apply_cms_sections_to_view_data($sections, false);
        }
        $pageBodyHtml = isset($cmsPage->page_text) ? trim((string) $cmsPage->page_text) : '';
        $bodyForRender = $this->filter_body_sections($sections);
        $localBodyHtml = trim($this->webshop_section_engine->render_components($bodyForRender, $this->data));
        $combinedBodyHtml = function_exists('webshop_compose_cms_body_html')
            ? webshop_compose_cms_body_html($pageBodyHtml, $localBodyHtml, $sections)
            : trim($pageBodyHtml . ($pageBodyHtml !== '' && $localBodyHtml !== '' ? "\n" : '') . $localBodyHtml);
        if ($combinedBodyHtml !== '') {
            $this->data['home_section_html_block'] = $combinedBodyHtml;
            $this->data['home_has_category_grid'] = false;
            $this->data['home_has_product_grid'] = false;
        }
        if ($pageBodyHtml === '' && trim($localBodyHtml) === ''
            && $this->cms_section_list_includes_types($bodyForRender, array('category_grid', 'category_carousel'))
            && empty($this->data['main_categories'])) {
            $this->data['home_has_category_grid'] = false;
        }
        return $cmsPage;
    }

    public function product_details()
    {

        $product_hash = $this->uri->segment(3);

        $this->data['product_details'] = $productDetails = $this->webshop_model->get_product_by_hash($product_hash);
        if ($productDetails === false || !is_array($productDetails) || !isset($productDetails['item'])) {
            show_404();
            return;
        }
        $product = $productDetails['item'];
        if (method_exists($this->webshop_model, 'normalize_product_detail_item_for_view')) {
            $product = $this->webshop_model->normalize_product_detail_item_for_view($product);
        } elseif (is_object($product)) {
            $product = (array) $product;
        }
        $this->data['product'] = $product;
        $rawVariants = isset($productDetails['variants']) ? $productDetails['variants'] : array();
        if (function_exists('webshop_product_variants_from_row')) {
            $normalizedVariants = webshop_product_variants_from_row(array_merge($product, array('variants' => $rawVariants)));
            $this->data['product_variants'] = !empty($normalizedVariants) ? $normalizedVariants : $rawVariants;
        } else {
            $this->data['product_variants'] = $rawVariants;
        }
        $this->data['gallary_images'] = $productDetails['images'];

        $this->data['active_search_category'] = isset($product['category_id']) ? $product['category_id'] : 0;
        $productId = isset($product['id']) ? (int)$product['id'] : 0;

        if ($productId > 0) {
            $this->webshop_model->set_recent_viewed_product($productId);
        }

        if (!$this->webshop_catalog_bootstrap) {
            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
        } else {
            $this->data['recent_viewed'] = array();
        }

        $categoryHash = ($product['subcategory_id']) ? md5($product['subcategory_id']) : md5($product['category_id']);
        $reletedItems = $this->webshop_model->get_products_list('category', $categoryHash, $usedHash = TRUE, 8, 1);
        $this->data['related_products'] = (is_array($reletedItems) && isset($reletedItems['items']) && is_array($reletedItems['items']))
            ? $reletedItems['items']
            : array();

        $productStatus = ($productId > 0 && !$this->uses_elintom_catalog_api())
            ? $this->webshop_model->productAvailable($productId)
            : array(true, '');
        $this->data['product']['product_is_active'] = isset($productStatus[0]) ? $productStatus[0] : false;
        $this->data['product']['product_info_text'] = $productStatus[1];
        $this->data['product']['category_is_active'] = $this->uses_elintom_catalog_api()
            ? 'true'
            : $this->webshop_model->categoryActive($product['category_id']);
        if (empty($this->data['website_setting'])) {
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
        }
        $raw_settings = (isset($this->data['website_setting']) && (is_array($this->data['website_setting']) || is_object($this->data['website_setting'])))
            ? $this->data['website_setting']
            : [];
        $setting_map = [];
        foreach ($raw_settings as $row) {
            $setting_map[$row->fields] = $row->value;
        }

        $entity_tags = ($productId > 0) ? $this->webshop_model->get_entity_tag_map('product', $productId) : [];
        if (!is_array($entity_tags)) {
            $entity_tags = [];
        }
        $entity_tag_rows = method_exists($this->webshop_model, 'get_entity_tag_rows')
            ? $this->webshop_model->get_entity_tag_rows('product', $productId)
            : array();
        if (!is_array($entity_tag_rows)) {
            $entity_tag_rows = array();
        }
        $this->data['entity_tags'] = $entity_tags;
        $this->data['entity_tag_groups'] = $this->group_entity_tags_for_view($entity_tag_rows);
        $this->data['entity_meta_title'] = $this->resolve_entity_meta_title($entity_tags);
        $entity_meta_tags = $this->build_entity_meta_tags($entity_tags);
        if ($entity_meta_tags !== '') {
            $this->data['meta_tags'] = $entity_meta_tags;
        }

        // Prepare technical specifications for the unified table view
        $this->data['technical_specs'] = array(
            'Basic Information' => array(
                array('label' => 'Product Name', 'value' => isset($product['name']) ? $product['name'] : '-'),
                array('label' => 'Product Code', 'value' => isset($product['code']) ? $product['code'] : '-'),
                array('label' => 'SKU',          'value' => isset($product['article_code']) ? $product['article_code'] : '-'),
                array('label' => 'Brand',        'value' => isset($product['brand_name']) ? $product['brand_name'] : (isset($product['brand']) ? $product['brand'] : '-')),
                array('label' => 'Weight',       'value' => isset($product['weight']) ? $product['weight'] : '-'),
            ),
            'Pricing & Tax' => array(
                array('label' => 'Price',    'value' => $this->sma->formatMoney(isset($product['price']) ? $product['price'] : 0)),
                array('label' => 'MRP',      'value' => $this->sma->formatMoney(isset($product['mrp']) ? $product['mrp'] : 0)),
                array('label' => 'Tax Rate', 'value' => (isset($product['tax_rate']) ? $product['tax_rate'] : '0') . '%'),
            )
        );
        // Fetch and merge rating data for the product
        list($product['ratings_avarage'], $product['ratings_count']) = $this->resolve_product_rating_fields($product['id'], $product);
        $this->data['product'] = $product;
        $this->data['product_reviews'] = ($productId > 0)
            ? $this->webshop_model->get_product_reviews($productId, 24)
            : array();
        if (!is_array($this->data['product_reviews'])) {
            $this->data['product_reviews'] = array();
        }

        if ($this->input->get('format') === 'json' || $this->input->get('get_data') === '1') {
            $this->output->set_content_type('application/json')->set_output(json_encode($this->data));
            return;
        }

        $this->load_view("components/product_details", $this->data);
    }

    public function product_reviews($product_hash = '')
    {
        $product_hash = $product_hash !== '' ? $product_hash : $this->uri->segment(3);
        if ($product_hash === '') {
            show_404();
            return;
        }
        $productDetails = $this->webshop_model->get_product_by_hash($product_hash);
        if ($productDetails === false || !is_array($productDetails) || !isset($productDetails['item'])) {
            show_404();
            return;
        }
        $product = is_object($productDetails['item']) ? (array) $productDetails['item'] : $productDetails['item'];
        $reviews = $this->webshop_model->get_product_reviews($product['id']);

        $this->data['product'] = $product;
        $this->data['product_hash'] = $product_hash;
        $this->data['reviews'] = $reviews;
        $this->load_view("components/product_reviews", $this->data);
    }

    public function submit_product_review()
    {
        $product_hash   = trim((string) $this->input->post('product_hash'));
        $rating         = (int) $this->input->post('rating');
        $review_title   = trim((string) $this->input->post('review_title'));
        $review_details = trim((string) $this->input->post('review_details'));

        $back_url = $product_hash !== ''
            ? 'webshop/product_reviews/' . rawurlencode($product_hash)
            : 'webshop/index';

        // Preserve what the user typed so the view can repopulate after a redirect.
        // mb_strlen is safe for ASCII; fall back to strlen if mbstring is missing.
        $len = function ($v) { return function_exists('mb_strlen') ? mb_strlen((string) $v) : strlen((string) $v); };
        $fail = function ($field, $message) use ($back_url, $rating, $review_title, $review_details) {
            $this->session->set_flashdata('error', $message);
            $this->session->set_flashdata('error_field', $field);
            $this->session->set_flashdata('pr_old', array(
                'rating'         => $rating,
                'review_title'   => $review_title,
                'review_details' => $review_details,
            ));
            redirect($back_url);
        };

        // Server-side validation (browser-required is not enough — JS off, bots, etc.).
        if ($product_hash === '') {
            $this->session->set_flashdata('error', 'Missing product reference.');
            redirect('webshop/index');
            return;
        }
        if ($rating < 1 || $rating > 5) {
            $fail('rating', 'Please choose a rating between 1 and 5 stars.');
            return;
        }
        if ($review_title === '' || $len($review_title) < 3) {
            $fail('review_title', 'Please give your review a short title (min 3 characters).');
            return;
        }
        if ($review_details === '' || $len($review_details) < 10) {
            $fail('review_details', 'Please add a few words about your experience (min 10 characters).');
            return;
        }

        // Resolve the product through the API model (single source of truth).
        try {
            $productDetails = $this->webshop_api_model->get_product_by_hash($product_hash);
        } catch (Exception $e) {
            log_message('error', 'submit_product_review: get_product_by_hash threw: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Could not look up the product. Please try again.');
            redirect($back_url);
            return;
        }
        if ($productDetails === false || !is_array($productDetails) || !isset($productDetails['item'])) {
            $this->session->set_flashdata('error', 'Invalid product.');
            redirect('webshop/index');
            return;
        }

        $product = is_object($productDetails['item']) ? (array) $productDetails['item'] : $productDetails['item'];
        $product_id = isset($product['id']) ? (int) $product['id'] : 0;
        if ($product_id <= 0) {
            $this->session->set_flashdata('error', 'Invalid product.');
            redirect($back_url);
            return;
        }

        $ws_sess = $this->session->userdata('webshop');
        $customer_id = 0;
        $customer_name = '';
        if ($ws_sess) {
            $customer_id   = is_object($ws_sess) ? (isset($ws_sess->user_id) ? (int) $ws_sess->user_id : 0) : (isset($ws_sess['user_id']) ? (int) $ws_sess['user_id'] : 0);
            $customer_name = is_object($ws_sess) ? (isset($ws_sess->name) ? (string) $ws_sess->name : '') : (isset($ws_sess['name']) ? (string) $ws_sess['name'] : '');
        }
        if ($customer_name === '') {
            $customer_name = $customer_id > 0 ? 'Customer' : 'Guest';
        }

        // Match the field names that ElintOm's Webshop_api::submitproductreview reads.
        $reviewData = array(
            'product_id'    => $product_id,
            'rating'        => $rating,
            'review'        => $review_details,
            'review_title'  => $review_title,
            'product_name'  => isset($product['name']) ? (string) $product['name'] : 'Product',
            'variant_id'    => 0,
            'variant_name'  => '',
            'customer_id'   => $customer_id,
            'customer_name' => $customer_name,
        );

        try {
            $result = $this->webshop_api_model->submit_product_review($reviewData);
        } catch (Exception $e) {
            log_message('error', 'submit_product_review: api call threw: ' . $e->getMessage());
            $result = array('status' => 'ERROR', 'msg' => 'Service temporarily unavailable. Please try again.');
        }

        if ($result && isset($result['status']) && $result['status'] === 'SUCCESS') {
            log_message('info', 'submit_product_review: review saved for product_id=' . $product_id . ' rating=' . $rating);
            $this->session->set_flashdata('message', 'Review submitted successfully. Thank you for your feedback!');
        } else {
            log_message('error', 'submit_product_review: failed for product_id=' . $product_id
                . ' msg=' . (isset($result['msg']) ? $result['msg'] : 'no msg'));
            $msg = (isset($result['msg']) && $result['msg'] !== '') ? (string) $result['msg'] : 'Failed to submit review. Please try again.';
            $this->session->set_flashdata('error', $msg);
        }

        redirect($back_url);
    }

    /**
     * Head meta for CMS pages from getcmspage meta_tags_raw (Tag Values by Category).
     *
     * @param object $cmsPage
     * @return string
     */
    private function resolve_cms_page_head_meta($cmsPage)
    {
        if (!is_object($cmsPage)) {
            return '';
        }
        $pageTitle = isset($cmsPage->page_title) ? trim((string) $cmsPage->page_title) : '';
        $apiHtml = isset($cmsPage->meta_tags) ? trim((string) $cmsPage->meta_tags) : '';
        $raw = isset($cmsPage->meta_tags_raw) && is_array($cmsPage->meta_tags_raw) ? $cmsPage->meta_tags_raw : array();
        if (function_exists('webshop_meta_tags_html_from_cms_rows') && !empty($raw)) {
            $built = webshop_meta_tags_html_from_cms_rows($raw, array('page_title' => $pageTitle));
            if (trim($built) !== '') {
                return $built;
            }
        }
        return $apiHtml;
    }

    private function build_entity_meta_tags($entity_tags)
    {
        if (empty($entity_tags) || !is_array($entity_tags)) {
            return '';
        }
        $meta_parts = array();
        foreach ($entity_tags as $property_name => $value) {
            $property_name = trim((string) $property_name);
            $value = trim((string) $value);
            if ($property_name === '' || $value === '') {
                continue;
            }
            $normalized = strtolower(str_replace(array('-', ' '), '_', $property_name));
            if ($normalized === 'meta_description' || $normalized === 'description') {
                $meta_parts[] = '<meta name="description" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }
            if ($normalized === 'meta_keywords' || $normalized === 'keywords') {
                $meta_parts[] = '<meta name="keywords" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }
            if ($normalized === 'canonical' || $normalized === 'canonical_url') {
                $meta_parts[] = '<link rel="canonical" href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }
            if ($normalized === 'robots' || $normalized === 'meta_robots') {
                $meta_parts[] = '<meta name="robots" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }
            if ($normalized === 'viewport') {
                $meta_parts[] = '<meta name="viewport" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
                continue;
            }
            $safe_name = preg_replace('/[^a-zA-Z0-9\-_:.]/', '-', strtolower($property_name));
            $safe_name = trim((string) $safe_name, '-');
            if ($safe_name === '') {
                continue;
            }
            $meta_parts[] = '<meta name="' . htmlspecialchars($safe_name, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
        }
        return implode("\n", $meta_parts);
    }

    private function resolve_entity_meta_title($entity_tags)
    {
        if (empty($entity_tags) || !is_array($entity_tags)) {
            return '';
        }
        foreach ($entity_tags as $property_name => $value) {
            $property_name = strtolower(str_replace(array('-', ' '), '_', trim((string) $property_name)));
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if ($property_name === 'meta_title' || $property_name === 'title') {
                return $value;
            }
        }
        return '';
    }

    private function group_entity_tags_for_view($rows)
    {
        if (!is_array($rows) || empty($rows)) {
            return array();
        }
        $groups = array();
        foreach ($rows as $row) {
            $r = is_object($row) ? (array) $row : (is_array($row) ? $row : array());
            $value = isset($r['value']) ? trim((string) $r['value']) : '';
            if ($value === '') {
                continue;
            }
            $category = isset($r['category']) ? trim((string) $r['category']) : '';
            if ($category === '') {
                $category = 'General';
            }
            $tag_name = isset($r['tag_name']) && trim((string) $r['tag_name']) !== ''
                ? (string) $r['tag_name']
                : (isset($r['property_name']) ? (string) $r['property_name'] : '');
            if ($tag_name === '') {
                continue;
            }
            if (!isset($groups[$category])) {
                $groups[$category] = array();
            }
            $groups[$category][] = array(
                'label' => $tag_name,
                'value' => $value,
            );
        }
        return $groups;
    }

    public function category_products()
    {

        $this->data['get_category_id'] = $product_category = $this->uri->segment(3);
        $isCategoryActive = $this->webshop_model->checkIsCategoryActiveForWebshop($product_category);
        if(! $isCategoryActive) {
            redirect('webshop/index');
        }

        $this->data['idHash'] = md5($product_category);

        $this->data['active_search_category'] = $product_category;

        if ($this->uri->segment(4)) {
            $this->data['get_subcategory_id'] = $product_category = $this->uri->segment(4);
        }
        $this->data["category_is_active"] = $this->webshop_model->categoryActive($product_category);

        //$this->data['product_variants'] = $this->webshop_model->get_category_product_variants($product_category);
        // $this->data['listItems'] = $this->webshop_model->get_category_products($product_category);
        $idHash = md5($product_category);
        $specialItems = $this->getTodaysSpecialItemsForGivenCategory($product_category);
        $specialItemsId = [];
        $special_text = '';
        if (!empty($specialItems)) {
            foreach ($specialItems as $item) {
                $specialItemsId[] = $item['product_id'];
                if (!empty($item['title'])) {
                    $special_text =   $item['title'];
                }
            }
        }
        $this->data['special_item_text'] = $special_text;

        $page = (int) $this->input->get('page', true);
        if ($page < 1) {
            $page = 1;
        }
        $limit = 12;
        $data = $this->webshop_model->get_products_list('category', $idHash, $usedHash = TRUE, $limit, $page);

        $products = [];
        $specialItemsList = [];
        $apiCatalog = $this->uses_elintom_catalog_api();
        $restaurantOpen = 'true';
        $restaurantStatusText = 'Open';
        if (!$apiCatalog) {
            $restaurantWorking = $this->webshop_model->restaurantWorking();
            $restaurantOpen = isset($restaurantWorking['is_working']) ? $restaurantWorking['is_working'] : 'true';
            $restaurantStatusText = isset($restaurantWorking['working_flag_text']) ? $restaurantWorking['working_flag_text'] : 'Open';
        }
        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                $row = is_array($item) ? $item : (array) $item;
                if ($apiCatalog) {
                    $item['product_is_active'] = 'true';
                    $item['product_info_text'] = '';
                    $item['category_is_active'] = 'true';
                    $item['category_info_text'] = array('All*');
                } else {
                    list($available, $availabilityText) = $this->webshop_model->productAvailable($row['id']);
                    list($categoryActive, $categoryInfoText) = $this->webshop_model->categoryActive($row['category_id']);
                    $item['product_is_active'] = $available;
                    $item['product_info_text'] = $availabilityText;
                    $item['category_is_active'] = $categoryActive;
                    $item['category_info_text'] = $categoryInfoText;
                }
                $item['restaurant_is_active'] = $restaurantOpen;
                $item['restaurant_status_text'] = $restaurantStatusText;

                list($item['ratings_avarage'], $item['ratings_count']) = $this->resolve_product_rating_fields($row['id'], $row);

                if (!in_array($row['id'], $specialItemsId, true)) {
                    $products[] = $item;
                } else {
                    $specialItemsList[] = $item;
                }
            }
            unset($item);
        }

        /* get_products_list() already enriches stock + variants for API category lists — avoid duplicate HTTP. */
        $this->data['listItems'] = $products;

        foreach ($specialItemsList as $key => $item1) {
            foreach ($specialItems as $item2) {
                if ($item2['product_id'] == $item1['id']) {
                    $specialItemsList[$key]['special_price'] = $item2['special_price'];
                    break;
                }
            }
        }
        $this->data['special_items'] = $specialItemsList;

        $this->data['items_total'] = isset($data['items_total']) ? $data['items_total'] : 0;

        $gid = $this->data['get_category_id'];
        $this->data['subcategories'] = (isset($this->data['categories'][$gid]) && is_array($this->data['categories'][$gid]))
            ? $this->data['categories'][$gid]
            : array();

        if (!$this->webshop_catalog_bootstrap) {
            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
        } else {
            $this->data['recent_viewed'] = array();
        }
        $categoryEntityId = (int) $this->data['get_category_id'];
        $categoryTagRows = method_exists($this->webshop_model, 'get_entity_tag_rows')
            ? $this->webshop_model->get_entity_tag_rows('category', $categoryEntityId)
            : array();
        if (!is_array($categoryTagRows)) {
            $categoryTagRows = array();
        }
        $this->data['entity_tag_groups'] = $this->group_entity_tags_for_view($categoryTagRows);

        $categoryTagMap = method_exists($this->webshop_model, 'get_entity_tag_map')
            ? $this->webshop_model->get_entity_tag_map('category', $categoryEntityId)
            : array();
        if (!is_array($categoryTagMap)) {
            $categoryTagMap = array();
        }
        $category_meta_tags = $this->build_entity_meta_tags($categoryTagMap);
        if ($category_meta_tags !== '') {
            $this->data['meta_tags'] = $category_meta_tags;
        }
        $this->data['entity_meta_title'] = $this->resolve_entity_meta_title($categoryTagMap);


        if ($this->input->get('format') === 'json' || $this->input->get('get_data') === '1' || $this->webshop_settings->webshop_theme == 'restaurant') {

            foreach ($this->data['listItems'] as &$item) {
                $item['proudctIdHash'] = md5($item['id']);
                $item['formatedPrice'] = $this->sma->formatMoney($item['price']);
            }

            foreach ($this->data['special_items'] as &$special_item) {
                $special_item['proudctIdHash'] = md5($special_item['id']);
                $special_item['formatedPrice'] = $this->sma->formatMoney($special_item['special_price']);
            }
            if ($this->input->get('format') === 'json' || $this->input->get('get_data') === '1' || $this->webshop_settings->webshop_theme == 'restaurant') {
                $this->output->set_content_type('application/json')->set_output(json_encode($this->data));
                return;
            }
        }
        
        $this->load_view("components/category_products", $this->data);
    }

    public function getTodaysSpecialItemsForGivenCategory($categoryId)
    {
        $special_items = $this->webshop_model->getTodaysSpecialItemsForGivenCategoryDB($categoryId);
        return $special_items;
    }

    public function search_products()
    {

        $this->data['search_keyword'] = $keyword = $this->input->get('search');

        $this->data['get_category_id'] = $category = $this->input->get('search_by_category');

        $this->data['active_search_category'] = $category ? $category : '';

        $this->data['product_variants'] = $this->webshop_model->get_category_product_variants($category);

        $this->data['listItems'] = $products = $this->webshop_model->search_category_products($keyword, $category);

        $list_item = [];
        foreach ($this->data['listItems'] as $itemsList) {
            $list_item[] = $itemsList['code'];
        }

        $other_product = $this->webshop_model->search_other_products($keyword, $category);
        $otherProductArray = [];
        foreach ($other_product as $itemOtherProduct) {
            if (!in_array($itemOtherProduct['code'], $list_item)) {
                $otherProductArray[] = $itemOtherProduct;
            }
        }

        $this->data['otherItems'] = $otherProductArray;


        // $this->data['otherItems'] = $this->webshop_model->search_other_products($keyword, $category);

        if ($this->is_plane_vanila_storefront()) {
            $kw = trim((string) $keyword);
            $this->data['page_title'] = $kw !== '' ? ('Search: ' . $kw) : 'Search';
            $this->data['entity_meta_title'] = $this->data['page_title'];
            $this->data['get_category_id'] = 0;
            $this->load_view('category_products', $this->data);
            return;
        }

        $this->load_view("search_products", $this->data);
    }

    /**
     * AJAX endpoint — lightweight autocomplete for the header search box.
     *
     * GET ?q=… (or ?search=…) returns up to 8 matching products as JSON with
     * just enough fields to render a suggestion row (name, image, price, url).
     * Results are cached per-keyword in the session for 60s so a repeated key
     * sequence does not re-hit the ElintOm API on every keystroke.
     */
    public function search_suggest()
    {
        $keyword = trim((string) $this->input->get('q'));
        if ($keyword === '') {
            $keyword = trim((string) $this->input->get('search'));
        }

        $limit = (int) $this->input->get('limit');
        if ($limit < 1 || $limit > 12) {
            $limit = 8;
        }

        if (function_exists('mb_strlen') ? mb_strlen($keyword) < 2 : strlen($keyword) < 2) {
            $this->json_response(array('status' => 'OK', 'q' => $keyword, 'items' => array()));
            return;
        }

        // Short per-session cache absorbs repeated keystrokes without re-querying the API.
        $sess = $this->session->userdata('webshop_search_suggest_cache');
        if (!is_array($sess)) {
            $sess = array();
        }
        $cacheKey = (function_exists('mb_strtolower') ? mb_strtolower($keyword) : strtolower($keyword)) . '|' . $limit;
        if (isset($sess[$cacheKey]) && is_array($sess[$cacheKey]) && isset($sess[$cacheKey]['exp']) && $sess[$cacheKey]['exp'] > time()) {
            $this->json_response($sess[$cacheKey]['payload']);
            return;
        }

        $items = array();
        try {
            // Use the api model so DB-less and DB-backed deployments both work.
            $rows = $this->webshop_api_model->search_category_products($keyword, null);
            if (is_array($rows)) {
                $uploadsBase = isset($this->data['uploads']) ? (string) $this->data['uploads'] : '';
                $thumbsBase  = isset($this->data['thumbs'])  ? (string) $this->data['thumbs']  : '';
                $count = 0;
                foreach ($rows as $row) {
                    if ($count >= $limit) {
                        break;
                    }
                    $r = is_array($row) ? $row : (array) $row;
                    $id = !empty($r['id']) ? $r['id'] : (!empty($r['product_id']) ? $r['product_id'] : null);
                    if ($id === null) {
                        continue;
                    }
                    $hash  = md5((string) $id);
                    $name  = isset($r['name']) ? (string) $r['name'] : (isset($r['product_name']) ? (string) $r['product_name'] : '');
                    if ($name === '') {
                        continue;
                    }
                    $price = isset($r['price']) ? $r['price'] : (isset($r['unit_price']) ? $r['unit_price'] : 0);
                    $promo = isset($r['promo_price']) && $r['promo_price'] !== '' && (float) $r['promo_price'] > 0 ? $r['promo_price'] : null;
                    $mrp   = isset($r['mrp']) && $r['mrp'] !== '' && (float) $r['mrp'] > 0 ? $r['mrp'] : null;
                    $image = function_exists('webshop_product_image_src')
                        ? webshop_product_image_src($uploadsBase, $thumbsBase, $r)
                        : '';
                    $items[] = array(
                        'id'    => $id,
                        'hash'  => $hash,
                        'name'  => $name,
                        'image' => $image,
                        'price' => $promo !== null ? (float) $promo : (float) $price,
                        'mrp'   => $mrp,
                        'url'   => base_url('webshop/product_details/' . $hash),
                    );
                    $count++;
                }
            }
        } catch (Exception $e) {
            // Suggestions are best-effort — never fail the dropdown with a 500.
        }

        $payload = array('status' => 'OK', 'q' => $keyword, 'items' => $items);
        $sess[$cacheKey] = array('exp' => time() + 60, 'payload' => $payload);
        // Cap cache so it can't grow unbounded across a long session.
        if (count($sess) > 30) {
            $sess = array_slice($sess, -30, null, true);
        }
        $this->session->set_userdata('webshop_search_suggest_cache', $sess);

        $this->json_response($payload);
    }

    public function wishlist()
    {
        $ws_sess = $this->session->userdata('webshop');
        $user_id = null;
        if ($ws_sess) {
            $user_id = is_object($ws_sess) ? (isset($ws_sess->user_id) ? $ws_sess->user_id : null) : (isset($ws_sess['user_id']) ? $ws_sess['user_id'] : null);
        }

        if (!$user_id) {
            redirect('webshop/login');
            return;
        }

        $wishlist = $this->webshop_model->get_wishlist($user_id);
        $product_ids = array();
        $wishlist_lines = array();
        $this->data['wishlist_variants'] = array();

        $norm = function_exists('webshop_wishlist_normalize_rows')
            ? webshop_wishlist_normalize_rows($wishlist)
            : array('lines' => array(), 'lookup' => array(), 'count' => 0, 'duplicates' => array());

        if (!empty($norm['duplicates']) && method_exists($this->webshop_model, 'remove_from_wishlist')) {
            foreach ($norm['duplicates'] as $dup) {
                $dup_pid = isset($dup['product_id']) ? (int) $dup['product_id'] : 0;
                if ($dup_pid < 1) {
                    continue;
                }
                $dup_oid = isset($dup['option_id']) ? (int) $dup['option_id'] : 0;
                $this->webshop_model->remove_from_wishlist($user_id, $dup_pid, $dup_oid > 0 ? $dup_oid : null);
            }
        }

        foreach ($norm['lines'] as $line) {
            $pid = (int) $line['product_id'];
            $oid = (int) $line['option_id'];
            $product_ids[$pid] = $pid;
            $wishlist_lines[] = array(
                'product_id' => $pid,
                'option_id'  => $oid,
            );
            if (!isset($this->data['wishlist_variants'][$pid])) {
                $this->data['wishlist_variants'][$pid] = array();
            }
            if (!in_array($oid, $this->data['wishlist_variants'][$pid], true)) {
                $this->data['wishlist_variants'][$pid][] = $oid;
            }
        }

        $products_by_id = array();
        if (!empty($product_ids)) {
            $list_data = $this->webshop_model->get_products_list('products', array_values($product_ids), true);
            $items = (is_array($list_data) && isset($list_data['items']) && is_array($list_data['items']))
                ? $list_data['items']
                : array();
            if (!empty($items) && method_exists($this->webshop_model, 'enrich_product_list_items_with_variants')) {
                $items = $this->webshop_model->enrich_product_list_items_with_variants($items);
            }
            foreach ($items as $item) {
                $a = is_array($item) ? $item : (array) $item;
                $id = isset($a['id']) ? (int) $a['id'] : 0;
                if ($id > 0) {
                    $products_by_id[$id] = $a;
                }
            }
            $missing = array();
            foreach ($product_ids as $pid) {
                if (!isset($products_by_id[$pid]) && method_exists($this->webshop_model, 'resolve_product_row_by_id')) {
                    $missing[$pid] = $pid;
                }
            }
            foreach ($missing as $pid) {
                $full = $this->webshop_model->resolve_product_row_by_id($pid);
                if (is_array($full) && !empty($full)) {
                    $products_by_id[$pid] = $full;
                }
            }
        }

        $wl_display = array();
        foreach ($wishlist_lines as $line) {
            $pid = (int) $line['product_id'];
            if (!isset($products_by_id[$pid])) {
                continue;
            }
            $wl_display[] = array(
                'product'   => $products_by_id[$pid],
                'option_id' => (int) $line['option_id'],
            );
        }

        $this->data['wishlist'] = array('items' => array_values($products_by_id));
        $this->data['wishlist_display'] = $wl_display;
        $this->data['wishlist_count'] = (int) $norm['count'];
        if (function_exists('webshop_build_wishlist_lookup')) {
            $this->data['wishlist_lookup'] = webshop_build_wishlist_lookup($wishlist);
        }
        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $theme = isset($this->webshop_settings->webshop_theme) ? $this->webshop_settings->webshop_theme : 'default';
        if ($theme == 'restaurant') {
            $this->load_view("webshop_restaurant_t1/wishlist", $this->data);
            return;
        }
        $this->load_view("wishlist", $this->data);
    }

    public function compare()
    {

        $this->load_view("compare", $this->data);
    }

    /**
     * True when session cart enrichment has displayable name + price for every line.
     *
     * @param array|false $raw_cd
     * @return bool
     */
    private function _cart_enrichment_is_complete($raw_cd)
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || $_SESSION['cart'] === array()) {
            return true;
        }
        $products = (is_array($raw_cd) && isset($raw_cd['products']) && is_array($raw_cd['products']))
            ? $raw_cd['products']
            : array();
        foreach ($_SESSION['cart'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($pid < 1) {
                continue;
            }
            $p = isset($products[$pid]) && is_array($products[$pid]) ? $products[$pid] : array();
            $name = '';
            if (!empty($item['product_name'])) {
                $name = trim((string) $item['product_name']);
            } elseif (!empty($p['name'])) {
                $name = trim((string) $p['name']);
            } elseif (!empty($p['product_name'])) {
                $name = trim((string) $p['product_name']);
            }
            if ($name === '' || strcasecmp($name, 'product') === 0) {
                return false;
            }
            $price = 0.0;
            foreach (array('product_price', 'price') as $sk) {
                if (isset($item[$sk]) && (float) $item[$sk] > 0) {
                    $price = (float) $item[$sk];
                    break;
                }
            }
            if ($price <= 0) {
                foreach (array('eshop_price', 'price', 'sale_price', 'mrp') as $pk) {
                    if (isset($p[$pk]) && (float) $p[$pk] > 0) {
                        $price = (float) $p[$pk];
                        break;
                    }
                }
            }
            if ($price <= 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Expose wishlist product/variant ids to views for PLP card heart state (logged-in only).
     *
     * @param int|null $user_id
     */
    private function _apply_wishlist_lookup_for_user($user_id)
    {
        $this->data['wishlist_lookup'] = array();
        $this->data['webshop_is_logged_in'] = false;
        $uid = (int) $user_id;
        if ($uid < 1 && function_exists('webshop_is_customer_logged_in') && webshop_is_customer_logged_in()) {
            $ws_sess = $this->session->userdata('webshop');
            if ($ws_sess) {
                $uid = is_object($ws_sess)
                    ? (int) (isset($ws_sess->user_id) ? $ws_sess->user_id : 0)
                    : (int) (isset($ws_sess['user_id']) ? $ws_sess['user_id'] : 0);
            }
        }
        if ($uid < 1) {
            return;
        }
        if (function_exists('webshop_is_customer_logged_in') && !webshop_is_customer_logged_in()) {
            return;
        }
        $this->data['webshop_is_logged_in'] = true;
        if (!function_exists('webshop_build_wishlist_lookup')) {
            return;
        }
        $rows = $this->webshop_model->get_wishlist($uid);
        $this->data['wishlist_lookup'] = webshop_build_wishlist_lookup($rows);
        if (method_exists($this->webshop_model, 'get_wishlist_count')) {
            $this->data['wishlist_count'] = (int) $this->webshop_model->get_wishlist_count($uid);
        } elseif (function_exists('webshop_wishlist_normalize_rows')) {
            $this->data['wishlist_count'] = (int) webshop_wishlist_normalize_rows($rows)['count'];
        }
    }

    /**
     * Cart page enrichment: one bulk API pass when possible; refresh session cache only if incomplete.
     */
    private function _prepare_cart_page_data()
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || $_SESSION['cart'] === array()) {
            $this->data['cart_items'] = array();
            $this->data['cart_data']  = array();
            return;
        }

        $this->data['cart_items'] = $_SESSION['cart'];
        $raw_cd = $this->webshop_model->get_cart_data();
        if (!$this->_cart_enrichment_is_complete($raw_cd)) {
            $this->session->unset_userdata('elintom_cache_cart_data');
            $raw_cd = $this->webshop_model->get_cart_data();
        }

        $products = (is_array($raw_cd) && isset($raw_cd['products']) && is_array($raw_cd['products']))
            ? $raw_cd['products']
            : array();

        $missing_ids = array();
        foreach ($_SESSION['cart'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($pid < 1) {
                continue;
            }
            $p = isset($products[$pid]) && is_array($products[$pid]) ? $products[$pid] : array();
            $name_ok = !empty($item['product_name'])
                || (!empty($p['name']) && strcasecmp(trim((string) $p['name']), 'product') !== 0)
                || (!empty($p['product_name']) && strcasecmp(trim((string) $p['product_name']), 'product') !== 0);
            if (!$name_ok) {
                $missing_ids[$pid] = $pid;
            }
        }

        if (!empty($missing_ids) && method_exists($this->webshop_model, 'resolve_product_rows_by_ids')) {
            $fetched = $this->webshop_model->resolve_product_rows_by_ids(array_values($missing_ids));
            foreach ($fetched as $pid => $row) {
                if (is_array($row) && !empty($row)) {
                    $products[(int) $pid] = isset($products[$pid]) && is_array($products[$pid])
                        ? array_merge($products[$pid], $row)
                        : $row;
                }
            }
        }

        if (function_exists('webshop_enrich_cart_session_prices')) {
            $products = webshop_enrich_cart_session_prices($products);
        }
        if (function_exists('webshop_enrich_cart_session_variant_labels')) {
            $products = webshop_enrich_cart_session_variant_labels($products);
        }

        foreach ($_SESSION['cart'] as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($pid < 1) {
                continue;
            }
            $p = isset($products[$pid]) && is_array($products[$pid]) ? $products[$pid] : array();
            if (!empty($p['name']) && empty($_SESSION['cart'][$key]['product_name'])) {
                $_SESSION['cart'][$key]['product_name'] = (string) $p['name'];
            }
        }

        $this->data['cart_items'] = $_SESSION['cart'];
        $this->data['cart_data']  = array('products' => $products);
        if (function_exists('webshop_cart_variants_map_from_products')) {
            $this->data['cart_data']['variants'] = webshop_cart_variants_map_from_products($products);
            foreach ($_SESSION['cart'] as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $vid = isset($line['variant_id']) ? (int) $line['variant_id'] : 0;
                if ($vid < 1 || isset($this->data['cart_data']['variants'][$vid])) {
                    continue;
                }
                if (!empty($line['variant_name'])) {
                    $this->data['cart_data']['variants'][$vid] = array(
                        'id'         => $vid,
                        'name'       => trim((string) $line['variant_name']),
                        'product_id' => isset($line['product_id']) ? (int) $line['product_id'] : 0,
                    );
                }
            }
        }
        if (is_array($raw_cd) && isset($raw_cd['coupon'])) {
            $this->data['cart_data']['coupon'] = $raw_cd['coupon'];
        }
    }

    public function cart()
    {

        // var_dump($this->data);
        // exit;

        if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && $_SESSION['cart'] !== array()) {
            $this->_prepare_cart_page_data();
        }

        $this->data['gp_header_logo_fetchpriority'] = false;
        $this->data['cart_page_perf'] = true;

        $theme = $this->webshop_settings->webshop_theme;
        if ($theme == 'restaurant') {
            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
            $this->data['state_list'] = $this->webshop_model->get_state();
            $raw_settings = $this->webshop_model->get_website_setting();
            $this->data['website_setting'] = $raw_settings;
            $setting_map = [];
            if (is_array($raw_settings) || is_object($raw_settings)) {
                foreach ($raw_settings as $row) {
                    $setting_map[$row->fields] = $row->value;
                }
            }
            $this->data['setting_map'] = $setting_map;
            // if($this->input->get("getCart") == "1"){
            // print_r($this->data);
            //     $this->json_response($this->data);
            //     return;
            // }
            $this->load_view("cart", $this->data);
        } else if ($theme == 'nw') {
            $hideCategories = ['Veterinary Nutraceuticals', 'Softgel Capsules'];
            foreach ($this->data['cart_items'] as &$item) {
                $idHash = md5($item['product_id']);
                $productDetails = $this->webshop_model->get_product_by_hash($idHash);
                $categoryId = $productDetails['item']['category_id'];
                $catName = rtrim($this->data['categories']['main'][$categoryId]->name);
                $item['show_price'] = !in_array($catName, $hideCategories);
            }
            unset($item);
            $this->load_view("cart", $this->data);
        } else if ($theme) {
            // $hideCategories = ['Veterinary Nutraceuticals', 'Softgel Capsules'];
            // foreach ($this->data['cart_items'] as &$item) {
            //     $idHash = md5($item['product_id']);
            //     $productDetails = $this->webshop_model->get_product_by_hash($idHash);
            //     $categoryId = $productDetails['item']['category_id'];
            //     $catName = rtrim($this->data['categories']['main'][$categoryId]->name);
            //     $item['show_price'] = !in_array($catName, $hideCategories);
            // }
            // unset($item);
            $this->load_view("cart", $this->data);
        } else {
            if (!isset($_SESSION['cart'])) {
                redirect('webshop/index');
            }

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
            $this->data['state_list'] = $this->webshop_model->get_state();

            $this->load_view("cart", $this->data);
        }
    }

    public function checkout()
    {
        $this->load->library('webshop_checkout');
        $this->webshop_checkout->present($this);
    }

    public function submit_order()
    {
        $this->load->model('webshop_api_model');

        if ($this->input->post('submit_order')) {

            $submit_token_ok = function_exists('webshop_checkout_submit_token_is_valid')
                ? webshop_checkout_submit_token_is_valid($this->input->post('submit_order'))
                : (md5(date('Y-m-d H')) === (string) $this->input->post('submit_order'));

            if ($submit_token_ok) {

                $terms_post = $this->input->post('terms');
                if ($terms_post === false || $terms_post === null || $terms_post === '') {
                    $this->session->set_flashdata(
                        'error_message',
                        'You must agree to the terms and conditions before placing your order.'
                    );
                    redirect('webshop/checkout');
                    return;
                }

                // Authoritative customer: when a webshop user is logged in, the order MUST be
                // attributed to that session user_id. Looking the customer up by typed billing
                // phone/email would otherwise attach the new order to a stranger whose existing
                // record happens to share the phone the buyer typed (so /my-orders shows nothing).
                $session_user_id = $this->_get_webshop_session_user_id();

                if ($this->input->post('default_shipping_address') && $this->input->post('customer_id')) {
                    $customer_id = $this->input->post('customer_id');
                    if ($session_user_id) {
                        // Never trust a client-supplied customer_id for a logged-in session.
                        $customer_id = $session_user_id;
                    }
                    $address_id = $this->input->post('default_shipping_address');
                    $address = $this->webshop_model->get_customer_address($customer_id, $address_id);
                    if (empty($address) || !isset($address[$address_id])) {
                        $this->session->set_flashdata('error_message', 'Could not resolve selected address. Please try again.');
                        redirect('webshop/checkout');
                        return;
                    }
                    $billing_address = $address[$address_id];
                    $shipping_address = $address[$address_id];

                    $customer = $this->webshop_model->get_customer(['id' => $customer_id]);

                    $shipping_address_id = $billing_address_id = $address_id;
                } else {
                    if ($this->input->post('billing_address_1') !== false && $this->input->post('billing_address_1') !== null) {
                        $billing_state_raw = $this->input->post('billing_state');
                        $billing_stateData = (strpos($billing_state_raw, '~') !== false) ? explode('~', $billing_state_raw) : array($billing_state_raw, '');

                        $billing_phone = $this->input->post('billing_phone');
                        $billing_email = $this->input->post('billing_email');

                        // Logged-in checkout: bind the order to the session user, not to whichever
                        // existing record happens to match the typed billing phone.
                        if ($session_user_id) {
                            $customer = $this->webshop_model->get_customer(['id' => $session_user_id]);
                        } else {
                            $customer = $this->webshop_model->get_customer(['phone' => $billing_phone]);
                        }

                        if (!$customer) {

                            $account_password = NULL;
                            if (!empty($this->input->post('account_password'))) {
                                $account_password = md5($this->input->post('account_password'));
                            }
                            $country_code_raw = $this->input->post('billing_country', true);
                            if ($country_code_raw) {
                                $country_parts = explode('~', $country_code_raw);
                                $country = $country_parts[1];
                            }
                            $customerData = array(
                                "group_id" => '3',
                                "group_name" => 'customer',
                                "customer_group_id" => '1',
                                "customer_group_name" => 'General',
                                "price_group_id" => '2',
                                "price_group_name" => 'Standered',
                                "name" => $this->input->post('billing_first_name') . ' ' . $this->input->post('billing_last_name'),
                                "company" => $this->input->post('billing_company'),
                                "address" => $this->input->post('billing_address_1') . ' ' . $this->input->post('billing_address_2'),
                                "city" => $this->input->post('billing_city'),
                                "state" => $billing_stateData[0],
                                "state_code" => $billing_stateData[1],
                                "postal_code" => $this->input->post('billing_postcode'),
                                "country" => $country,
                                "phone" => $this->input->post('billing_phone'),
                                "email" => $this->input->post('billing_email'),
                                "password" => $account_password,
                            );

                            $customer = $this->webshop_api_model->add_customer($customerData);

                            if (empty($customer) || !is_array($customer)) {
                                // API failed to create a customer — bail early.
                                $this->session->set_flashdata('error_message', 'We could not register your details. Please try again.');
                                redirect('webshop/checkout');
                                return;
                            }

                            $this->send_welcome_mail($customerData);
                        }
                    } else {
                        $customer_id = $this->input->post('customer_id');
                        if ($session_user_id) {
                            $customer_id = $session_user_id;
                        }
                        $customer = $this->webshop_model->get_customer(['id' => $customer_id]);

                        $address_id =   $this->webshop_model->getAddressDefault($customer_id, 'default');
                        $shipping_address_id = $billing_address_id = $address_id;
                    }


                    if (!empty($this->input->post('billing_address_id'))) {
                        $billing_address_id = $this->input->post('billing_address_id');
                    } else {
                        if ($this->input->post('billing_address_1') !== false && $this->input->post('billing_address_1') !== null) {
                            $country_code_raw = $this->input->post('billing_country', true);
                            $country = '';
                            if ($country_code_raw) {
                                $country_parts = explode('~', $country_code_raw);
                                $country = isset($country_parts[1]) ? $country_parts[1] : '';
                            }
                            $billing_address = array(
                                "company_id" => isset($customer['id']) ? $customer['id'] : 0,
                                "address_name" => $this->input->post('billing_first_name') . ' ' . $this->input->post('billing_last_name'),
                                "company_name" => $this->input->post('billing_company'),
                                "line1" => $this->input->post('billing_address_1'),
                                "line2" => $this->input->post('billing_address_2'),
                                "city" => $this->input->post('billing_city'),
                                "postal_code" => $this->input->post('billing_postcode'),
                                "state" => $this->input->post('billing_state'),
                                "country" => $country,
                                "phone" => $this->input->post('billing_phone'),
                                "email_id" => $this->input->post('billing_email'),
                            );

                            $billing_address_id = $this->webshop_api_model->add_address($billing_address);
                        } else {
                            $billing_address_id =   $this->webshop_model->getAddressDefault($customer['id'], 'default');
                        }
                    }


                    if (!empty($this->input->post('shipping_address_id'))) {

                        $shipping_address_id = $this->input->post('shipping_address_id');
                    } else {

                        if ($this->input->post('billing_and_shipping_address_is_same')) {

                            // $billing_address may be undefined when the buyer picked an existing
                            // saved billing address (id-only path). Keep the same id for shipping;
                            // guard the array clone so we don't emit notices in that path.
                            if (isset($billing_address) && is_array($billing_address)) {
                                $shipping_address = $billing_address;
                                $shipping_address['address_type'] = 'shipping';
                            } else {
                                $shipping_address = array();
                            }

                            $shipping_address_id = $billing_address_id;
                        } else {
                            if ($this->input->post('shipping_address_1') !== false && $this->input->post('shipping_address_1') !== null) {
                                $shipping_state_raw = $this->input->post('shipping_state');
                                $shipping_stateData = (strpos($shipping_state_raw, '~') !== false) ? explode('~', $shipping_state_raw) : array($shipping_state_raw, '');
                                $country_code_raw = $this->input->post('shipping_country');
                                if (!$country_code_raw) {
                                    $country_code_raw = $this->input->post('billing_country');
                                }
                                $country = '';
                                if ($country_code_raw) {
                                    $country_parts = explode('~', $country_code_raw);
                                    $country = isset($country_parts[1]) ? $country_parts[1] : '';
                                }
                                $shipping_address = array(
                                    "company_id" => isset($customer['id']) ? $customer['id'] : 0,
                                    "address_name" => $this->input->post('shipping_first_name') . ' ' . $this->input->post('shipping_last_name'),
                                    "company_name" => $this->input->post('shipping_company'),
                                    "line1" => $this->input->post('shipping_address_1'),
                                    "line2" => $this->input->post('shipping_address_2'),
                                    "city" => $this->input->post('shipping_city'),
                                    "postal_code" => $this->input->post('shipping_postcode'),
                                    "state" => $shipping_stateData[0],
                                    "state_code" => $shipping_stateData[1],
                                    "country" => $country,
                                    "phone" => $this->input->post('shipping_phone'),
                                    "email_id" => $this->input->post('shipping_email'),
                                );

                                $shipping_address_id = $this->webshop_api_model->add_address($shipping_address);
                            } else {
                                $shipping_address_id = $this->webshop_model->getAddressDefault(isset($customer['id']) ? $customer['id'] : 0, 'shipping');
                            }
                        }
                    } //end if.
                } //end else

                // Logged-in checkout: reject forged address ids (saved-address picker).
                if ($session_user_id && !empty($customer) && is_array($customer) && (int) $customer['id'] === $session_user_id) {
                    $allowed_addrs = $this->webshop_model->get_customer_address($session_user_id);
                    $posted_bill = (int) $this->input->post('billing_address_id');
                    if ($posted_bill > 0 && !isset($allowed_addrs[$posted_bill])) {
                        $this->session->set_flashdata('error_message', 'Invalid billing address. Please choose an address from your list.');
                        redirect('webshop/checkout');
                        return;
                    }
                    $posted_ship = (int) $this->input->post('shipping_address_id');
                    $same_ship = (string) $this->input->post('billing_and_shipping_address_is_same') === '1'
                        || $this->input->post('billing_and_shipping_address_is_same') === true;
                    if ($posted_ship > 0 && !$same_ship && !isset($allowed_addrs[$posted_ship])) {
                        $this->session->set_flashdata('error_message', 'Invalid shipping address. Please choose an address from your list.');
                        redirect('webshop/checkout');
                        return;
                    }
                }

                $warehouse_id = $this->webshop_settings->warehouse_id;
                $biller_id = $this->webshop_settings->biller_id;
                $biller = $this->webshop_model->get_company_by_id($biller_id);
                if (!is_array($biller)) {
                    $biller = [];
                }
                $biller += ['name' => '', 'state_code' => ''];

                $products = array();
                $order = array();
                $total_items = 0;
                $total = 0;
                $total_item_tax = 0;
                $total_item_discount = 0;
                $sale_cgst = $sale_sgst = $sale_igst = 0;
                $discount = 0;
                $interStateTax = false;
                if (isset($customer['state_code']) && isset($biller['state_code']) && !empty($customer['state_code']) && !empty($biller['state_code']) && $customer['state_code'] != $biller['state_code']) {
                    $interStateTax = true;
                }

                if (is_array($this->input->post('item_id'))) {

                    $cartItems = $this->input->post('item_id');
                    $cart_options = $this->input->post('option_id');
                    $cart_option_price = $this->input->post('option_price');
                    $cart_item_unit_quantity = $this->input->post('item_unit_quantity');
                    $cart_item_quantity = $this->input->post('item_quantity');
                    $cart_item_unit_price = $this->input->post('item_unit_price');
                    $cart_item_tax_rate = $this->input->post('item_tax_rate');
                    $cart_item_tax_method = $this->input->post('item_tax_method');
                    $cart_item_promotion_price = $this->input->post('item_promotion_price');
                    $cart_item_product_price = $this->input->post('item_product_price');
                    $discount = $this->input->post('coupon_discount_amount');
                    $units = $this->webshop_model->get_units();

                    $sale_cgst = $sale_sgst = $sale_igst = 0;
                    $total = $total_item_tax = $total_item_discount = 0;
                    $total_items = 0;

                    foreach ($cartItems as $key => $product_id) {

                        $product_id = (int) $product_id;
                        if ($product_id < 1) {
                            continue;
                        }

                        $sess_line = (isset($_SESSION['cart'][$key]) && is_array($_SESSION['cart'][$key]))
                            ? $_SESSION['cart'][$key]
                            : null;

                        $option_id = isset($cart_options[$key]) ? (int) $cart_options[$key] : 0;
                        if ($option_id <= 0 && $sess_line !== null && !empty($sess_line['variant_id'])) {
                            $option_id = (int) $sess_line['variant_id'];
                        }

                        $option_price = isset($cart_option_price[$key]) ? (float) $cart_option_price[$key] : 0.0;
                        if ($option_price <= 0 && $sess_line !== null && isset($sess_line['variant_price'])) {
                            $option_price = (float) $sess_line['variant_price'];
                        }

                        $quintity = isset($cart_item_quantity[$key]) ? (float) $cart_item_quantity[$key] : 1.0;
                        $line_unit_qty = isset($cart_item_unit_quantity[$key]) ? (float) $cart_item_unit_quantity[$key] : 1.0;
                        if ($line_unit_qty < 1 && $sess_line !== null && isset($sess_line['unit_quantity'])) {
                            $line_unit_qty = max(1.0, (float) $sess_line['unit_quantity']);
                        }
                        if ($line_unit_qty < 1) {
                            $line_unit_qty = 1.0;
                        }
                        // POS stock uses cart packs × variant unit_quantity; customer totals use cart packs only.
                        $stock_quantity = $quintity * $line_unit_qty;

                        $unit_price = (float) $cart_item_unit_price[$key];
                        $tax_rate = (float) $cart_item_tax_rate[$key];
                        $tax_method = $cart_item_tax_method[$key];
                        $promotion_price = (float) $cart_item_promotion_price[$key];
                        $item_price = (float) $cart_item_product_price[$key];

                        $selectData = "id,code,article_code,name,unit AS unit_id,eshop_price As price,weight,cf1,cf2,tax_rate AS tax_id , tax_method, type AS product_type, sale_unit AS sale_unit_id, mrp, hsn_code, storage_type, promotion, promo_price,start_date,end_date";

                        $productData = $this->webshop_model->get_product_by_id($product_id, $selectData);

                        if (empty($productData) || !isset($productData[$product_id])) {
                            log_message('error', 'Checkout error: Product ' . $product_id . ' could not be resolved via API.');
                            $this->session->set_flashdata('error_message', 'One or more items in your cart are no longer available. Please check your cart.');
                            redirect('webshop/cart');
                            return;
                        }

                        $product = $productData[$product_id];

                        if (method_exists($this->webshop_model, 'resolve_product_row_by_id')) {
                            $full_product = $this->webshop_model->resolve_product_row_by_id($product_id);
                            if (is_array($full_product) && !empty($full_product)) {
                                $product = array_merge($product, $full_product);
                            }
                        }

                        if ($option_id <= 0 && function_exists('webshop_resolve_line_option_id')) {
                            $opt_hints = array();
                            if ($sess_line !== null) {
                                if (!empty($sess_line['variant_id'])) {
                                    $opt_hints['variant_id'] = (int) $sess_line['variant_id'];
                                }
                                if (!empty($sess_line['variant_name'])) {
                                    $opt_hints['variant_name'] = trim((string) $sess_line['variant_name']);
                                }
                                if (isset($sess_line['variant_price'])) {
                                    $opt_hints['variant_price'] = (float) $sess_line['variant_price'];
                                }
                            }
                            if ($option_price > 0) {
                                $opt_hints['variant_price'] = $option_price;
                            }
                            $option_id = webshop_resolve_line_option_id($product, $option_id, $opt_hints);
                        }

                        $product['tax_rate']        = $tax_rate;
                        $product['sale_unit_id']    = isset($product['sale_unit_id'])  ? $product['sale_unit_id']  : null;
                        $product['mrp']             = isset($product['mrp'])           ? $product['mrp']           : 0;
                        $product['hsn_code']        = isset($product['hsn_code'])      ? $product['hsn_code']      : '';
                        $product['code']            = isset($product['code'])          ? $product['code']          : '';
                        $product['article_code']    = isset($product['article_code'])  ? $product['article_code']  : '';
                        $product['name']            = isset($product['name'])          ? $product['name']          : '';
                        $product['product_type']    = isset($product['product_type'])  ? $product['product_type']  : '';
                        $product['tax_id']          = isset($product['tax_id'])        ? $product['tax_id']        : null;
                        $product['promotion']       = isset($product['promotion'])     ? $product['promotion']     : 0;
                        $product['tax_method']      = isset($product['tax_method'])    ? $product['tax_method']    : $tax_method;

                        $sale_unit_id = $product['sale_unit_id'];
                        $unit_code = ($sale_unit_id && isset($units[$sale_unit_id]['code'])) ? $units[$sale_unit_id]['code'] : '';
                        // Per cart pack (checkout $180), not base + variant again (which doubled to $360).
                        $productPrice = function_exists('webshop_submit_order_line_sale_price')
                            ? webshop_submit_order_line_sale_price($product, $option_id, $option_price, $unit_price, $item_price, $sess_line)
                            : product_sale_price_webshop($product, array('1' => 0.0), null, 1);
                        $invoice_unit_price = $productPrice['net_unit_price'];
                        // $invoice_net_unit_price = $productPrice['net_unit_price'] + $productPrice['unit_discount'] + $productPrice['unit_tax'];
                        $invoice_net_unit_price = $productPrice['net_unit_price'] + $productPrice['unit_discount'];
                        $net_price = $stock_quantity * $product['mrp'];
                        $invoice_total_net_unit_price = $invoice_net_unit_price * $quintity;
                        $item_tax = $productPrice['unit_tax'] * $quintity;
                        // $item_discount = $productPrice['unit_discount'] * (float) $quintity;
                        $item_discount = $productPrice['unit_discount'];
                        // $subtotal = (($productPrice['net_unit_price'] * (float) $quintity) + (float) $item_tax);
                        $subtotal = ($productPrice['net_unit_price'] * $quintity);
                        if ($interStateTax) {
                            $item_gst = $tax_rate;
                            $item_cgst = 0;
                            $item_sgst = 0;
                            $item_igst = $item_tax;
                        } else {
                            $item_gst = (float) $tax_rate / 2;
                            $item_cgst = (float) $item_tax / 2;
                            $item_sgst = (float) $item_tax / 2;
                            $item_igst = 0;
                        }
                        $order_comment = trim((string) $this->input->post('order_comments'));
                        $order_comment = (strtolower($order_comment) === 'null' || $order_comment === '') ? null : $order_comment;

                        $variant_label = '';
                        if ($option_id > 0) {
                            if ($sess_line !== null && !empty($sess_line['variant_name'])) {
                                $variant_label = trim((string) $sess_line['variant_name']);
                            } elseif (function_exists('webshop_product_variants_from_row')) {
                                foreach (webshop_product_variants_from_row($product) as $vRow) {
                                    if (webshop_variant_row_id($vRow) === $option_id) {
                                        $variant_label = webshop_variant_row_display_name($vRow, '');
                                        break;
                                    }
                                }
                            }
                        }
                        $line_product_name = (string) $product['name'];
                        if ($variant_label !== '') {
                            $line_product_name = $line_product_name . ' — ' . $variant_label;
                        }

                        $products[] = array(
                            "product_id" => $product_id,
                            "product_code" => $product['code'],
                            "article_code" => $product['article_code'],
                            "product_name" => $line_product_name,
                            "product_type" => $product['product_type'],
                            "option_id" => (int) $option_id,
                            "net_unit_price" => $this->sma->formatDecimal($productPrice['net_unit_price'], 4),
                            "unit_discount" => is_numeric($productPrice['unit_discount'])
                                ? (float) $productPrice['unit_discount']
                                : 0,
                            "unit_tax" => $this->sma->formatDecimal($productPrice['unit_tax'], 4),
                            "invoice_unit_price" => $this->sma->formatDecimal($invoice_unit_price, 4),
                            "invoice_net_unit_price" => $this->sma->formatDecimal($invoice_net_unit_price, 4),
                            "unit_price" => $productPrice['unit_price'],
                            "quantity" => $stock_quantity,
                            "net_price" => $net_price,
                            "invoice_total_net_unit_price" => $invoice_total_net_unit_price,
                            "warehouse_id" => $warehouse_id,
                            "item_tax" => $this->sma->formatDecimal($item_tax, 4),
                            "tax_method" => $tax_method,
                            "tax_rate_id" => $product['tax_id'],
                            "tax" => is_numeric($productPrice['tax_rate']) ? (float) $productPrice['tax_rate'] : 0,
                            "discount" => is_numeric($productPrice['discount_rate']) ? (float) $productPrice['discount_rate'] : 0,
                            "item_discount" => $this->sma->formatDecimal($item_discount, 4),
                            "subtotal" => $this->sma->formatDecimal($subtotal, 4),
                            "real_unit_price" => $productPrice['real_unit_price'],
                            "product_unit_id" => $product['sale_unit_id'],
                            "product_unit_code" => $unit_code,
                            "unit_quantity" => $quintity,
                            "mrp" => $this->sma->formatDecimal($product['mrp'], 4),
                            "hsn_code" => $product['hsn_code'],
                            "note" => $order_comment,
                            "delivery_status" => 'pending',
                            "pending_quantity" => $quintity,
                            "delivered_quantity" => 0,
                            "gst_rate" => $item_gst,
                            "cgst" => $this->sma->formatDecimal($item_cgst, 4),
                            "sgst" => $this->sma->formatDecimal($item_sgst, 4),
                            "igst" => $this->sma->formatDecimal($item_igst, 4),
                            "item_weight" => $stock_quantity,
                        );

                        $total_items++;

                        $sale_cgst += $item_cgst;
                        $sale_sgst += $item_sgst;
                        $sale_igst += $item_igst;

                        // $total += ((float) $productPrice['net_unit_price'] * (float) $quintity);
                        $total += ($productPrice['net_unit_price'] * $quintity);
                        $total_item_tax += (float) $item_tax;
                        $total_item_discount += (float) $item_discount;
                    } //end foreach.

                    // Site model requires direct DB access which is unavailable in API/DB-less mode.
                    // Always generate a unique reference directly to avoid the fatal crash.
                    $reference = 'ES-' . date('Ymd') . '-' . strtoupper(substr(uniqid('', true), -6));
                    $date = date('Y-m-d H:i:s');

                    // Guard: customer resolution may return false/null if the API is unavailable.
                    if (empty($customer) || !is_array($customer)) {
                        $this->session->set_flashdata('error_message', 'Could not resolve customer. Please try again.');
                        redirect('webshop/checkout');
                        return;
                    }

                    $customer_id   = isset($customer['id'])   ? $customer['id']   : 0;
                    $customer_name = isset($customer['name']) ? $customer['name'] : '';
                    // $note = $this->db->escape($this->input->post('order_comments'));
                    $shipping = (($this->input->post('shipping_charges')) ? $this->input->post('shipping_charges') : 0);
                    $shipping = is_numeric($shipping) ? (float) $shipping : 0.0;
                    $order_discount_id = NULL;
                    $order_tax_id = NULL;
                    $order_tax = 0;
                    $discount = is_numeric($discount) ? (float) $discount : 0.0;

                    $total_discount = $total_item_discount + $discount;
                    $total_tax = $total_item_tax + $order_tax;
                    // Never trust client-submitted totals. Recompute from server-side item math.
                    $grand_total = (($total + $total_tax + $shipping) - $discount);
                    if ($grand_total < 0) {
                        $grand_total = 0;
                    }
                    $rounding = 0;

                    if ($this->webshop_settings->rounding > 0) {
                        $round_total = $this->sma->roundNumber($grand_total, $this->webshop_settings->rounding);
                        // $rounding = ($round_total - $grand_total);
                        $rounding = 0;
                    }

                    $order = array(
                        'eshop_sale' => 1,
                        'date' => $date,
                        'reference_no' => $reference,
                        'customer_id' => $customer_id,
                        'customer' => $customer_name,
                        'biller_id' => $biller_id,
                        'biller' => $biller['name'],
                        'warehouse_id' => $warehouse_id,
                        'note' => $order_comment,
                        'staff_note' => '',
                        'total' => $this->sma->formatDecimal($total, 4),
                        'product_discount' => $this->sma->formatDecimal($total_item_discount, 4),
                        'order_discount_id' => $order_discount_id,
                        'order_discount' => $this->sma->formatDecimal($discount, 4),
                        'total_discount' => $this->sma->formatDecimal($total_discount, 4),
                        'product_tax' => $this->sma->formatDecimal($total_item_tax, 4),
                        'order_tax_id' => $order_tax_id,
                        'order_tax' => $this->sma->formatDecimal($order_tax, 4),
                        'total_tax' => $this->sma->formatDecimal($total_tax, 4),
                        'shipping' => $this->sma->formatDecimal($shipping, 4),
                        'grand_total' => $this->sma->formatDecimal($grand_total, 4),
                        'total_items' => $total_items,
                        'sale_status' => 'Received',
                        'payment_status' => 'due',
                        'payment_method' => $this->input->post('payment_method'),
                        'payment_term' => $this->input->post('term'),
                        'rounding' => $this->sma->formatDecimal($rounding, 4),
                        'due_date' => NULL,
                        'paid' => 0,
                        'eshop_order_alert_status' => 0,
                        'created_by' => NULL,
                        'cgst' => $this->sma->formatDecimal($sale_cgst, 4),
                        'sgst' => $this->sma->formatDecimal($sale_sgst, 4),
                        'igst' => $this->sma->formatDecimal($sale_igst, 4),
                        'billing_address_id' => $billing_address_id,
                        'shipping_address_id' => $shipping_address_id,
                        'delivery_type' => $this->input->post('delivery_mode'),
                        'coupon_code' => $this->input->post('coupon_code'),
                    );
                }


                if (count($products) && !empty($order)) {

                    if (function_exists('webshop_prepare_order_lines_for_elintom')) {
                        $products = webshop_prepare_order_lines_for_elintom(
                            $products,
                            isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array()
                        );
                    }
                    if (empty($products)) {
                        $this->session->set_flashdata(
                            'error_message',
                            'Your cart lines could not be sent to the store. Please open your cart, refresh quantities, and try checkout again.'
                        );
                        redirect('webshop/checkout');
                        return;
                    }

                    $payment_method = (string) $this->input->post('payment_method');
                    $customer_id    = isset($customer['id']) ? $customer['id'] : 0;
                    $is_online      = in_array($payment_method, array('razorpay', 'ccavenue', 'paytm', 'instamojo', 'online'), true);

                    if ($is_online) {
                        $temp_id = 'TMP_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
                        $this->session->set_flashdata(
                            'message',
                            'Complete payment below. Your order is sent to ElintOm only after payment succeeds.'
                        );
                        $this->session->set_userdata('order_id', $temp_id);
                        $this->session->set_userdata('pending_order_payload', array(
                            'order'    => $order,
                            'products' => $products,
                            'customer' => $customer,
                        ));
                        $this->session->set_userdata('pending_payment_order', array(
                            'order_id'     => $temp_id,
                            'customer_id'  => $customer_id,
                            'reference_no' => isset($order['reference_no']) ? $order['reference_no'] : $temp_id,
                            'grand_total'  => isset($order['grand_total']) ? (float) $order['grand_total'] : 0.0,
                            'currency_iso' => $this->resolve_currency_iso_at_checkout_submit(),
                            'method'       => $payment_method,
                        ));
                        $this->_pending_order_payload_cache_put(
                            array(
                                'order'    => $order,
                                'products' => $products,
                                'customer' => $customer,
                            ),
                            $temp_id,
                            isset($order['reference_no']) ? (string) $order['reference_no'] : ''
                        );
                        redirect("webshop/payments?order=$temp_id&customer=$customer_id");
                        return;
                    }

                    $order_id = $this->webshop_api_model->add_order($order, $products);
                    $this->session->set_userdata('order_id', $order_id);
                    $this->session->set_userdata('checkout_currency_iso', $this->resolve_currency_iso_at_checkout_submit());

                    if ($order_id) {
                        $this->session->set_userdata('pending_payment_order', array(
                            'order_id'     => $order_id,
                            'customer_id'  => $customer_id,
                            'reference_no' => isset($order['reference_no']) ? $order['reference_no'] : '',
                            'grand_total'  => isset($order['grand_total']) ? (float) $order['grand_total'] : 0.0,
                            'currency_iso' => $this->resolve_currency_iso_at_checkout_submit(),
                            'method'       => $payment_method,
                        ));

                        if (function_exists('webshop_build_order_success_flash')) {
                            $this->session->set_flashdata(
                                'order_success_display',
                                webshop_build_order_success_flash($order, $products, (int) $order_id)
                            );
                        }

                        $this->_notify_order_placed_customer((int) $order_id, $order);

                        redirect("webshop/order_success?order=$order_id&customer=$customer_id");

                        return;
                    }

                    // add_order failed — redirect back to checkout with error.
                    $api_err = method_exists($this->webshop_api_model, 'get_last_order_error')
                        ? trim((string) $this->webshop_api_model->get_last_order_error())
                        : '';
                    $this->session->set_flashdata(
                        'error_message',
                        $api_err !== ''
                            ? $api_err
                            : 'Order could not be placed. Please try again or choose Cash on delivery.'
                    );
                    log_message('error', 'Webshop::submit_order add_order failed'
                        . (isset($order['reference_no']) ? ' ref=' . $order['reference_no'] : '')
                        . ($api_err !== '' ? ' — ' . $api_err : ''));
                    redirect('webshop/checkout');
                    return;

                } else {
                    // Cart empty or customer/address resolution failed.
                    $this->session->set_flashdata('error_message', 'Your cart is empty or address is missing.');
                    redirect('webshop/checkout');
                    return;
                }
            } else {
                $this->session->set_flashdata(
                    'error_message',
                    'Your checkout session expired. Please open checkout again and place your order within the same hour.'
                );
                $_SESSION['postdata'] = $this->input->post();
                redirect('webshop/checkout');
            }
        } else {
            $this->session->set_flashdata('error_message', 'Invalid checkout request. Please try again from your cart.');
            redirect('webshop/checkout');
        }
    }



    public function send_invoice_by_email(array $para)
    {

        $order_id = $para['order_id'];
        $customer = $para['customer'];

        $to_email = $customer['email'];
        $to_name = $customer['name'];

        if (empty($to_email)) {
            return false;
        }
        $subject = 'Invoice for Order #' . $order_id;
        $message = 'Dear ' . $to_name . ",\n\nYour order has been placed successfully.\nOrder ID: " . $order_id . "\n\nThank you.";
        return $this->_send_basic_email($to_email, $subject, nl2br($message));
    }

    public function send_welcome_mail($customer)
    {

        $to_email = $customer['email'];
        $to_name = $customer['name'];

        if (empty($to_email)) {
            return false;
        }
        $subject = 'Welcome to ' . (isset($this->Settings->site_name) ? $this->Settings->site_name : 'Webshop');
        $message = 'Dear ' . $to_name . ",<br><br>Welcome to our webshop. Your account is now active.";
        return $this->_send_basic_email($to_email, $subject, $message);
    }

    /**
     * Normalize to ISO 4217 3-letter code or empty string.
     *
     * @param mixed $raw
     * @return string
     */
    private function _normalize_currency_iso($raw)
    {
        $s = preg_replace('/[^A-Za-z]/', '', (string) $raw);
        $code = strtoupper(strlen($s) >= 3 ? substr($s, 0, 3) : $s);
        return strlen($code) === 3 ? $code : '';
    }

    /**
     * Currency shown with checkout totals — from API webshop settings / POS settings (same source as checkout screen).
     *
     * @return string 3-letter ISO or empty
     */
    private function get_checkout_currency_iso()
    {
        if (isset($this->webshop_settings->currency_code)) {
            $c = $this->_normalize_currency_iso($this->webshop_settings->currency_code);
            if ($c !== '') {
                return $c;
            }
        }
        if (isset($this->Settings->default_currency)) {
            return $this->_normalize_currency_iso($this->Settings->default_currency);
        }
        return '';
    }

    /**
     * Persist at order submit — matches storefront currency used on checkout before cart is cleared.
     *
     * @return string
     */
    private function resolve_currency_iso_at_checkout_submit()
    {
        $store = $this->get_checkout_currency_iso();
        if ($store !== '') {
            return $store;
        }
        return $this->_normalize_currency_iso($this->get_default_currency_code_for_schema());
    }

    /**
     * Currency for payment gateway: POST (payments form) → session from checkout → store settings → schema default.
     *
     * @return string
     */
    private function resolve_payment_currency_iso()
    {
        $post = $this->_normalize_currency_iso($this->input->post('currency'));
        if ($post !== '') {
            return $post;
        }
        $sess = $this->_normalize_currency_iso($this->session->userdata('checkout_currency_iso'));
        if ($sess !== '') {
            return $sess;
        }
        $store = $this->get_checkout_currency_iso();
        if ($store !== '') {
            return $store;
        }
        return $this->_normalize_currency_iso($this->get_default_currency_code_for_schema());
    }

    /**
     * CCAvenue expects the same ISO code as checkout; no region-specific hardcoding — configure store + gateway to match.
     *
     * @param string $requested_code From encrypted handler / POST (may be empty)
     * @return string
     */
    private function _resolve_ccavenue_currency($requested_code)
    {
        $code = $this->_normalize_currency_iso($requested_code);
        if ($code !== '') {
            return $code;
        }
        return $this->resolve_payment_currency_iso();
    }

    /**
     * Read country from API/local address row (multiple possible keys).
     *
     * @param array $addr
     * @return string Trimmed display/country text or empty
     */
    private function _address_country_text(array $addr)
    {
        foreach (array('country', 'country_name', 'Country') as $k) {
            if (!isset($addr[$k])) {
                continue;
            }
            $raw = (string) $addr[$k];
            // Unicode whitespace trim — NBSP-only values must not pass as "non-empty".
            if (function_exists('preg_replace')) {
                $raw = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $raw);
            }
            $raw = trim(strip_tags($raw));
            if ($raw !== '') {
                return $raw;
            }
        }
        if (isset($addr['country_code'])) {
            $cc = trim((string) $addr['country_code']);
            if (preg_match('/^[A-Za-z]{2,3}$/', $cc)) {
                return $cc;
            }
        }
        return '';
    }

    /**
     * CCAvenue validates billing_country strictly (error 21009 if blank). Docs/samples use full country names
     * (e.g. "India"), max ~50 alphabetic characters — some ME merchant profiles reject 2-letter ISO as "missing".
     *
     * @param string $raw
     * @return string Non-empty country name (truncated to 50)
     */
    private function _normalize_ccavenue_country_string($raw)
    {
        $defaultFull = 'United Arab Emirates';
        $s = (string) $raw;
        if (function_exists('preg_replace')) {
            $s = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $s);
        }
        $s = trim(strip_tags($s));
        if ($s === '' || $s === '-' || strtoupper($s) === 'N/A') {
            return $defaultFull;
        }

        // Two-letter ISO → full English name (CCAvenue initiate samples use full names).
        if (preg_match('/^[A-Za-z]{2}$/', $s)) {
            $iso = strtoupper($s);
            static $isoToFull = array(
                'AE' => 'United Arab Emirates',
                'IN' => 'India',
                'PK' => 'Pakistan',
                'SA' => 'Saudi Arabia',
                'KW' => 'Kuwait',
                'QA' => 'Qatar',
                'BH' => 'Bahrain',
                'OM' => 'Oman',
                'EG' => 'Egypt',
                'GB' => 'United Kingdom',
                'US' => 'United States',
                'CA' => 'Canada',
                'AU' => 'Australia',
                'SG' => 'Singapore',
                'MY' => 'Malaysia',
                'PH' => 'Philippines',
                'BD' => 'Bangladesh',
                'LK' => 'Sri Lanka',
                'NP' => 'Nepal',
                'FR' => 'France',
                'DE' => 'Germany',
                'IT' => 'Italy',
                'ES' => 'Spain',
                'NL' => 'Netherlands',
                'BE' => 'Belgium',
                'IE' => 'Ireland',
                'ZA' => 'South Africa',
                'NG' => 'Nigeria',
                'KE' => 'Kenya',
            );
            if (isset($isoToFull[$iso])) {
                $s = $isoToFull[$iso];
            }
        }

        $lower = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        static $aliases = array(
            'united arab emirates' => 'United Arab Emirates',
            'u.a.e.'               => 'United Arab Emirates',
            'uae'                  => 'United Arab Emirates',
            'india'                => 'India',
            'pakistan'             => 'Pakistan',
            'saudi arabia'         => 'Saudi Arabia',
            'kuwait'               => 'Kuwait',
            'qatar'                => 'Qatar',
            'bahrain'              => 'Bahrain',
            'oman'                 => 'Oman',
            'egypt'                => 'Egypt',
            'united kingdom'       => 'United Kingdom',
            'united states'        => 'United States',
            'usa'                  => 'United States',
            'canada'               => 'Canada',
            'australia'            => 'Australia',
            'singapore'            => 'Singapore',
            'malaysia'             => 'Malaysia',
            'philippines'          => 'Philippines',
            'bangladesh'           => 'Bangladesh',
            'sri lanka'            => 'Sri Lanka',
            'nepal'                => 'Nepal',
            'france'               => 'France',
            'germany'              => 'Germany',
            'italy'                => 'Italy',
            'spain'                => 'Spain',
            'netherlands'          => 'Netherlands',
            'belgium'              => 'Belgium',
            'ireland'              => 'Ireland',
            'south africa'         => 'South Africa',
            'nigeria'              => 'Nigeria',
            'kenya'                => 'Kenya',
        );
        if (isset($aliases[$lower])) {
            $s = $aliases[$lower];
        } elseif (strpos($lower, 'emirates') !== false) {
            $s = 'United Arab Emirates';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($s, 'UTF-8') > 50) {
            return mb_substr($s, 0, 50, 'UTF-8');
        }
        if (!function_exists('mb_strlen') && strlen($s) > 50) {
            return substr($s, 0, 50);
        }
        return $s;
    }

    /**
     * Last-mile value for encrypted merchant_data: CCAvenue validates billing_country as Latin "Alphabets".
     * Arabic-only or punctuation-only values become blank at the gateway → 21009. UAE/GCC spaced names are
     * normalized to the single token "UAE" to match strict parsers.
     *
     * @param string $normalized Output of {@see _normalize_ccavenue_country_string()}
     * @return string Non-empty ASCII letters (and single spaces where kept), max 50
     */
    private function _finalize_ccavenue_country_field($normalized)
    {
        $s = trim((string) $normalized);
        // Latin letters and spaces only (documentation: Alphabets).
        $latin = preg_replace('/[^A-Za-z\s]/', '', $s);
        $latin = preg_replace('/\s+/', ' ', $latin);
        $latin = trim($latin);
        if ($latin === '') {
            return 'UAE';
        }
        $compact = strtolower(str_replace(' ', '', $latin));
        if (strpos($compact, 'unitedarabemirates') !== false || $compact === 'uae'
            || strpos($compact, 'emirates') !== false) {
            return 'UAE';
        }
        if (strlen($latin) > 50) {
            return substr($latin, 0, 50);
        }
        return $latin;
    }

    /**
     * CCAvenue requires non-empty billing_* and delivery_* on many merchant profiles; empty fields cause gateway errors.
     *
     * @param int        $customer_id companies.id (webshop customer)
     * @param array|false $order       Row from get_order_by_id (API or DB)
     * @return array Keys: billing_name, billing_company, billing_address, billing_city, billing_state, billing_country, billing_zip, billing_tel, billing_email
     */
    private function _resolve_ccavenue_billing($customer_id, $order)
    {
        $defaults = array(
            'billing_name'    => 'Customer',
            'billing_company' => '-',
            'billing_address' => 'Address not provided',
            'billing_city'    => 'Dubai',
            'billing_state'   => 'Dubai',
            'billing_country' => 'United Arab Emirates',
            'billing_zip'     => '00000',
            'billing_tel'     => '0500000000',
            'billing_email'   => 'customer@example.com',
        );
        $customer_id = (int) $customer_id;
        if (empty($order) || !is_array($order)) {
            return $defaults;
        }
        $bid = isset($order['billing_address_id']) ? (int) $order['billing_address_id'] : 0;
        if ($bid < 1 || $customer_id < 1) {
            return $defaults;
        }
        $addr_map = $this->webshop_api_model->get_customer_address($customer_id, $bid);
        $addr = array();
        if (is_array($addr_map)) {
            if (isset($addr_map[$bid]) && is_array($addr_map[$bid])) {
                $addr = $addr_map[$bid];
            } else {
                foreach ($addr_map as $row) {
                    if (is_array($row) && isset($row['id']) && (int) $row['id'] === $bid) {
                        $addr = $row;
                        break;
                    }
                }
            }
        }
        if (empty($addr)) {
            return $defaults;
        }
        $name = isset($addr['address_name']) ? trim((string) $addr['address_name']) : '';
        $line1 = isset($addr['line1']) ? trim((string) $addr['line1']) : '';
        $line2 = isset($addr['line2']) ? trim((string) $addr['line2']) : '';
        $street = trim($line1 . ($line2 !== '' ? ', ' . $line2 : ''));
        $city = isset($addr['city']) ? trim((string) $addr['city']) : '';
        $state = isset($addr['state']) ? trim((string) $addr['state']) : '';
        $country = $this->_address_country_text($addr);
        $zip = isset($addr['postal_code']) ? preg_replace('/\s+/', '', (string) $addr['postal_code']) : '';
        $phone = isset($addr['phone']) ? preg_replace('/[^\d+]/', '', (string) $addr['phone']) : '';
        $email = isset($addr['email_id']) ? trim((string) $addr['email_id']) : (isset($addr['email']) ? trim((string) $addr['email']) : '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $defaults['billing_email'];
        }
        if ($email === '') {
            $email = $defaults['billing_email'];
        }
        if ($zip === '') {
            $zip = $defaults['billing_zip'];
        }
        if ($phone === '') {
            $phone = $defaults['billing_tel'];
        }
        $trunc = function ($str, $max) {
            $str = (string) $str;
            if (function_exists('mb_substr')) {
                return mb_substr($str, 0, $max);
            }
            return strlen($str) > $max ? substr($str, 0, $max) : $str;
        };
        return array(
            'billing_name'    => $name !== '' ? $trunc($name, 120) : $defaults['billing_name'],
            'billing_company' => isset($addr['company_name']) && trim((string) $addr['company_name']) !== ''
                ? $trunc(trim((string) $addr['company_name']), 120) : $defaults['billing_company'],
            'billing_address' => $street !== '' ? $trunc($street, 240) : $defaults['billing_address'],
            'billing_city'    => $city !== '' ? $trunc($city, 60) : $defaults['billing_city'],
            'billing_state'   => $state !== '' ? $trunc($state, 60) : $defaults['billing_state'],
            'billing_country' => $this->_finalize_ccavenue_country_field(
                $this->_normalize_ccavenue_country_string(
                    $country !== '' ? $trunc($country, 60) : $defaults['billing_country']
                )
            ),
            'billing_zip'     => $trunc($zip, 24),
            'billing_tel'     => $trunc($phone, 40),
            'billing_email'   => $email,
        );
    }

    public function payment_ccavResponseHandler()
    {

        $this->load->helper('crypto_helper');

        // Same credential source as payment_ccavRequestHandler / payments(): API first, then local config.
        // Response decrypt must use the **same** working key used to encrypt the request or decryption fails.
        $ccavenue = array();
        $apiCredentials = $this->webshop_model->get_gateway_credentials();
        if (!empty($apiCredentials['ccavenue'])) {
            $ccavenue = $apiCredentials['ccavenue'];
        } else {
            $ci = get_instance();
            $ci->config->load('payment_gateways', TRUE);
            $pc = $ci->config->item('payment_gateways');
            $ccavenue = isset($pc['ccavenue']) ? $pc['ccavenue'] : array();
        }
        $workingKey = isset($ccavenue['API_KEY']) ? trim((string) $ccavenue['API_KEY']) : '';

        $encResponse = isset($_POST['encResp']) ? (string) $_POST['encResp'] : '';
        if ($encResponse === '' && isset($_POST['encresp'])) {
            $encResponse = (string) $_POST['encresp'];
        }

        if ($encResponse === '') {
            $this->session->set_flashdata(
                'error_message',
                'No payment data was received. Complete payment on the CCAvenue page, or open the payment link from your order again. Refreshing this page will not work.'
            );
            $this->load_view('payment_declined', $this->theme_view_data(array(
                'error_message' => 'No payment response was posted. Use your order payment link again.',
            )));
            return;
        }
        if ($workingKey === '') {
            $this->session->set_flashdata(
                'error_message',
                'CCAvenue working key is not configured. Add it in ElintOm gateway settings or application/config/payment_gateways.php.'
            );
            $this->load_view('payment_declined', $this->theme_view_data(array(
                'error_message' => 'CCAvenue decryption key is missing.',
            )));
            return;
        }

        $rcvdString = decrypt($encResponse, $workingKey);
        if ($rcvdString === '') {
            log_message('error', 'CCAvenue response decrypt failed or empty (wrong working key vs gateway).');
            $this->session->set_flashdata(
                'error_message',
                'Could not decrypt the payment response. Ensure the CCAvenue working key in ElintOm matches this merchant account.'
            );
            $this->load_view('payment_declined', $this->theme_view_data(array(
                'error_message' => 'Payment response could not be decrypted.',
            )));
            return;
        }
        $order_status = "";
        $responseMap = [];
        $decryptValues = explode('&', $rcvdString);
        $dataSize = sizeof($decryptValues);
        for ($i = 0; $i < $dataSize; $i++) {
            $information = explode('=', $decryptValues[$i], 2);
            if (isset($information[0]) && $information[0] !== '') {
                $responseMap[$information[0]] = isset($information[1]) ? urldecode($information[1]) : '';
            }
        }

        $order_status = isset($responseMap['order_status']) ? $responseMap['order_status'] : '';

        // 2. If success, persist payment on ElintOm (API mode) or local DB (legacy).
        if ($order_status === 'Success') {
            $oid_map = isset($responseMap['order_id']) ? trim((string) $responseMap['order_id']) : '';
            // If this was a deferred order (TMP_ prefix), create it in ElintOm now.
            if (strpos($oid_map, 'TMP_') === 0) {
                $tmp_placeholder = $oid_map;
                $payload = $this->_resolve_pending_order_payload_for_tmp($tmp_placeholder, $responseMap);
                if ($payload && is_array($payload)) {
                    $real_oid = $this->webshop_api_model->add_order($payload['order'], $payload['products']);
                    if ($real_oid) {
                        $responseMap['order_id'] = $real_oid;
                        $oid_map = (string) $real_oid;
                        $this->_pending_order_payload_cache_delete($payload, $tmp_placeholder);
                    }
                }
            }
            if (strpos($oid_map, 'TMP_') === 0) {
                log_message(
                    'error',
                    'CCAvenue Success but deferred checkout snapshot missing for tmp order_id=' . $oid_map
                );
                $this->session->set_flashdata(
                    'error_message',
                    'Payment was received, but your order could not be created automatically. Please contact support with your transaction reference from the payment page.'
                );
                $this->load_view('payment_declined', $this->theme_view_data(array(
                    'error_message' => 'Payment succeeded, but the order session was lost before completion. Support can reconcile using your gateway receipt.',
                    'payment_gateway_response' => $responseMap,
                )));
                return;
            }

            if ($this->webshop_api_model->uses_elintom_api_for_orders()) {
                $this->webshop_api_model->record_ccavenue_payment_remote($responseMap);
            } else {
                $this->webshop_model->CcavenuePayAfterSale($responseMap);
            }
            $oid_for_wa = isset($responseMap['order_id']) ? trim((string) $responseMap['order_id']) : '';
            if ($oid_for_wa !== '' && ctype_digit($oid_for_wa)) {
                $this->_notify_order_placed_customer((int) $oid_for_wa);
            }
            $success_payload = array('payment_gateway_response' => $responseMap);
            $oid_ok = isset($responseMap['order_id']) ? trim((string) $responseMap['order_id']) : '';
            if ($oid_ok !== '') {
                $orow = $this->webshop_model->get_order_by_id($oid_ok);
                if (!empty($orow) && is_array($orow)) {
                    $success_payload['order'] = $orow;
                    $success_payload['items'] = $this->webshop_model->get_order_items_by_order_id($oid_ok);
                }
            }
            // Payment confirmed — safe to clear the cart and pending-payment snapshot.
            if (isset($_SESSION['cart'])) {
                unset($_SESSION['cart']);
            }
            $this->session->unset_userdata('order_id');
            $this->session->unset_userdata('pending_payment_order');
            $this->session->unset_userdata('pending_order_payload');
            $this->session->unset_userdata('checkout_currency_iso');
            $this->load_view('payment_success', $this->theme_view_data($success_payload));
        } else {
            $declined_oid = isset($responseMap['order_id']) ? trim((string) $responseMap['order_id']) : '';
            $declined_ref = isset($responseMap['reference_no']) ? trim((string) $responseMap['reference_no']) : '';
            $decline_reason = isset($responseMap['status_message']) && $responseMap['status_message'] !== ''
                ? (string) $responseMap['status_message']
                : 'Payment declined at gateway';

            // Mark the order Cancelled so it does not appear as a pending sale in ElintOm.
            if ($declined_oid !== '' || $declined_ref !== '') {
                if ($this->webshop_api_model->uses_elintom_api_for_orders()) {
                    $this->webshop_api_model->cancel_order_remote(
                        (int) $declined_oid,
                        $declined_ref,
                        $decline_reason
                    );
                } elseif (isset($this->db) && $declined_oid !== '') {
                    $this->db->where('id', $declined_oid);
                    $this->db->update('sma_orders', array(
                        'sale_status'    => 'Cancelled',
                        'payment_status' => 'Failed',
                    ));
                }
            }

            // Cart is intentionally preserved so the buyer can retry without
            // re-adding items. Clear only the order-bound session entries.
            $this->session->unset_userdata('order_id');
            $this->session->unset_userdata('pending_payment_order');
            $this->session->unset_userdata('checkout_currency_iso');

            $response_data = array(
                'payment_gateway_response' => $responseMap,
                'error_message' => $decline_reason,
            );
            $this->load_view('payment_declined', $this->theme_view_data($response_data));
        }
    }

    public function payment_ccavRequestHandler($postData = null)
    {
        $this->load->helper('crypto_helper');

        // Prefer credentials injected by the caller (fetched from ElintOm API).
        // Fall back to the local payment_gateways.php config file.
        $ccavenue = array();
        if (is_array($postData) && !empty($postData['API_KEY'])) {
            $ccavenue = array(
                'API_KEY'     => $postData['API_KEY'],
                'ACCESS_CODE' => isset($postData['ACCESS_CODE']) ? $postData['ACCESS_CODE'] : '',
                'MERCHANT_ID' => isset($postData['MERCHANT_ID']) ? $postData['MERCHANT_ID'] : '',
                'API_URL'     => isset($postData['API_URL'])     ? $postData['API_URL']     : '',
            );
        } else {
            $apiCredentials = $this->webshop_model->get_gateway_credentials();
            if (!empty($apiCredentials['ccavenue'])) {
                $ccavenue = $apiCredentials['ccavenue'];
            } else {
                $ci = get_instance();
                $ci->config->load('payment_gateways', TRUE);
                $pc = $ci->config->item('payment_gateways');
                $ccavenue = isset($pc['ccavenue']) ? $pc['ccavenue'] : array();
            }
        }

        // Snapshot merged CCAvenue fields from caller — do not reassign this variable later (closure safety).
        $incoming = is_array($postData) ? $postData : array();
        $getField = function ($key) use ($incoming) {
            if (array_key_exists($key, $incoming)) {
                return $incoming[$key];
            }
            return $this->input->post($key);
        };

        $working_key = !empty($ccavenue['API_KEY'])     ? (string) $ccavenue['API_KEY']     : (string) $this->input->post('API_KEY');
        $access_code = !empty($ccavenue['ACCESS_CODE']) ? (string) $ccavenue['ACCESS_CODE'] : (string) $this->input->post('ACCESS_CODE');
        $api_url     = !empty($ccavenue['API_URL'])     ? (string) $ccavenue['API_URL']     : (string) $this->input->post('API_URL');
        $merchant_id = !empty($ccavenue['MERCHANT_ID']) ? (string) $ccavenue['MERCHANT_ID'] : (string) $this->input->post('MERCHANT_ID');

        if ($working_key === '' || $access_code === '' || $merchant_id === '') {
            $this->session->set_flashdata('error_message', 'CCAvenue configuration is missing.');
            $this->load_view('payment_declined', $this->theme_view_data(array(
                'error_message' => 'CCAvenue configuration is missing.',
            )));
            return;
        }

        $clean = function ($v, $max_len = 500) {
            $v = trim(strip_tags((string) $v));
            if (function_exists('mb_strlen')) {
                return mb_strlen($v) > $max_len ? mb_substr($v, 0, $max_len) : $v;
            }
            return strlen($v) > $max_len ? substr($v, 0, $max_len) : $v;
        };

        // Collect form data (billing_* and date must be non-empty; wrong region currency → CCAvenue 31002).
        $merchant_row = array(
            'integration_type' => 'iframe_normal',
            'reference_no'     => $clean($getField('reference_no'), 120),
            'customer_id'      => $clean($getField('customer_id'), 32),
            'date'             => $clean($getField('date'), 32),
            'language'         => $clean($getField('language'), 8) !== '' ? $clean($getField('language'), 8) : 'EN',
            'amount'           => $clean($getField('amount'), 32),
            'currency'         => $clean($getField('currency'), 8),
            'billing_name'     => $clean($getField('billing_name'), 120),
            'billing_company'  => $clean($getField('billing_company'), 120),
            'billing_address'  => $clean($getField('billing_address'), 240),
            'billing_city'     => $clean($getField('billing_city'), 60),
            'billing_state'    => $clean($getField('billing_state'), 60),
            'billing_country'  => $clean($getField('billing_country'), 60),
            'billing_zip'      => $clean($getField('billing_zip'), 24),
            'billing_tel'      => $clean($getField('billing_tel'), 40),
            'billing_email'    => $clean($getField('billing_email'), 120),
            // Callback URLs must stay literal (no HTML entity encoding of & etc.).
            'redirect_url'     => trim((string) $getField('redirect_url')),
            'cancel_url'       => trim((string) $getField('cancel_url')),
            'merchant_id'      => $merchant_id,
            'order_id'         => $clean($getField('order_id'), 64),
            'merchant_param1'  => $clean($getField('merchant_param1'), 100) !== ''
                ? $clean($getField('merchant_param1'), 100)
                : $clean($getField('reference_no'), 100),
        );

        $bill_fb = array(
            'billing_name'    => 'Customer',
            'billing_company' => '-',
            'billing_address' => 'Address not provided',
            'billing_city'    => 'Dubai',
            'billing_state'   => 'Dubai',
            'billing_country' => 'United Arab Emirates',
            'billing_zip'     => '00000',
            'billing_tel'     => '0500000000',
            'billing_email'   => 'customer@example.com',
        );
        foreach ($bill_fb as $bk => $bv) {
            if (!isset($merchant_row[$bk]) || $merchant_row[$bk] === '') {
                $merchant_row[$bk] = $bv;
            }
        }
        // Whitespace-only counts as empty for CCAvenue (error 21009 billing_country).
        if (isset($merchant_row['billing_country']) && function_exists('preg_replace')) {
            $bcTrim = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', (string) $merchant_row['billing_country']);
            if ($bcTrim === '') {
                $merchant_row['billing_country'] = $bill_fb['billing_country'];
            }
        }
        $merchant_row['billing_country'] = $this->_finalize_ccavenue_country_field(
            $this->_normalize_ccavenue_country_string(
                isset($merchant_row['billing_country']) ? $merchant_row['billing_country'] : ''
            )
        );
        if ($merchant_row['billing_email'] !== '' && !filter_var($merchant_row['billing_email'], FILTER_VALIDATE_EMAIL)) {
            $merchant_row['billing_email'] = $bill_fb['billing_email'];
        }
        if ($merchant_row['date'] === '') {
            $merchant_row['date'] = date('d/m/Y H:i:s');
        }
        // Official CCAvenue error 31002 = invalid currency for merchant/region (not generic "bad field").
        $merchant_row['currency'] = $this->_resolve_ccavenue_currency(isset($merchant_row['currency']) ? $merchant_row['currency'] : '');

        // Many merchant profiles require delivery_* (official missing-parameter codes 21012–21018).
        $merchant_row['delivery_name']     = $merchant_row['billing_name'];
        $merchant_row['delivery_address']  = $merchant_row['billing_address'];
        $merchant_row['delivery_city']     = $merchant_row['billing_city'];
        $merchant_row['delivery_state']    = $merchant_row['billing_state'];
        $merchant_row['delivery_country']  = $merchant_row['billing_country'];
        $merchant_row['delivery_zip']      = $merchant_row['billing_zip'];
        $merchant_row['delivery_tel']      = $merchant_row['billing_tel'];

        $amt_send = (float) str_replace(',', '', (string) $merchant_row['amount']);
        if ($amt_send < 0.01) {
            $this->session->set_flashdata(
                'error_message',
                'Invalid payment amount. Please start again from checkout.'
            );
            $this->load_view('payment_declined', $this->theme_view_data(array(
                'error_message' => 'Invalid payment amount.',
            )));
            return;
        }
        $merchant_row['amount'] = number_format($amt_send, 2, '.', '');

        // Canonical parameter order (CCAvenue samples); billing_country must appear with a non-blank Latin value.
        $ccavenue_key_order = array(
            'merchant_id', 'order_id', 'currency', 'amount', 'redirect_url', 'cancel_url',
            'language', 'billing_name', 'billing_address', 'billing_city', 'billing_state',
            'billing_zip', 'billing_country', 'billing_tel', 'billing_email',
            'delivery_name', 'delivery_address', 'delivery_city', 'delivery_state',
            'delivery_zip', 'delivery_country', 'delivery_tel',
            'integration_type', 'reference_no', 'customer_id', 'date',
        );
        $ordered_row = array();
        foreach ($ccavenue_key_order as $ok) {
            if (array_key_exists($ok, $merchant_row)) {
                $ordered_row[$ok] = $merchant_row[$ok];
            }
        }
        foreach ($merchant_row as $ok => $ov) {
            if (!array_key_exists($ok, $ordered_row)) {
                $ordered_row[$ok] = $ov;
            }
        }
        $merchant_row = $ordered_row;

        // Build the merchant data string
        $merchant_data = '';
        foreach ($merchant_row as $key => $value) {
            $merchant_data .= $key . '=' . urlencode((string) $value) . '&';
        }

        // Encrypt the merchant data
        $encrypted_data = encrypt($merchant_data, $working_key, $merchant_id);
        if ($encrypted_data === '' || $encrypted_data === null) {
            $this->session->set_flashdata('error_message', 'Could not initiate payment encryption. Check CCAvenue working key.');
            $this->load_view('payment_declined', $this->theme_view_data(array(
                'error_message' => 'Payment initiation failed.',
            )));
            return;
        }
        $transaction_url = !empty($api_url) ? $api_url : "https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction";
        $iframe_url = $transaction_url . "&encRequest=" . urlencode($encrypted_data) . "&access_code=" . urlencode($access_code);

        // Output the iframe (correct PHP syntax)
        echo '<iframe src="' . htmlspecialchars($iframe_url) . '" width="100%" height="700" frameborder="0"></iframe>';
        exit;

        $this->load->load_view('mydata', $data);

        // Load the view
        // $this->load_view("mydata", $this->data);
    }

    public function payment_cancel()
    {
        $order_id = $this->session->userdata('order_id');
        $pending  = $this->session->userdata('pending_payment_order');
        $reference_no = (is_array($pending) && isset($pending['reference_no'])) ? (string) $pending['reference_no'] : '';

        // Roll back the order so it does NOT remain in ElintOm as a ghost sale.
        // Two paths:
        //   1. API mode (DB-less storefront) → call ElintOm /webshop_api/cancelorder
        //   2. Legacy local DB mode → mark the row Cancelled directly.
        if ($order_id) {
            if ($this->webshop_api_model->uses_elintom_api_for_orders()) {
                $this->webshop_api_model->cancel_order_remote((int) $order_id, $reference_no, 'Buyer cancelled at payment gateway');
            } elseif (isset($this->db)) {
                $this->db->where('id', $order_id);
                $this->db->update('sma_orders', array(
                    'sale_status'    => 'Cancelled',
                    'payment_status' => 'Failed',
                ));
            }
        }

        // Cart is intentionally preserved here — the buyer should be able to
        // retry payment or edit their cart instead of losing the items.
        $this->session->unset_userdata('order_id');
        $this->session->unset_userdata('pending_payment_order');
        $this->session->unset_userdata('checkout_currency_iso');

        $customer_hint = $this->_get_webshop_session_user_id();
        $cancel_payload = array(
            'error_message' => 'Payment was cancelled. Your cart is still ready for checkout.',
            'order_id'      => $order_id ? $order_id : null,
            'customer_id'   => $customer_hint > 0 ? $customer_hint : null,
        );
        $this->load_view('payment_declined', $this->theme_view_data($cancel_payload));
    }

    /**
     * Standalone declined page (GET) — e.g. Instamojo redirect or bookmarked retry entry.
     * Without this method, _remap may 404 because theme slug views live under plane_vanila_theme, not legacy webshop/.
     *
     * Query: order, customer (optional). Flash error_message from prior redirect is shown when set.
     */
    public function payment_declined()
    {
        $flash = $this->session->flashdata('error_message');
        $order_id_q = $this->input->get('order');
        $payload = array(
            'order_id'    => $order_id_q,
            'customer_id' => $this->input->get('customer'),
        );
        if ($flash !== null && $flash !== '') {
            $payload['error_message'] = $flash;
        }

        // If we landed here with an order id and a pending payment session, roll
        // the order back to Cancelled so it doesn't appear as a live sale in ElintOm.
        $pending = $this->session->userdata('pending_payment_order');
        $reference_no = (is_array($pending) && isset($pending['reference_no'])) ? (string) $pending['reference_no'] : '';
        if ($order_id_q !== null && $order_id_q !== '') {
            if ($this->webshop_api_model->uses_elintom_api_for_orders()) {
                $this->webshop_api_model->cancel_order_remote(
                    (int) $order_id_q,
                    $reference_no,
                    $flash !== null && $flash !== '' ? (string) $flash : 'Payment declined'
                );
            } elseif (isset($this->db)) {
                $this->db->where('id', $order_id_q);
                $this->db->update('sma_orders', array(
                    'sale_status'    => 'Cancelled',
                    'payment_status' => 'Failed',
                ));
            }
            // Cart stays intact; only release the order-bound session pointers.
            $this->session->unset_userdata('order_id');
            $this->session->unset_userdata('pending_payment_order');
            $this->session->unset_userdata('checkout_currency_iso');
        }

        $this->load_view('payment_declined', $this->theme_view_data($payload));
    }

    public function payments()
    {

        $order_id = $this->input->get('order');

        $customer_id = $this->input->get('customer');

        if (!empty($this->input->post('submit'))) {

            if (!empty($this->input->post('payment_gatway'))) {

                switch ($this->input->post('payment_gatway')) {

                    case "ccavenue":
                        // Fetch credentials from ElintOm API and inject into the handler.
                        $ccCreds = array();
                        $gwCreds = $this->webshop_model->get_gateway_credentials();
                        if (!empty($gwCreds['ccavenue'])) {
                            $ccCreds = $gwCreds['ccavenue'];
                        }
                        $posted_oid = $this->input->post('order_id');
                        $posted_cust = (int) $this->input->post('customer_id');
                        $posted_amt = (float) str_replace(',', '', (string) $this->input->post('amount'));
                        if ($posted_amt < 0.01) {
                            $this->session->set_flashdata(
                                'error_message',
                                'Payment amount is missing or zero. Please open the payment link again from checkout or your account orders.'
                            );
                            redirect(base_url('webshop/payments?order=' . urlencode((string) $posted_oid) . '&customer=' . urlencode((string) $posted_cust)));
                            return;
                        }
                        $order_row = $this->webshop_model->get_order_by_id($posted_oid);
                        $billing_cc = $this->_resolve_ccavenue_billing($posted_cust, $order_row);
                        $currency_cc = $this->_resolve_ccavenue_currency($this->input->post('currency'));
                        $posted_ref = trim((string) $this->input->post('reference_no'));
                        $ccHandlerData = array_merge($billing_cc, array(
                            'reference_no'     => $this->input->post('reference_no'),
                            'merchant_param1'  => $posted_ref,
                            'customer_id'      => $this->input->post('customer_id'),
                            'amount'           => number_format($posted_amt, 2, '.', ''),
                            'order_id'         => $this->input->post('order_id'),
                            'redirect_url'     => base_url('webshop/payment_ccavResponseHandler'),
                            'cancel_url'       => base_url('webshop/payment_cancel'),
                            'language'         => 'EN',
                            'currency'         => $currency_cc,
                            'date'             => date('d/m/Y H:i:s'),
                            'API_KEY'          => isset($ccCreds['API_KEY'])     ? $ccCreds['API_KEY']     : '',
                            'ACCESS_CODE'      => isset($ccCreds['ACCESS_CODE']) ? $ccCreds['ACCESS_CODE'] : '',
                            'MERCHANT_ID'      => isset($ccCreds['MERCHANT_ID']) ? $ccCreds['MERCHANT_ID'] : '',
                            'API_URL'          => isset($ccCreds['API_URL'])     ? $ccCreds['API_URL']     : '',
                        ));
                        $this->payment_ccavRequestHandler($ccHandlerData);
                        break;

                    case "instamojo":

                        $pay_result = $this->webshop_model->instamojoEshop($_POST);


                        if (isset($pay_result['longurl']) && !empty($pay_result['longurl'])):
                            redirect($pay_result['longurl'], 'refresh');

                        else:
                            $this->webshop_model->deleteSale($order_id);
                            $result['msg'] = $this->instamojo_error($pay_result['error']);
                            $this->session->set_flashdata('error_message', $result['msg']);
                            redirect(base_url('webshop/payment_declined?order=' . urlencode($order_id) . '&customer=' . urlencode($customer_id)));
                            return;
                        endif;


                        break;

                    case 'paytm':
                        $paytmpayment['order_id'] = $_POST['order_id'];
                        $paytmpayment['amount'] = $_POST['amount'];
                        $paytmpayment['userid'] = $_POST['customer_id'];

                        //                        $this->Orders_Emails($orderData);
                        //                        $this->Shopkeeper_Emails($orderData);
                        $this->paytm_init($paytmpayment);

                        break;

                    case 'razorpay':
                        $this->razorpay_init($_POST);
                        //                            echo 'Razorpay';
                        break;

                    case 'stripe':
                        // Stripe gateway selected but backend checkout is not yet configured here.
                        // Fail safely instead of marking order as success without payment.
                        $this->session->set_flashdata('error_message', 'Stripe payment is not configured. Please choose another payment method.');
                        redirect(base_url('webshop/payments?order=' . urlencode($order_id) . '&customer=' . urlencode($customer_id)));
                        return;






                    case 'cod':
                        // Cash on delivery: complete without online payment.
                        redirect(base_url('webshop/order_success?order=' . urlencode($order_id) . '&customer=' . urlencode($customer_id)));
                        return;
                    default:
                        $this->session->set_flashdata('error_message', 'Invalid payment method selected.');
                        redirect(base_url('webshop/payments?order=' . urlencode($order_id) . '&customer=' . urlencode($customer_id)));
                        return;
                } //end switch
            }
        } else {

            // Fetch gateway credentials + enabled flags from ElintOm API.
            // Falls back to the local payment_gateways.php config if the API call fails.
            $apiCredentials = $this->webshop_model->get_gateway_credentials();
            if (!empty($apiCredentials)) {
                // Merge API data into the expected flat config format.
                $this->data['payment_config'] = $apiCredentials;
            } else {
                // API unavailable — fall back to local config file.
                $this->data['payment_config'] = array();
                if (file_exists(APPPATH . 'config/payment_gateways.php')) {
                    $ci = get_instance();
                    $ci->config->load('payment_gateways', true);
                    $this->data['payment_config'] = $ci->config->item('payment_gateways');
                }
            }

            $this->data['customer_id'] = $customer_id;

            $order = $this->webshop_model->get_order_by_id($order_id);

            // Authoritative fallback total: submit_order() snapshotted the computed
            // grand_total into session right after the order was created. Use that
            // when the API order row isn't readable yet (API mode + race), so the
            // payment screen never shows 0 and the wrong amount never reaches the gateway.
            $pending = $this->session->userdata('pending_payment_order');
            $pending_total = (is_array($pending) && isset($pending['grand_total'])) ? (float) $pending['grand_total'] : 0.0;
            $pending_ref   = (is_array($pending) && isset($pending['reference_no'])) ? (string) $pending['reference_no'] : '';

            // Orders created via Elintom API mode are not mirrored to the local DB.
            // Build a minimal stub from URL/session data so the payment page still renders.
            if (empty($order) || !is_array($order)) {
                $grand_total_stub = $pending_total;
                // Fall back to cart subtotal from the session if the snapshot is missing.
                if ($grand_total_stub <= 0 && isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                    foreach ($_SESSION['cart'] as $ci) {
                        $p = isset($ci['product_price']) && (float) $ci['product_price'] > 0
                            ? (float) $ci['product_price']
                            : (isset($ci['price']) ? (float) $ci['price'] : 0.0);
                        $grand_total_stub += $p * (float) (isset($ci['quantity']) ? $ci['quantity'] : 1);
                    }
                }
                $order = array(
                    'id'                 => $order_id,
                    'reference_no'       => $pending_ref !== '' ? $pending_ref : 'ES-' . $order_id,
                    'grand_total'        => $grand_total_stub,
                    'billing_address_id' => null,
                );
            } elseif ($pending_total > 0) {
                // Even when the API returned the order, prefer the snapshot if the API
                // row was created with grand_total=0 (legacy POS configurations) so the
                // amount sent to the gateway always matches what the buyer saw at checkout.
                if (!isset($order['grand_total']) || (float) $order['grand_total'] <= 0) {
                    $order['grand_total'] = $pending_total;
                }
            }

            $this->data['order'] = $order;

            $this->data['payments_gatway'] = $this->webshop_model->get_payment_gatways();

            $billing_address_id = isset($order['billing_address_id']) ? $order['billing_address_id'] : null;
            $this->data['billing_address'] = $billing_address_id
                ? $this->webshop_model->get_address_by_id($billing_address_id)
                : array();

            $this->data['payment_currency_iso'] = $this->resolve_payment_currency_iso();

            /* echo "###################";
              print_r($this->data['payments_gatway']);
              echo "<br/>webshop_settings:";
              print_r($this->webshop_settings);
              echo "<br/>order Data:";
              print_r($this->data['order']);
              echo "<br/>billing_address:";
              print_r($this->data['billing_address']);
              echo '</pre>'; */

            $this->load_view("payments", $this->data);
        }
    }

    /**
     * WhatsApp: single entry for checkout + order_success retry (API mode).
     * Skips if wa_notify_done_{id} session flag set after a successful send.
     * See cheerio_whatsapp_helper.php header for full chain to ElintOm/Cheerio.
     */
    private function _attempt_order_whatsapp_notify($order_id)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0 || !$this->webshop_api_model->uses_elintom_api_for_orders()) {
            return false;
        }
        if ($this->session->userdata('wa_notify_done_' . $order_id)) {
            return false;
        }
        try {
            $wa_res = $this->webshop_api_model->notify_order_placed_whatsapp_remote($order_id, 'true');
            if (is_object($wa_res) && !empty($wa_res->whatsapp_sent)) {
                $this->session->set_userdata('wa_notify_done_' . $order_id, 1);
                return true;
            }
        } catch (\Throwable $e) {
            log_message('error', 'WhatsApp notify failed for order ' . $order_id . ': ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Post-checkout WhatsApp (ElintOm / Cheerio) + confirmation email. Sets flash for order_success view.
     *
     * @param int         $order_id
     * @param array|object|null $order_row Optional order row with billing_address_id for legacy Cheerio
     */
    private function _notify_order_placed_customer($order_id, $order_row = null)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return;
        }
        $attempted = false;
        try {
            if ($this->webshop_api_model->uses_elintom_api_for_orders()) {
                $this->_attempt_order_whatsapp_notify($order_id);
                $attempted = true;
            } elseif (isset($this->db) && $order_row !== null) {
                // Legacy: local DB + Whatsapp_model (no ElintOm HTTP). Prefer API mode in production.
                $billing_id = null;
                if (is_array($order_row) && isset($order_row['billing_address_id'])) {
                    $billing_id = $order_row['billing_address_id'];
                } elseif (is_object($order_row) && isset($order_row->billing_address_id)) {
                    $billing_id = $order_row->billing_address_id;
                }
                if ($billing_id) {
                    $wa_customer = $this->getShippingAddress($billing_id);
                    if (is_object($wa_customer) && !empty($wa_customer->phone)) {
                        $country_code = $this->getcountryCode($wa_customer->country);
                        $this->call_whatsapp_cheerio($country_code . $wa_customer->phone, $order_id, 'true');
                        $attempted = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'WhatsApp notify failed for order ' . $order_id . ': ' . $e->getMessage());
        }
        try {
            if ($this->webshop_api_model->uses_elintom_api_for_orders()) {
                $this->webshop_api_model->notify_order_placed_email_remote($order_id);
                $attempted = true;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Email notify failed for order ' . $order_id . ': ' . $e->getMessage());
        }
        if ($attempted) {
            $this->session->set_flashdata(
                'order_notify_hint',
                'Order confirmation and updates will be sent via WhatsApp, SMS, and/or email when available on your account.'
            );
        }
    }

    public function order_success()
    {
        $order_id = $this->input->get('order');
        $order_id_int = (int) $order_id;

        // Retry WhatsApp if checkout notify failed before redirect (same path as _notify_order_placed_customer).
        if ($order_id_int > 0) {
            $this->_attempt_order_whatsapp_notify($order_id_int);
        }

        $this->data['order'] = $this->webshop_model->get_order_by_id($order_id);
        $this->data['items'] = $this->webshop_model->get_order_items_by_order_id($order_id);
        $this->data['order_notify_hint'] = $this->session->flashdata('order_notify_hint');
        if (empty($this->data['order']) || !is_array($this->data['order'])) {
            // API-only fallback so success page does not break when local DB rows are absent.
            $this->data['order'] = array(
                'id' => $order_id,
                'reference_no' => 'ES-' . $order_id,
                'grand_total' => 0,
            );
        }
        if (empty($this->data['items']) || !is_array($this->data['items'])) {
            $this->data['items'] = array();
        }

        // Prefer checkout snapshot from submit_order (matches cart totals; not ElintOm Eshop controller).
        $success_display = $this->session->flashdata('order_success_display');
        if (is_array($success_display)) {
            if (!empty($success_display['order']) && is_array($success_display['order'])) {
                $this->data['order'] = array_merge($this->data['order'], $success_display['order']);
                if (isset($success_display['order']['grand_total']) && (float) $success_display['order']['grand_total'] > 0) {
                    $this->data['order']['grand_total'] = $success_display['order']['grand_total'];
                }
                if (!empty($success_display['order']['reference_no'])) {
                    $this->data['order']['reference_no'] = $success_display['order']['reference_no'];
                }
            }
            if (!empty($success_display['items']) && is_array($success_display['items'])) {
                $this->data['items'] = $success_display['items'];
            }
        }

        if (function_exists('webshop_normalize_order_payload') && !empty($this->data['items'])) {
            $normalized = webshop_normalize_order_payload(
                is_array($this->data['order']) ? $this->data['order'] : array(),
                $this->data['items']
            );
            $this->data['order'] = $normalized['order'];
            $this->data['items'] = $normalized['items'];
            if (function_exists('webshop_order_grand_total_amount')) {
                $recomputed = webshop_order_grand_total_amount($this->data['order'], $this->data['items']);
                if ($recomputed > 0) {
                    $this->data['order']['grand_total'] = $recomputed;
                }
            }
        }

        // Clear the cart once the order is confirmed.
        if (isset($_SESSION['cart'])) {
            unset($_SESSION['cart']);
        }
        $this->session->unset_userdata('order_id');
        $this->session->unset_userdata('pending_payment_order');
        $this->session->unset_userdata('checkout_currency_iso');
        $this->session->unset_userdata('pending_order_payload');

        if (isset($this->webshop_settings->webshop_theme) && $this->webshop_settings->webshop_theme == 'restaurant') {
            redirect('webshop?order_status=success');
            return;
        }

        $this->load_view("order_success", $this->data);
    }

    public function my_orders()
    {
        redirect('webshop/your_orders');
    }

    private function set_theme_sections_data($themeSections)
    {

        if (!is_array($themeSections)) {
            return false;
        }

        foreach ($themeSections as $sections) {

            switch ($sections->section_name) {

                case 'section_subcategory_tabs_multiple_sections':

                    $sectionRaw = !empty($sections->section_data) ? @unserialize($sections->section_data) : null;
                    $sectionData = is_string($sectionRaw) ? json_decode($sectionRaw, TRUE) : null;
                    if (!is_array($sectionData)) {
                        $sectionData = [];
                    }

                    $this->data[$sections->section_name]['section_titles'] = isset($sectionData['section_titles']) ? $sectionData['section_titles'] : [];
                    $this->data[$sections->section_name]['section_tab_categories'] = isset($sectionData['section_tab_categories']) ? $sectionData['section_tab_categories'] : [];

                    $category_products = $this->webshop_model->get_category_tab_products($this->data[$sections->section_name]['section_tab_categories']);

                    $this->data[$sections->section_name]['section_products'] = $category_products;

                    break;

                case 'section_category_exclusive_products':
                case 'section_category_tab_right_highlite_products':
                case 'section_category_tab_vertical_align':
                case 'section_category_tab_center_align':
                case 'section_category_tab_right_align':
                case 'section_category_tab_left_align':

                    $sectionRaw = !empty($sections->section_data) ? @unserialize($sections->section_data) : null;
                    $sectionData = is_string($sectionRaw) ? json_decode($sectionRaw, TRUE) : null;
                    if (!is_array($sectionData)) {
                        $sectionData = [];
                    }

                    $this->data[$sections->section_name]['section_titles'] = $sections->section_title;
                    $this->data[$sections->section_name]['section_tabs'] = isset($sectionData['tabs']) ? $sectionData['tabs'] : [];
                    $this->data[$sections->section_name]['section_products'] = $this->webshop_model->get_tab_products_by_id(isset($sectionData['products']) ? $sectionData['products'] : []);

                    if (isset($sectionData['highlite'])) {
                        $this->data[$sections->section_name]['section_products_highlite'] = $this->webshop_model->get_tab_products_by_id($sectionData['highlite']);
                    }

                    //                    echo '<pre>';
                    //                    print_r($this->data[$sections->section_name]);
                    //                    echo '</pre>'; 

                    break;

                case 'section_fullwidth_notice':

                    $this->data['section_fullwidth_notice'] = $sections->section_data;
                    break;

                case 'section_top_categories':

                    $this->data['section_top_categories'] = $sections->section_data;
                    break;


                default:
                    break;
            } //end switch
        } //end foreach
    }

    /**
     * JSON response via CI output (avoids raw echo per codeigniter3-conventions).
     *
     * @param array|object $payload
     * @param int          $http_code HTTP status (default 200)
     */
    private function json_response($payload, $http_code = 200)
    {
        if (!is_array($payload) && !is_object($payload)) {
            $payload = array('status' => 'FAIL', 'error' => 'Invalid response payload');
        }
        if (is_array($payload) && $this->config->item('csrf_protection')) {
            $payload['csrf_name'] = $this->security->get_csrf_token_name();
            $payload['csrf_hash'] = $this->security->get_csrf_hash();
        }
        $code = (int) $http_code;
        if ($code !== 200) {
            $this->output->set_status_header($code);
        }
        $this->output
            ->set_content_type('application/json', 'UTF-8')
            ->set_output(json_encode($payload));
    }

    private function post_int($data, $key, $default = 0)
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return (int) $default;
        }
        return (int) $data[$key];
    }

    private function post_float($data, $key, $default = 0.0)
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return (float) $default;
        }
        return (float) $data[$key];
    }

    private function post_string($data, $key, $default = '')
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return (string) $default;
        }
        return trim((string) $data[$key]);
    }

    private function ensure_cart_session()
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = array();
        }
    }

    private function cart_totals_from_session()
    {
        $this->ensure_cart_session();
        $subtotal = 0.0;
        foreach ($_SESSION['cart'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $subtotal += (float) (isset($item['product_price']) ? $item['product_price'] : 0) * (float) (isset($item['quantity']) ? $item['quantity'] : 0);
        }
        return array(
            'count' => count($_SESSION['cart']),
            'total' => number_format($subtotal, 2),
        );
    }

    public function add_to_wishlist($postData)
    {
        $user_id = $this->_get_webshop_session_user_id();
        $result = $this->webshop_action_engine->add_to_wishlist((array) $postData, $user_id);
        $this->json_response($result);
    }

    public function remove_from_wishlist($postData)
    {
        $user_id = $this->_get_webshop_session_user_id();
        $result = $this->webshop_action_engine->remove_from_wishlist((array) $postData, $user_id);
        $this->json_response($result);
    }

    public function add_to_cart($postData)
    {
        $result = $this->webshop_action_engine->add_to_cart((array) $postData);
        $this->json_response($result);
    }

    public function update_cart($postData)
    {
        $result = $this->webshop_action_engine->update_cart((array) $postData);
        // Keep legacy plain-text response for older storefront JS.
        echo (isset($result['status']) && $result['status'] === 'SUCCESS') ? 'SUCCESS' : 'FAIL';
    }

    public function buy_now($postData)
    {
        $result = $this->webshop_action_engine->buy_now((array) $postData);
        if (isset($result['status']) && $result['status'] === 'SUCCESS') {
            $result['checkout_url'] = base_url('webshop/checkout');
        }
        $this->json_response($result);
    }

    public function load_header_cart_items()
    {

        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            $this->data['cart_data'] = $this->webshop_model->get_cart_data();
            if (function_exists('webshop_enrich_cart_session_prices') && is_array($this->data['cart_data'])) {
                $pm = isset($this->data['cart_data']['products']) && is_array($this->data['cart_data']['products'])
                    ? $this->data['cart_data']['products']
                    : array();
                $pm = webshop_enrich_cart_session_prices($pm);
                $this->data['cart_data']['products'] = $pm;
            }
            $this->data['cart_items'] = $_SESSION['cart'];

            $this->load_view("headers/header_cart_items", $this->data);
        } else {
            echo 'EMPTY';
        }
    }

    public function remove_cart_item($postData)
    {

        // var_dump($postData);
        // exit;

        if (isset($_SESSION['cart'][$postData['cart_item_key']]) && !empty($_SESSION['cart'])) {
            unset($_SESSION['cart'][$postData['cart_item_key']]);
            $this->data['cart_items'] = $_SESSION['cart'];
            $this->data['cart_data'] = $this->webshop_model->get_cart_data();

            if ($postData['action_source'] == "cart_page") {
                $this->load_view("cart_content", $this->data);
            } else {
                $this->load_view("headers/header_cart_items", $this->data);
            }
        }
    }

    /**
     * JSON cart payload for the gulfpharmacy mini-cart drawer.
     * Reuses the same $_SESSION['cart'] + webshop_model->get_cart_data() shape
     * already powering cart_view.php; only the response format differs (JSON, not HTML).
     */
    public function mini_cart()
    {
        $cart_items = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array();
        $this->json_response($this->_build_mini_cart_payload($cart_items));
    }

    public function mini_cart_remove($postData)
    {
        $postData = is_array($postData) ? $postData : array();
        $key = isset($postData['cart_item_key']) ? (string) $postData['cart_item_key'] : '';
        if ($key !== '' && isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
        }
        $cart_items = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array();
        $this->json_response($this->_build_mini_cart_payload($cart_items));
    }

    /**
     * GET JSON for Gulf Pharmacy header mini-cart drawer (header-drawers.js).
     * Aliases fields expected by the drawer (url, qty, price_formatted, subtotal_formatted).
     */
    public function get_cart_json()
    {
        $cart_items = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array();
        $base = $this->_build_mini_cart_payload($cart_items);
        if (!is_array($base)) {
            $base = array(
                'status'   => 'success',
                'count'    => 0,
                'subtotal' => 0,
                'items'    => array(),
            );
        }
        $out = array(
            'status'               => isset($base['status']) ? $base['status'] : 'success',
            'count'                => isset($base['count']) ? (int) $base['count'] : 0,
            'subtotal'             => isset($base['subtotal']) ? (float) $base['subtotal'] : 0.0,
            'subtotal_fmt'         => isset($base['subtotal_fmt']) ? $base['subtotal_fmt'] : '',
            'subtotal_formatted'   => isset($base['subtotal_fmt']) ? $base['subtotal_fmt'] : '',
            'currency'             => isset($base['currency']) ? $base['currency'] : '$',
            'items'                => array(),
            'view_cart_url'        => isset($base['view_cart_url']) ? $base['view_cart_url'] : base_url('webshop/cart'),
            'checkout_url'         => isset($base['checkout_url']) ? $base['checkout_url'] : base_url('webshop/checkout'),
        );
        if (!empty($base['items']) && is_array($base['items'])) {
            foreach ($base['items'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = isset($row['product_id']) ? (int) $row['product_id'] : 0;
                $hash = $pid > 0 ? md5((string) $pid) : '';
                $url = $hash !== '' ? base_url('webshop/product_details/' . rawurlencode($hash)) : $out['view_cart_url'];
                $qty = isset($row['quantity']) ? (int) $row['quantity'] : 0;
                $priceFmt = isset($row['line_total_fmt']) ? $row['line_total_fmt'] : (isset($row['unit_price_fmt']) ? $row['unit_price_fmt'] : '');
                $out['items'][] = array_merge($row, array(
                    'url'               => $url,
                    'qty'               => $qty,
                    'price_formatted'   => $priceFmt,
                ));
            }
        }
        $this->json_response($out);
    }

    private function _build_mini_cart_payload(array $cart_items)
    {
        $checkout_url  = base_url('webshop/checkout');
        $view_cart_url = base_url('webshop/cart');
        $symbol = (isset($this->Settings) && is_object($this->Settings) && !empty($this->Settings->symbol))
            ? (string) $this->Settings->symbol
            : '$';

        if (empty($cart_items)) {
            return array(
                'status'        => 'success',
                'count'         => 0,
                'subtotal'      => 0,
                'subtotal_fmt'  => $symbol . ' 0.00',
                'currency'      => $symbol,
                'items'         => array(),
                'view_cart_url' => $view_cart_url,
                'checkout_url'  => $checkout_url,
            );
        }

        $cart_data = $this->webshop_model->get_cart_data();
        $products = (is_array($cart_data) && isset($cart_data['products']) && is_array($cart_data['products'])) ? $cart_data['products'] : array();
        $variants = (is_array($cart_data) && isset($cart_data['variants']) && is_array($cart_data['variants'])) ? $cart_data['variants'] : array();

        $uploadsBase = isset($this->data['uploads']) ? (string) $this->data['uploads'] : '';
        $thumbsBase  = isset($this->data['thumbs'])  ? (string) $this->data['thumbs']  : '';

        $items = array();
        $subtotal = 0.0;
        foreach ($cart_items as $key => $item) {
            if (!is_array($item)) { continue; }
            $pid = (int) (isset($item['product_id']) ? $item['product_id'] : 0);
            $vid = (int) (isset($item['variant_id']) ? $item['variant_id'] : 0);
            $qty = (int) (isset($item['quantity']) ? $item['quantity'] : 0);
            $prow = isset($products[$pid]) ? $products[$pid] : array();
            $fallback_delta = ($vid > 0 && isset($item['variant_price'])) ? (float) $item['variant_price'] : null;
            $cart_unit = (float) (isset($item['product_price']) ? $item['product_price'] : (isset($item['price']) ? $item['price'] : 0));
            $resolved = array('unit_price' => $cart_unit, 'variant_price' => $fallback_delta !== null ? $fallback_delta : 0.0);
            if (function_exists('webshop_resolve_variant_line_price')) {
                $resolved = webshop_resolve_variant_line_price($prow, $vid, $fallback_delta, $cart_unit, $cart_unit);
            } elseif ($cart_unit <= 0 && is_array($prow) && !empty($prow) && function_exists('webshop_checkout_resolve_product_price')) {
                $resolved['unit_price'] = (float) webshop_checkout_resolve_product_price($prow, 0, 0);
            }
            $unit_price = isset($resolved['unit_price']) ? (float) $resolved['unit_price'] : $cart_unit;
            if ($unit_price > 0 && isset($_SESSION['cart'][$key]) && is_array($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['product_price'] = $unit_price;
                $_SESSION['cart'][$key]['price'] = $unit_price;
                if ($vid > 0 && isset($resolved['variant_price'])) {
                    $_SESSION['cart'][$key]['variant_price'] = (float) $resolved['variant_price'];
                }
            }
            $line = $unit_price * $qty;
            $subtotal += $line;
            $name = isset($prow['name']) ? (string) $prow['name'] : 'Product';
            $variant_name = '';
            if (function_exists('webshop_cart_line_variant_label')) {
                $variant_name = webshop_cart_line_variant_label($item, is_array($prow) ? $prow : array(), $variants);
                if ($variant_name !== '' && isset($_SESSION['cart'][$key]) && is_array($_SESSION['cart'][$key])) {
                    $_SESSION['cart'][$key]['variant_name'] = $variant_name;
                }
            } elseif ($vid && isset($variants[$vid]['name'])) {
                $variant_name = (string) $variants[$vid]['name'];
            }
            $image_url = '';
            if (!empty($prow) && function_exists('webshop_product_image_src')) {
                $image_url = webshop_product_image_src($uploadsBase, $thumbsBase, $prow);
            }

            $items[] = array(
                'item_key'       => (string) $key,
                'product_id'     => $pid,
                'variant_id'     => $vid,
                'name'           => $name,
                'variant_name'   => $variant_name,
                'image'          => $image_url,
                'quantity'       => $qty,
                'unit_price'     => $unit_price,
                'unit_price_fmt' => $symbol . ' ' . number_format($unit_price, 2),
                'line_total'     => $line,
                'line_total_fmt' => $symbol . ' ' . number_format($line, 2),
            );
        }

        return array(
            'status'        => 'success',
            'count'         => count($items),
            'subtotal'      => $subtotal,
            'subtotal_fmt'  => $symbol . ' ' . number_format($subtotal, 2),
            'currency'      => $symbol,
            'items'         => $items,
            'view_cart_url' => $view_cart_url,
            'checkout_url'  => $checkout_url,
        );
    }

    public function set_product_gallery($postData)
    {

        $this->data['gallary_images'] = $this->webshop_model->get_product_images($postData['product_id'], $postData['variant_id']);

        $this->load_view("product_single_item_gallery", $this->data);
    }

    public function get_sections($postData)
    {


        $section_name = $postData['section'];

        switch ($section_name) {

            case 'section_features_list':

                $this->data['features'] = $this->webshop_model->get_features();
                if (is_array($this->data['features'])) {
                    $this->load_view("sections/section_features_list", $this->data);
                }
                break;

            case 'section_top_categories':

                $this->load_view("sections/section_top_categories", $this->data);
                break;


            default:
                break;
        } //end switch
    }

    public function storeInfo()
    {
        if (!$this->input->is_cli_request() && strtolower($this->router->fetch_method()) === 'storeinfo') {
            show_404();
            return false;
        }

        $this->load->model('settings_model');
        if (!isset($this->eshop_model) || !is_object($this->eshop_model)) {
            return false;
        }

        $res = $this->eshop_model->getPosSettings();
        if (!is_object($res)) {
            return false;
        }

        $config = $this->config;
        $merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : null;
        $res->merchant_phone = $merchant_phone;

        $data = array();
        foreach ($res as $key => $value) {
            $data[$key] = $value;
        }
        return $data;

        //return $storeInfo = $this->shop_model->storeDetails();
    }

    // public function login() {

    //     if ($this->input->post('submit_login')) {

    //         $username = $this->input->post('webshop_username');
    //         $passwdHash = md5($this->input->post('webshop_password'));
    //         $return_page = $this->input->post('return_page');
    //         $data['phone'] = $this->input->post('phone');
    //         $logdata['first'] = $this->input->post('first');
    //         $logdata['last']  = $this->input->post('last');

    //         $authData = $this->webshop_model->authenticate_user($username, $passwdHash);

    //         if (!empty($authData)) {
    //             $authData->user_id = $authData->id;
    //             $authData->is_login = TRUE;
    //             if (isset($_SESSION['cart'])) {
    //                 $this->session->cart = $_SESSION['cart'];
    //             } else {
    //                 unset($_SESSION['cart']);
    //             }
    //             $this->session->webshop = $authData;
    //             redirect($return_page . "?msg=auth_success");
    //             $register_session_data = array(
    //                         'user_id'       => $authData->id,
    //                         'first'          => $logdata['first'],
    //                         'last'          => $logdata['last'],
    //                         'email'         => $data['email'],
    //                         'phone'         => $data['phone'],
    //                     );
    //             $this->session->set_userdata('customer_register', $register_session_data);
    //             redirect($return_page . "?msg=webshop/index");
    //         } else {
    //             redirect("webshop/login?msg=error");
    //         }
    //     } else {
    //         if ($this->session->webshop->is_login && $this->session->webshop->user_id) {
    //             redirect("webshop/index");
    //         }

    //         $this->data['return_page'] = str_replace(base_url(), '', $_SERVER['HTTP_REFERER']);
    //         if ($this->webshop_settings->webshop_theme == 'restaurant') {
    //             $this->load_view("webshop_restaurant_t1/sign_in.php", $this->data);
    //         } else {
    //             $this->load_view("login_registration", $this->data);
    //         }  
    //     }
    // }
    public function login()
    {
        $theme = $this->webshop_settings->webshop_theme;

        // Treat any POST to /webshop/login as a login attempt. The modern
        // "Welcome Back" form (components/login_form.php) posts identity+password
        // and a "submit" button; the legacy two-column view posts phone +
        // webshop_password + submit_login. Reading by method instead of one
        // button name keeps both working — without this the modern form's POST
        // silently fell through to the render branch and the page only refreshed.
        $is_login_post = (strtoupper($this->input->method(true)) === 'POST');

        if ($is_login_post) {
            // identity = email OR phone (ElintOm logincheck accepts either).
            $identity_raw = $this->input->post('identity');
            if ($identity_raw === false || $identity_raw === null || trim((string) $identity_raw) === '') {
                $identity_raw = $this->input->post('phone');
            }
            $login_input = trim((string) $identity_raw);

            $password_raw = $this->input->post('password');
            if ($password_raw === false || $password_raw === null || $password_raw === '') {
                $password_raw = $this->input->post('webshop_password');
            }
            $plainPassword = (string) $password_raw;

            $return_page = trim((string) $this->input->post('return_page'));
            // Don't loop back to the login page on success.
            if ($return_page === '' || stripos($return_page, 'webshop/login') !== false) {
                $return_page = site_url('webshop');
            } elseif (strpos($return_page, 'http') !== 0) {
                $return_page = site_url(ltrim($return_page, '/'));
            }

            if ($login_input === '' || $plainPassword === '') {
                $this->session->set_flashdata('toast_error', 'Please enter your email or mobile number and password.');
                redirect('webshop/login');
                return;
            }

            $authData = $this->webshop_model->authenticate_user_password($login_input, $plainPassword);

            if (!empty($authData)) {
                $authData = is_object($authData) ? $authData : (object) $authData;
                $authData->user_id = isset($authData->id) ? $authData->id : (isset($authData->user_id) ? $authData->user_id : 0);
                $authData->is_login = TRUE;
                if (isset($_SESSION['cart'])) {
                    $this->session->cart = $_SESSION['cart'];
                    $return_page = site_url('webshop/checkout');
                } else {
                    unset($_SESSION['cart']);
                }
                $this->session->webshop = $authData;
                $auth_name = isset($authData->name) ? $authData->name : '';
                $name_parts = explode(' ', trim($auth_name), 2);
                $first_name = $name_parts[0];
                $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                $register_session_data = array(
                    'user_id'       => $authData->user_id,
                    'first'         => $first_name,
                    'last'          => $last_name,
                    'email'         => isset($authData->email) ? $authData->email : '',
                    'phone'         => isset($authData->phone) ? $authData->phone : '',
                );
                $this->session->set_userdata('customer_register', $register_session_data);
                if ($theme == 'restaurant' || $theme == 'nw') {
                    redirect('webshop?msg=login_success');
                    return;
                }
                redirect($return_page . (strpos($return_page, '?') === false ? '?' : '&') . 'msg=auth_success');
                return;
            }

            // Failure path — flashdata is read by both the modern and legacy
            // login views (toast_error / flash_err). Redirect (PRG) so a browser
            // refresh after the error doesn't re-POST the credentials.
            $this->session->set_flashdata('toast_error', 'Invalid email/mobile or password. Please try again.');
            redirect('webshop/login');
            return;
        }

        // GET — render the login page (or send the user away if already signed in).
        $ws_sess = $this->session->userdata('webshop');
        $is_login = false;
        if ($ws_sess) {
            $is_login = is_object($ws_sess)
                ? (isset($ws_sess->is_login) && $ws_sess->is_login)
                : (isset($ws_sess['is_login']) && $ws_sess['is_login']);
        }

        if ($is_login) {
            redirect('webshop/index');
            return;
        }
        $return_page = '';
        if (isset($_GET['return_page']) && trim((string) $_GET['return_page']) !== '') {
            $return_page = trim((string) $_GET['return_page']);
        } elseif (isset($_SERVER['HTTP_REFERER']) && trim((string) $_SERVER['HTTP_REFERER']) !== '') {
            $return_page = str_replace(base_url(), '', (string) $_SERVER['HTTP_REFERER']);
        }
        $this->data['return_page'] = $return_page;
        $this->data['website_setting'] = $this->webshop_model->get_website_setting();
        $this->data['validated'] = null;
        $this->load_view('login', $this->data);
    }
    public function logout()
    {
        $this->session->set_flashdata('message', 'You have been logged out successfully.');

        unset($_SESSION['cart']);
        unset($_SESSION['webshop']);

        redirect("webshop");
    }

    public function register()
    {
        $theme = isset($this->webshop_settings->webshop_theme) ? $this->webshop_settings->webshop_theme : '';

        if ($this->input->post('submit_register')) {

            $first  = trim($this->input->post('first'));
            $last   = trim($this->input->post('last'));
            $email  = trim($this->input->post('email'));
            $phone  = trim($this->input->post('phone'));
            if (function_exists('webshop_phone_digit_variants')) {
                $reg_dial = function_exists('webshop_settings_phone_dial_code') ? webshop_settings_phone_dial_code() : '91';
                $reg_local = function_exists('webshop_settings_local_phone_length') ? webshop_settings_local_phone_length() : 10;
                $reg_variants = webshop_phone_digit_variants($phone, $reg_dial, $reg_local);
                if (!empty($reg_variants)) {
                    $phone = $reg_variants[0];
                }
            }
            $passwd = $this->input->post('passwd');
            $passwd_confirm = $this->input->post('passwd_confirm');

            // --- Server-side validation ---
            $errors = array();
            if (empty($first))  $errors[] = 'First name is required.';
            if (empty($last))   $errors[] = 'Last name is required.';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid email address is required.';
            }
            if (empty($phone) || strlen(preg_replace('/\D/', '', $phone)) < 7) {
                $errors[] = 'A valid phone number is required.';
            }
            if (empty($passwd) || strlen($passwd) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
            if ($passwd !== $passwd_confirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (!empty($errors)) {
                $this->session->set_flashdata('toast_error', implode(' ', $errors));
                $this->session->set_flashdata('reg_first', $first);
                $this->session->set_flashdata('reg_last',  $last);
                $this->session->set_flashdata('reg_email', $email);
                $this->session->set_flashdata('reg_phone', $phone);
                redirect(current_url());
                return;
            }

            // --- Duplicate phone / email check via API ---
            $dupCheck = $this->webshop_model->register_check($phone, $email);
            if (!empty($dupCheck->dup_phone)) {
                $this->session->set_flashdata('toast_error', 'An account already exists for this mobile number.');
                $this->_flash_register_inputs($first, $last, $email, $phone);
                redirect(current_url());
                return;
            }
            if (!empty($dupCheck->dup_email)) {
                $this->session->set_flashdata('toast_error', 'An account already exists for this email address.');
                $this->_flash_register_inputs($first, $last, $email, $phone);
                redirect(current_url());
                return;
            }

            // --- Build customer data (send plain password; ElintOm will md5 it) ---
            $customerData = array(
                'name'                => $first . ' ' . $last,
                'email'               => $email,
                'phone'               => $phone,
                'password'            => $passwd,
                'group_id'            => 3,
                'group_name'          => 'customer',
                'customer_group_id'   => 1,
                'customer_group_name' => 'General',
            );

            $country_code_raw = (string) $this->input->post('country_code', true);
            if ($country_code_raw) {
                $country_parts = explode('~', $country_code_raw);
                if (isset($country_parts[1])) {
                    $customerData['country'] = $country_parts[1];
                }
            }

            $newCustomer = $this->webshop_model->add_customer($customerData);
            if (!$newCustomer) {
                $this->session->set_flashdata('toast_error', 'Registration failed. Please try again.');
                $this->_flash_register_inputs($first, $last, $email, $phone);
                redirect(current_url());
                return;
            }

            $this->send_registration_email();

            // --- Auto-login after successful registration ---
            // login_customer sends plain password; ElintOm md5's it internally
            $loginIdentity = !empty($email) ? $email : $phone;
            $authData = $this->webshop_model->login_customer($loginIdentity, $passwd);

            $return_page = 'webshop';
            if (!empty($authData)) {
                $authData = is_array($authData) ? (object) $authData : $authData;
                $authData->user_id  = isset($authData->id) ? $authData->id : (isset($authData->user_id) ? $authData->user_id : 0);
                $authData->is_login = TRUE;

                if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                    $return_page = site_url('webshop/checkout');
                }

                $this->session->webshop = $authData;
                $this->session->set_userdata('customer_register', array(
                    'user_id'             => $authData->user_id,
                    'first'               => $first,
                    'last'                => $last,
                    'email'               => $email,
                    'phone'               => $phone,
                    'group_id'            => 3,
                    'group_name'          => 'customer',
                    'customer_group_id'   => 1,
                    'customer_group_name' => 'General',
                ));

                redirect($return_page . '?msg=auth_success');
            } else {
                // Registration succeeded but auto-login failed — send to login
                $this->session->set_flashdata('toast_success', 'Account created! Please sign in.');
                redirect('webshop/login');
            }

        } else {
            // --- GET: show form (redirect away if already logged in) ---
            $ws_sess = $this->session->userdata('webshop');
            $is_login = false;
            if ($ws_sess) {
                $is_login = is_object($ws_sess)
                    ? (isset($ws_sess->is_login) && $ws_sess->is_login)
                    : (isset($ws_sess['is_login']) && $ws_sess['is_login']);
            }
            if ($is_login) {
                redirect('webshop/index');
                return;
            }

            $raw_settings = $this->webshop_model->get_website_setting();
            $this->data['website_setting'] = $raw_settings;
            $setting_map = array();
            if (is_array($raw_settings) || is_object($raw_settings)) {
                foreach ($raw_settings as $row) {
                    $setting_map[$row->fields] = $row->value;
                }
            }
            $this->data['setting_map'] = $setting_map;
            $this->data['country']     = $this->webshop_model->getCountry();
            $this->load_view('register', $this->data);
        }
    }

    private function _flash_register_inputs($first, $last, $email, $phone)
    {
        $this->session->set_flashdata('reg_first', $first);
        $this->session->set_flashdata('reg_last',  $last);
        $this->session->set_flashdata('reg_email', $email);
        $this->session->set_flashdata('reg_phone', $phone);
    }
    public function send_registration_email()
    {
        $email = trim((string) $this->input->post('email'));
        $name = trim((string) $this->input->post('first') . ' ' . (string) $this->input->post('last'));
        if ($email === '') {
            return false;
        }
        $subject = 'Registration successful';
        $message = 'Dear ' . ($name !== '' ? $name : 'Customer') . ",<br><br>Your account has been created successfully.";
        return $this->_send_basic_email($email, $subject, $message);
    }

    private function _send_basic_email($to, $subject, $message)
    {
        try {
            $this->load->library('email');
            $from = !empty($this->Settings->default_email) ? $this->Settings->default_email : 'no-reply@localhost';
            $fromName = !empty($this->Settings->site_name) ? $this->Settings->site_name : 'Webshop';
            $this->email->clear(true);
            $this->email->from($from, $fromName);
            $this->email->to($to);
            $this->email->subject($subject);
            $this->email->message($message);
            return (bool) $this->email->send(false);
        } catch (\Throwable $e) {
            log_message('error', 'Email send failed: ' . $e->getMessage());
            return false;
        }
    }

    // public function apply_coupon($postData) {

    //     $coupon_code = $postData['coupon_code'];
    //     $cart_amount = $postData['cart_amount'];

    //     $couponData = $this->webshop_model->get_coupon_data($coupon_code);

    //     $data['status'] = 'failed';
    //     $data['msg'] = "Invalid coupon code " . $couponData->coupon_code;

    //     if ($couponData->coupon_code === $coupon_code) {

    //         if ($couponData->minimum_cart_amount > $cart_amount) {
    //             $data['status'] = 'failed';
    //             $data['msg'] = "Cart amount is less as per coupon conditions.";
    //         } elseif (strtotime($couponData->expiry_date) < strtotime(date('Y-m-d')) || $couponData->status == 'expired') {
    //             $data['status'] = 'failed';
    //             $data['msg'] = "Coupon " . $couponData->coupon_code . " has been expired.";
    //         } elseif ($couponData->status != 'active') {
    //             $data['status'] = 'failed';
    //             $data['msg'] = "Coupon " . $couponData->coupon_code . " is no more active.";
    //         } elseif ($couponData->max_coupons != '' && $couponData->max_coupons > 0 && $couponData->used_coupons > 0 && $couponData->max_coupons <= $couponData->used_coupons) {
    //             $data['status'] = 'failed';
    //             $data['msg'] = "Coupon " . $couponData->coupon_code . " max limit has been already reach.";
    //         } elseif ($couponData->customer_id != '' && $couponData->customer_id != $this->session->webshop->user_id) {
    //             $data['status'] = 'failed';
    //             $data['msg'] = "Coupon code " . $couponData->coupon_code . " is belongs to another customer.";
    //         } elseif ($couponData->customer_group_id != '' && $couponData->customer_group_id != $this->session->webshop->customer_group_id) {
    //             $data['status'] = 'failed';
    //             $data['msg'] = "Coupon code " . $couponData->coupon_code . " is belongs to another customer groups.";
    //         } else {

    //             if (!empty($couponData->discount_rate)) {
    //                 $discount = $couponData->discount_rate;
    //                 $dpos = strpos($discount, '%');
    //                 if ($dpos !== false) {
    //                     $cup_ds = explode("%", $discount);
    //                     $coupon_discount = $this->sma->formatDecimal(( ( (Float) $cart_amount * (Float) $cup_ds[0] ) / 100), 4);
    //                 } else {
    //                     $coupon_discount = $this->sma->formatDecimal($discount, 4);
    //                 }

    //                 if ($couponData->maximum_discount_amount > 0 && $coupon_discount > $couponData->maximum_discount_amount) {
    //                     $coupon_discount = $couponData->maximum_discount_amount;
    //                 }

    //                 $couponData->aplied_discount_amount = $coupon_discount;

    //                 $data['status'] = 'success';
    //                 $data['msg'] = "Coupon applied successfully.";
    //                 $data['coupon_data'] = $couponData;
    //             }//end if
    //         }
    //     }

    //     $this->json_response($data);
    // }
    public function apply_coupon($postData)
    {
        $coupon_code = isset($postData['coupon_code']) ? trim((string) $postData['coupon_code']) : '';
        $cart_amount = isset($postData['cart_amount']) ? (float) $postData['cart_amount'] : 0;
        if ($coupon_code === '') {
            $this->json_response(array('status' => 'failed', 'msg' => 'Coupon code is required.'));
            return;
        }

        $apiResult = $this->webshop_model->apply_coupon($coupon_code, $cart_amount);
        if (!empty($apiResult) && is_array($apiResult)) {
            $c = $apiResult;
            $discountAmt = 0.0;
            if (isset($c['calculated_discount'])) {
                $discountAmt = (float) $c['calculated_discount'];
            } elseif (isset($c['aplied_discount_amount'])) {
                $discountAmt = (float) $c['aplied_discount_amount'];
            } elseif (isset($c['applied_discount_amount'])) {
                $discountAmt = (float) $c['applied_discount_amount'];
            }
            $cid = isset($c['id']) ? $c['id'] : (isset($c['coupon_id']) ? $c['coupon_id'] : 0);
            $rate = 0.0;
            if (isset($c['discount_rate'])) {
                $rate = (float) $c['discount_rate'];
            } elseif (!empty($c['discount_type']) && (string) $c['discount_type'] === 'percentage' && isset($c['discount'])) {
                $rate = (float) $c['discount'];
            }
            $coupon_data = array_merge($c, array(
                'id'                       => $cid,
                'discount_rate'            => $rate,
                'aplied_discount_amount'   => $discountAmt,
                'applied_discount_amount'  => $discountAmt,
            ));
            $this->json_response(array(
                'status'      => 'success',
                'msg'         => 'Coupon applied successfully.',
                'coupon_data' => (object) $coupon_data,
            ));
            return;
        }

        $this->json_response(array(
            'status' => 'failed',
            'msg' => 'Invalid coupon code.',
        ));
    }

    public function page($PageKey, $pageHashId)
    {

        $this->data['page_data'] = $this->webshop_model->getCustomPages($pageHashId);

        $this->load_view("page", $this->data);
    }

    public function your_account()
    {
        $this->_render_my_account_view('profile');
    }

    /**
     * Same shell as your_account, with the Track order tab selected (logged-in only).
     */
    public function your_tracking()
    {
        $this->_render_my_account_view('tracking');
    }

    /**
     * Unified Gulf Pharmacy "My Account" view.
     *
     * Loads /your_account, /your_orders, /your_tracking, /your_address, /change_password into the same
     * tabbed shell with $active_tab driving which panel is initially visible.
     * Heavy lists (orders, addresses, state/country) are loaded only for the tab that is shown first;
     * other tabs hydrate via POST webshop_request action=account_panel_data (see account_panel_data()).
     */
    private function _render_my_account_view($active_tab = 'profile')
    {
        $theme = isset($this->webshop_settings->webshop_theme) ? (string) $this->webshop_settings->webshop_theme : '';
        $ws_sess = $this->session->userdata('webshop');
        $is_login = false;
        $customer_id = 0;
        if ($ws_sess) {
            $is_login = is_object($ws_sess) ? (isset($ws_sess->is_login) && $ws_sess->is_login) : (isset($ws_sess['is_login']) && $ws_sess['is_login']);
            $customer_id = (int) (is_object($ws_sess) ? (isset($ws_sess->user_id) ? $ws_sess->user_id : 0) : (isset($ws_sess['user_id']) ? $ws_sess['user_id'] : 0));
        }

        if (!$is_login || !$customer_id) {
            redirect("webshop/login");
            return;
        }

        // Only load heavy lists for the tab that needs them on first paint; other tabs hydrate via account_panel_data.
        $ssr_orders = ($active_tab === 'orders');
        $ssr_addresses = ($active_tab === 'addresses');
        $ssr_geo = ($active_tab === 'addresses');

        // Legacy theme shells still expect the full payload in $this->data (even if the view is not Gulf).
        if ($theme === 'restaurant' || $theme === 'nw') {
            $ssr_orders = true;
            $ssr_addresses = true;
            $ssr_geo = true;
        }

        $this->data['orders'] = array('orders' => array());
        $this->data['addresses'] = array();
        $this->data['state_list'] = array();
        $this->data['country'] = array();

        if ($ssr_orders) {
            $orders_raw = $this->webshop_model->get_customer_orders($customer_id);
            if (is_array($orders_raw) && isset($orders_raw['orders']) && is_array($orders_raw['orders'])) {
                $this->data['orders'] = $orders_raw;
            }
        }
        if ($ssr_addresses) {
            $addr_raw = $this->webshop_model->get_customer_address($customer_id);
            if (is_array($addr_raw)) {
                $this->data['addresses'] = $addr_raw;
            }
        }
        if ($ssr_geo) {
            $st = $this->webshop_model->get_state();
            $this->data['state_list'] = is_array($st) ? $st : array();
            $cc = $this->webshop_model->getCountry();
            $this->data['country'] = is_array($cc) ? $cc : array();
        }

        $this->data['ma_orders_lazy'] = !$ssr_orders;
        $this->data['ma_addresses_lazy'] = !$ssr_addresses;
        $this->data['ma_geo_lazy'] = !$ssr_geo;

        $cust = $this->webshop_model->get_customer(['id' => $customer_id]);
        $this->data['customer'] = is_array($cust) ? $cust : array();
        $this->data['customer_id']  = $customer_id;
        $this->data['images']       = base_url("assets/images/customers/");

        $allowed_tabs = array('profile', 'orders', 'tracking', 'addresses', 'change_password');
        $this->data['active_tab'] = in_array($active_tab, $allowed_tabs, true) ? $active_tab : 'profile';

        // Surface password-change status from change_password() redirects (success / error).
        $segment3 = $this->uri->segment(3, '');
        if ($segment3 === 'success' || $segment3 === 'error') {
            $this->data['password_status'] = $segment3;
        }

        if ($theme === 'gulfpharmacy' || $theme === 'herbinnwellness' || $this->is_plane_vanila_storefront()) {
            $this->load_view('my_account', $this->data);
            return;
        }
        if ($theme === 'restaurant') {
            $this->load_view("webshop_restaurant_t1/my_account", $this->data);
            return;
        }
        if ($theme === 'nw') {
            $this->load_view("nw_theme/my_account", $this->data);
            return;
        }
        $this->load_view('my_account', $this->data);
    }

    /**
     * JSON payload for My Account lazy tabs (orders / addresses / geo dropdowns).
     * Called via POST webshop_request action=account_panel_data (same-origin session).
     */
    public function account_panel_data()
    {
        $customer_id = $this->_get_webshop_session_user_id();
        if (!$customer_id) {
            $this->json_response(array('status' => 'FAIL', 'error' => 'Unauthorized'));
            return;
        }

        $orders_raw = $this->webshop_model->get_customer_orders($customer_id);
        $orders_list = array();
        if (is_array($orders_raw) && isset($orders_raw['orders']) && is_array($orders_raw['orders'])) {
            foreach ($orders_raw['orders'] as $o) {
                $arr = is_object($o) ? (array) $o : (array) $o;
                $oid = isset($arr['order_id']) ? (int) $arr['order_id'] : 0;
                $arr['track_hash'] = $oid > 0 ? md5((string) $oid) : '';
                $orders_list[] = $arr;
            }
        }

        $addr_raw = $this->webshop_model->get_customer_address($customer_id);
        $addresses_out = $this->_normalize_ma_addresses_for_json($addr_raw);

        // Keep both `id` (for cascade lookup) and `country_id` so the JS can rebuild
        // the state dropdown filtered by the chosen country. ElintOm's getstates payload
        // already carries country_id — see Elintom_api_response::normalize_states_map().
        $state_rows = $this->webshop_model->get_state();
        $state_out = array();
        if (is_array($state_rows)) {
            foreach ($state_rows as $sr) {
                $row = is_object($sr) ? (array) $sr : (array) $sr;
                $n = '';
                if (isset($row['name']) && (string) $row['name'] !== '') {
                    $n = (string) $row['name'];
                } elseif (isset($row['state_name']) && (string) $row['state_name'] !== '') {
                    $n = (string) $row['state_name'];
                } elseif (isset($row['StateName']) && (string) $row['StateName'] !== '') {
                    $n = (string) $row['StateName'];
                }
                if ($n === '') {
                    continue;
                }
                $sid  = isset($row['id']) ? (int) $row['id'] : 0;
                $scid = isset($row['country_id']) ? (int) $row['country_id'] : 0;
                $state_out[] = array(
                    'id'         => $sid,
                    'country_id' => $scid,
                    'name'       => $n,
                );
            }
        }

        $countries_raw = $this->webshop_model->getCountry();
        $countries_out = array();
        if (is_array($countries_raw)) {
            foreach ($countries_raw as $c) {
                $name = '';
                $cid  = 0;
                if (is_object($c)) {
                    if (isset($c->name)) {
                        $name = (string) $c->name;
                    }
                    if (isset($c->id)) {
                        $cid = (int) $c->id;
                    }
                } else {
                    $co = is_array($c) ? $c : array();
                    if (isset($co['name'])) {
                        $name = (string) $co['name'];
                    }
                    if (isset($co['id'])) {
                        $cid = (int) $co['id'];
                    }
                }
                if ($name !== '') {
                    $countries_out[] = array(
                        'id'   => $cid,
                        'name' => $name,
                    );
                }
            }
        }

        // Identity echo: tells the JS exactly which DB customer was queried so the
        // empty-state cards can show "Signed in as …" — prevents the recurring
        // confusion where a logged-in test account has no orders/addresses because
        // a different account (different phone/email) is the one that owns them.
        $account_out = $this->_account_identity_for_response($customer_id);

        $this->json_response(array(
            'status' => 'OK',
            'account' => $account_out,
            'orders' => $orders_list,
            'addresses' => $addresses_out,
            'state_list' => $state_out,
            'countries' => $countries_out,
        ));
    }

    /**
     * Build a small {id, name, email_masked, phone_masked} bag for the panel JSON.
     * Source of truth is the API (get_customer); we fall back to session-stored
     * fields only when the API didn't return them. Email and phone are masked
     * before they leave the controller — keep the rule in security-and-secrets.mdc.
     *
     * @param int $customer_id
     * @return array<string,mixed>
     */
    private function _account_identity_for_response($customer_id)
    {
        $cust = $this->webshop_model->get_customer(array('id' => $customer_id));
        $cust = is_array($cust) ? $cust : array();
        $ws_sess = $this->session->userdata('webshop');
        $ws_obj  = $ws_sess;
        $ws_get  = function ($key) use ($ws_obj) {
            if (is_object($ws_obj) && isset($ws_obj->$key)) {
                return (string) $ws_obj->$key;
            }
            if (is_array($ws_obj) && isset($ws_obj[$key])) {
                return (string) $ws_obj[$key];
            }
            return '';
        };

        $name  = isset($cust['name']) ? (string) $cust['name'] : $ws_get('name');
        $email = isset($cust['email']) ? (string) $cust['email'] : $ws_get('email');
        $phone = isset($cust['phone']) ? (string) $cust['phone'] : $ws_get('phone');

        return array(
            'id'            => (int) $customer_id,
            'name'          => $name,
            'email_masked'  => $this->_mask_email_for_display($email),
            'phone_masked'  => $this->_mask_phone_for_display($phone),
        );
    }

    private function _mask_email_for_display($email)
    {
        $email = trim((string) $email);
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }
        list($local, $domain) = explode('@', $email, 2);
        if ($local === '') {
            return '@' . $domain;
        }
        if (strlen($local) <= 2) {
            return $local[0] . '***@' . $domain;
        }
        return substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 4)) . substr($local, -2) . '@' . $domain;
    }

    private function _mask_phone_for_display($phone)
    {
        $phone = preg_replace('/\s+/', '', (string) $phone);
        if ($phone === '') {
            return '';
        }
        if (strlen($phone) <= 4) {
            return $phone;
        }
        return substr($phone, 0, 2) . str_repeat('*', max(2, strlen($phone) - 4)) . substr($phone, -2);
    }

    /**
     * @param mixed $raw get_customer_address() return (keyed list, indexed list, or false)
     * @return array<int,array<string,mixed>>
     */
    private function _normalize_ma_addresses_for_json($raw)
    {
        $out = array();
        if ($raw === false || $raw === null) {
            return $out;
        }
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $row) {
            $a = is_object($row) ? (array) $row : (array) $row;
            $email = '';
            if (isset($a['email_id'])) {
                $email = (string) $a['email_id'];
            } elseif (isset($a['email'])) {
                $email = (string) $a['email'];
            }
            $out[] = array(
                'id' => isset($a['id']) ? (int) $a['id'] : 0,
                'address_name' => isset($a['address_name']) ? (string) $a['address_name'] : '',
                'line1' => isset($a['line1']) ? (string) $a['line1'] : '',
                'line2' => isset($a['line2']) ? (string) $a['line2'] : '',
                'city' => isset($a['city']) ? (string) $a['city'] : '',
                'postal_code' => isset($a['postal_code']) ? (string) $a['postal_code'] : '',
                'state' => isset($a['state']) ? (string) $a['state'] : '',
                'country' => isset($a['country']) ? (string) $a['country'] : '',
                'phone' => isset($a['phone']) ? (string) $a['phone'] : '',
                'email_id' => $email,
                'is_default' => isset($a['is_default']) ? (int) $a['is_default'] : 0,
            );
        }
        return $out;
    }

    public function your_address()
    {
        $customer_id = $this->_get_webshop_session_user_id();
        if (!$customer_id) {
            redirect("webshop/login");
            return;
        }
        $theme = isset($this->webshop_settings->webshop_theme) ? (string) $this->webshop_settings->webshop_theme : '';
        if ($theme === 'restaurant' || $theme === 'nw') {
            // Legacy themes still expect the JSON contract for this endpoint.
            $this->data['state_list'] = $this->webshop_model->get_state();
            $this->data['customer_id'] = $customer_id;
            $this->data['addresses'] = $this->webshop_model->get_customer_address($customer_id);
            $this->json_response($this->data);
            return;
        }
        $this->_render_my_account_view('addresses');
    }

    public function manage_address($postData)
    {

        if (isset($postData['submit_address'])) {

            $customer_id = $this->input->post('customer_id');
            $addressAction = $this->input->post('addressModalAction');

            $address_name = $this->input->post('address_name');
            $company_name = $this->input->post('company_name');
            $line_1 = $this->input->post('address_line_1');
            $line_2 = $this->input->post('address_line_2');
            $country = $this->input->post('country');
            $state = $this->input->post('state');
            $state_name = $this->input->post('state_name');
            $city = $this->input->post('city');
            $postal_code = $this->input->post('postal_code');
            $phone = $this->input->post('phone');
            $email_id = $this->input->post('email_id');
            $default_address = $this->input->post('default_address');

            $stateData = explode('~', $state);

            $state_name = $stateData[0];
            $state_code = $stateData[1];

            $data = [
                'company_id' => $customer_id,
                'company_name' => $company_name,
                'address_name' => $address_name,
                'line1' => $line_1,
                'line2' => $line_2,
                'city' => $city,
                'postal_code' => $postal_code,
                'state' => $state_name,
                'country' => $country,
                'phone' => $phone,
                'email_id' => $email_id,
                'state_code' => $state_code,
            ];


            if ($addressAction == 'add') {
                if ($address_id = $this->webshop_model->set_customer_address($data)) {

                    if ($default_address == 1) {
                        $this->webshop_model->set_address_default($customer_id, $address_id);
                    }

                    $this->session->set_flashdata('message', "Address Added Successfully.");
                    redirect("webshop/your_account#addresses");
                }
            } else if ($addressAction == 'edit') {
                $addressId = $this->input->post('addressModalActionId');
                $this->webshop_model->update_customer_address($data, $addressId);
                $this->session->set_flashdata('message', "Address Updated Successfully.");
                redirect("webshop/your_account#addresses");
            }
        }
    }

    public function manage_address_webshop($postData = null)
    {
        if (is_array($postData)) {
            $input = $postData;
        } else {
            $input = function_exists('webshop_read_json_request_body')
                ? webshop_read_json_request_body()
                : json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = array();
            }
        }
        $userId = $this->_get_webshop_session_user_id();
        if (!$userId) {
            $this->json_response(['statusMessage' => "unauthorized"]);
            return;
        }
        $address_name = isset($input['address_name']) ? $input['address_name'] : '';
        $company_name = isset($input['company_name']) ? $input['company_name'] : '';
        $line1 = isset($input['line1']) ? $input['line1'] : '';
        $line2 = isset($input['line2']) ? $input['line2'] : '';
        $city = isset($input['city']) ? $input['city'] : '';
        $postal_code = isset($input['postal_code']) ? $input['postal_code'] : '';
        $state = isset($input['state']) ? trim((string) $input['state']) : '';
        $state_code = '';
        if ($state !== '' && strpos($state, '~') !== false) {
            $sd = explode('~', $state, 2);
            $state = trim($sd[0]);
            $state_code = isset($sd[1]) ? trim($sd[1]) : '';
        }
        $country = isset($input['country']) ? $input['country'] : '';
        $phone = isset($input['phone']) ? $input['phone'] : '';
        $email_id = isset($input['email_id']) ? $input['email_id'] : '';
        $is_default = isset($input['is_default']) ? (int) $input['is_default'] : 0;
        $addressAction = isset($input['addressAction']) ? $input['addressAction'] : '';

        $data = [
            'company_id' => $userId,
            'address_name' => $address_name,
            'company_name' => $company_name,
            'line1' => $line1,
            'line2' => $line2,
            'city' => $city,
            'postal_code' => $postal_code,
            'state' => $state,
            'state_code' => $state_code,
            'country' => $country,
            'phone' => $phone,
            'email_id' => $email_id,
        ];

        if ($addressAction == "edit") {
            $eid = isset($input['address_id']) ? (int) $input['address_id'] : 0;
            if ($eid < 1) {
                $this->json_response(['statusMessage' => "error"]);
                return;
            }
            $ok = $this->webshop_model->update_customer_address($data, $eid);
            if ($ok && $is_default == 1) {
                $this->webshop_model->set_address_default($userId, $eid);
            }
            $this->json_response(['statusMessage' => $ok ? "success" : "error"]);
            return;
        } elseif ($addressAction == "add") {
            $newId = $this->webshop_model->set_customer_address($data);
            if ($newId) {
                if ($is_default == 1) {
                    $this->webshop_model->set_address_default($userId, $newId);
                }
                $this->json_response(['statusMessage' => "success"]);
                return;
            }
            $this->json_response(['statusMessage' => "error"]);
            return;
        }
        $this->json_response(['statusMessage' => "error"]);
    }

    public function address_set_default($customer_id, $address_id)
    {
        $uid = $this->_get_webshop_session_user_id();
        if (!$uid || (int) $customer_id !== (int) $uid) {
            redirect('webshop/login');
            return;
        }
        if ($this->webshop_model->set_address_default($customer_id, $address_id)) {

            $this->session->set_flashdata('message', "Default Address Set Successfully.");
            redirect("webshop/your_account#addresses");
        }
    }

    public function checkout_set_shipping_address($customer_id, $address_id)
    {

        if ($this->webshop_model->set_address_default($customer_id, $address_id)) {

            $this->session->set_flashdata('message', "Default Address Set Successfully.");
            redirect("webshop/checkout");
        }
    }

    public function address_delete($address_id)
    {
        $uid = $this->_get_webshop_session_user_id();
        if (!$uid) {
            redirect("webshop/login");
            return;
        }
        if ($this->webshop_model->delete_address((int) $address_id, $uid)) {

            $this->session->set_flashdata('message', "Address Deleted Successfully.");
            redirect("webshop/your_account#addresses");
        }
    }

    public function your_orders()
    {
        $customer_id = $this->_get_webshop_session_user_id();
        if (!$customer_id) {
            redirect("webshop/login");
            return;
        }
        $theme = isset($this->webshop_settings->webshop_theme) ? (string) $this->webshop_settings->webshop_theme : '';
        if ($theme === 'restaurant' || $theme === 'nw') {
            // Legacy themes still expect the JSON contract for this endpoint.
            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
            $this->data['customer_id']   = $customer_id;
            $this->data['orders']        = $this->webshop_model->get_customer_orders($customer_id);
            $this->data['addresses']     = $this->webshop_model->get_customer_address($customer_id);
            $this->json_response($this->data);
            return;
        }
        $this->_render_my_account_view('orders');
    }

    public function order_details($order_id)
    {

        $customer_id = $this->_get_webshop_session_user_id();
        if ($customer_id) {

            if (empty($order_id) || $order_id == '') {
                redirect("webshop/your_orders");
            }

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

            $this->data['customer_id'] = $customer_id;

            $this->data['order'] = $this->webshop_model->get_customer_orders($customer_id, $order_id);

            $this->load_view("order_details", $this->data);
        } else {
            redirect("webshop/login");
        }
    }

    public function profile_update()
    {

        // $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');
        $this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');

        if ($this->input->post('upload_image') !== false && $this->input->post('upload_image') !== null) {

            $upload_path = './assets/images/customers/';
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
                $indexfile = fopen($upload_path . "index.html", "w") or die("Unable to open file!");
                fwrite($indexfile, '<p>Directory access is forbidden.</p>');
                fclose($indexfile);
            }

            $fileconfig['upload_path'] = $upload_path;
            $fileconfig['allowed_types'] = 'jpg|png|jpeg';
            $fileconfig['max_size'] = 2000;
            $fileconfig['max_width'] = 700;
            $fileconfig['max_height'] = 900;
            $fileconfig['is_image'] = 1;
            $fileconfig['file_name'] = md5($this->_get_webshop_session_user_id());

            $this->load->library('upload', $fileconfig);
            $this->upload->overwrite = true;
            if (!$this->upload->do_upload('profile_image')) {

                $this->session->set_flashdata('error', $this->upload->display_errors());
                redirect("webshop/your_profile");
            } else {

                $upload_data = $this->upload->data(); //Returns array of containing all of the data related to the file you uploaded.
                $file_name = $upload_data['file_name'];

                if ($this->webshop_model->set_profile_photo($file_name, $this->_get_webshop_session_user_id())) {

                    $this->session->set_flashdata('message', "Profile Image Uploaded Successfully.");
                    redirect("webshop/your_profile");
                }
            }
        } else if ($this->input->post('submitProfle') !== false && $this->input->post('submitProfle') !== null) {

            $this->form_validation->set_rules('name', 'Your Name', 'trim|required|alpha_numeric_spaces');
            // $this->form_validation->set_rules('phone',  'Phone Number', 'trim|required|numeric|exact_length[10]|is_unique[companies.phone]');
            $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
            $this->form_validation->set_rules('country', 'country', 'required|alpha');
            $this->form_validation->set_rules('state', 'state', 'trim|required|alpha');
            $this->form_validation->set_rules('city', 'city', 'trim|required|alpha');
            $this->form_validation->set_rules('pincode', 'pincode', 'trim|required|numeric|exact_length[6]');

            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('error', 'Validation Errors!');
                $this->your_profile('edit');
            } else {
                $name = (string) $this->input->post('name', true);
                $email = (string) $this->input->post('email', true);
                $country = (string) $this->input->post('country', true);
                $state = (string) $this->input->post('state', true);
                $city = (string) $this->input->post('city', true);
                $pincode = (string) $this->input->post('pincode', true);
                $address = (string) $this->input->post('address', true);
                $pan_card = (string) $this->input->post('pan_card', true);
                $gstn_no = (string) $this->input->post('gstn_no', true);
                $vat_no = (string) $this->input->post('vat_no', true);
                $company = (string) $this->input->post('company', true);

                $company = $company == '' ? '-' : $company;

                $data = [
                    "name" => $name,
                    "email" => $email,
                    "country" => $country,
                    "state" => $state,
                    "city" => $city,
                    "postal_code" => $pincode,
                    "address" => $address,
                    "pan_card" => $pan_card,
                    "gstn_no" => $gstn_no,
                    "vat_no" => $vat_no,
                    "company" => $company,
                ];

                if ($this->webshop_model->update_profile($data, $this->_get_webshop_session_user_id())) {

                    $this->session->set_flashdata('message', "Profile Updated Successfully.");
                    redirect("webshop/your_profile");
                } else {
                    $this->session->set_flashdata('error', "Profile Update Sql Error.");
                    redirect("webshop/your_profile");
                }
            }
        } else {
            redirect("webshop/your_profile");
        }
    }

    public function profile_update_webshop($postData = null)
    {
        if (is_array($postData)) {
            $input = $postData;
        } else {
            $input = function_exists('webshop_read_json_request_body')
                ? webshop_read_json_request_body()
                : json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = array();
            }
        }
        $fName = isset($input['fName']) ? $input['fName'] : '';
        $dob   = isset($input['dob']) ? $input['dob'] : '';
        $email = isset($input['email']) ? $input['email'] : '';
        $data = [
            'name' => $fName,
            'email' => $email,
            'dob' => $dob,
        ];
        $ws_sess = $this->session->userdata('webshop');
        $userId = 0;
        if ($ws_sess) {
            $userId = (int) (is_object($ws_sess) ? (isset($ws_sess->user_id) ? $ws_sess->user_id : 0) : (isset($ws_sess['user_id']) ? $ws_sess['user_id'] : 0));
        }

        if ($userId) {
            $update = $this->webshop_model->update_profile($data, $userId);
            if ($update) {
                if (is_object($ws_sess)) {
                    $ws_sess->name = $fName;
                    $ws_sess->email = $email;
                } else {
                    $ws_sess['name'] = $fName;
                    $ws_sess['email'] = $email;
                }
                $this->session->set_userdata('webshop', $ws_sess);
                $this->json_response(['statusMessage' => "success"]);
                return;
            }
            $this->json_response(["statusMessage" => "failed"]);
            return;
        }
    }

    // public function update_profile_image(){
    //     if (isset($_POST['upload_image'])) {

    //         $upload_path = './assets/images/customers/';
    //         if(!file_exists($upload_path)){
    //             mkdir($upload_path, 0777, true);
    //             $indexfile = fopen($upload_path."index.html", "w") or die("Unable to open file!");                
    //             fwrite($indexfile, '<p>Directory access is forbidden.</p>');                
    //             fclose($indexfile);
    //         }

    //         $fileconfig['upload_path'] = $upload_path;
    //         $fileconfig['allowed_types'] = 'jpg|png|jpeg';
    //         $fileconfig['max_size'] = 2000;
    //         $fileconfig['max_width'] = 700;
    //         $fileconfig['max_height'] = 900;
    //         $fileconfig['is_image'] = 1;

    //         $fileconfig['file_name'] = md5($this->session->webshop->user_id);

    //         $this->load->library('upload', $fileconfig);
    //         $this->upload->overwrite = true;
    //         if (!$this->upload->do_upload('profile_image')) {

    //             $this->session->set_flashdata('error', $this->upload->display_errors());
    //             redirect("webshop/your_profile");
    //         } else {

    //             $upload_data = $this->upload->data();
    //             $file_name = $upload_data['file_name'];

    //             if ($this->webshop_model->set_profile_photo($file_name, $this->session->webshop->user_id)) {

    //                 $this->session->set_flashdata('message', "Profile Image Uploaded Successfully.");
    //                 redirect("webshop/your_profile");
    //             }
    //         }
    //     }
    // }



    public function your_profile($action = 'view')
    {
        $ws_sess = $this->session->userdata('webshop');
        $userId = 0;
        if ($ws_sess) {
            $userId = (int) (is_object($ws_sess) ? (isset($ws_sess->user_id) ? $ws_sess->user_id : 0) : (isset($ws_sess['user_id']) ? $ws_sess['user_id'] : 0));
        }

        if ($userId) {
            $theme = $this->webshop_settings->webshop_theme;

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

            $this->data['customer'] = $customer = $this->webshop_model->get_customer(['id' => $userId]);
            // var_dump($customer);
            // exit;

            $this->data['images'] = base_url("assets/images/customers/");
            $this->data['action'] = $action;
            if ($theme == 'restaurant' || $theme == "nw") {
                $this->json_response($this->data);
                return;
            }
            $this->load_view("your_profile", $this->data);
        } else {
            redirect("webshop/login");
        }
    }

    public function my_views_history()
    {

        $this->load_view("my_views_history", $this->data);
    }

    public function change_password()
    {
        $userId = $this->_get_webshop_session_user_id();
        if (!$userId) {
            redirect("webshop/login");
            return;
        }

        if ($this->input->post('changePassword') !== false && $this->input->post('changePassword') !== null) {

            $this->load->library('form_validation');
            $this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');

            $this->form_validation->set_rules('current_password', 'current password', 'required');
            $this->form_validation->set_rules('newpassword', 'newpassword', 'trim|required|min_length[8]|max_length[22]|differs[current_password]');
            $this->form_validation->set_rules('confirm', 'confirm', 'trim|required|matches[newpassword]');

            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('error', 'Validation Errors!');
                $this->_render_my_account_view('change_password');
                return;
            }

            $current_password = $this->input->post('current_password');
            $newpassword      = md5($this->input->post('newpassword'));

            if ($this->webshop_model->is_valid_current_password($userId, $current_password) === false) {
                $this->session->set_flashdata('error', 'Invalid Current Password');
                redirect("webshop/change_password/error");
                return;
            }

            if ($this->webshop_model->update_new_password($userId, $newpassword)) {
                $this->session->set_flashdata('message', 'Password has been changed successfully.');
                redirect("webshop/change_password/success");
                return;
            }

            $this->session->set_flashdata('error', 'Sql error!');
            redirect("webshop/change_password/error");
            return;
        }

        $this->_render_my_account_view('change_password');
    }

    public function forgot_password()
    {
        $ws_sess = $this->session->userdata('webshop');
        $already_logged_in = $ws_sess
            && (is_object($ws_sess) ? !empty($ws_sess->is_login)  : !empty($ws_sess['is_login']))
            && (is_object($ws_sess) ? !empty($ws_sess->user_id)   : !empty($ws_sess['user_id']));
        if ($already_logged_in) {
            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('controller.already_logged_in', array());
            }
            redirect('webshop/index');
        }

        $countries_list = array();
        try {
            $countries_list = $this->webshop_model->getCountry();
            if (!is_array($countries_list)) {
                $countries_list = array();
            }
        } catch (Exception $e) {
            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('controller.getCountry.exception', array('error' => $e->getMessage()));
            } else {
                log_message('error', 'forgot_password: getCountry failed: ' . $e->getMessage());
            }
        }
        $phone_code = function_exists('webshop_settings_phone_dial_code')
            ? webshop_settings_phone_dial_code($countries_list)
            : '91';
        $this->data['phone_code'] = $phone_code;
        $this->data['phone_local_digits'] = function_exists('webshop_settings_local_phone_length')
            ? webshop_settings_local_phone_length($countries_list)
            : 10;

        $country_setting = (isset($this->Settings) && is_object($this->Settings) && isset($this->Settings->country))
            ? (string) $this->Settings->country : '';
        $theme_name = (isset($this->webshop_settings) && is_object($this->webshop_settings) && isset($this->webshop_settings->webshop_theme))
            ? (string) $this->webshop_settings->webshop_theme : '';

        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) === 'POST' && function_exists('webshop_forgot_password_log')) {
            $sendOtpVal = $this->input->post('send_otp');
            $resetVal = $this->input->post('reset_password');
            webshop_forgot_password_log('controller.post_received', array(
                'send_otp'        => ($sendOtpVal !== false && $sendOtpVal !== null && $sendOtpVal !== '') ? 'yes' : 'no',
                'reset_password'  => ($resetVal !== false && $resetVal !== null && $resetVal !== '') ? 'yes' : 'no',
                'has_mobile'      => ($this->input->post('mobile') !== false && $this->input->post('mobile') !== null && $this->input->post('mobile') !== ''),
                'post_field_keys' => array_keys($_POST),
            ));
        }

        // ── Step 1: deliver OTP ────────────────────────────────────────
        if ($this->input->post('send_otp') !== false && $this->input->post('send_otp') !== null) {
            $raw_mobile = (string) $this->input->post('mobile');
            $mobile = $this->_normalize_mobile($raw_mobile);

            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('controller.send_otp.start', array(
                    'raw_mobile'    => $raw_mobile,
                    'mobile'        => $mobile,
                    'phone_code'    => $phone_code,
                    'local_digits'  => isset($this->data['phone_local_digits']) ? $this->data['phone_local_digits'] : null,
                    'country'       => $country_setting,
                ));
            }

            if ($mobile === '' || !$this->_is_valid_mobile($mobile)) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.send_otp.invalid_mobile', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('fp_error', 'Please enter a valid mobile number (10-15 digits).');
                $this->session->set_flashdata('error_field', 'mobile');
                redirect('webshop/forgot_password');
                return;
            }

            // Verify the customer exists via the API (try local + intl — register stores 10-digit local).
            try {
                $customer = $this->webshop_api_model->get_customer_by_phone_variants(
                    $mobile,
                    $phone_code,
                    isset($this->data['phone_local_digits']) ? (int) $this->data['phone_local_digits'] : 10
                );
                if ($customer && function_exists('webshop_phone_digit_variants')) {
                    $matched = webshop_phone_digit_variants($mobile, $phone_code, (int) $this->data['phone_local_digits']);
                    if (!empty($matched)) {
                        $mobile = $matched[0];
                    }
                }
            } catch (Exception $e) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.send_otp.get_customer.exception', array(
                        'mobile' => $mobile,
                        'error'  => $e->getMessage(),
                    ));
                } else {
                    log_message('error', 'forgot_password: get_customer failed: ' . $e->getMessage());
                }
                $this->session->set_flashdata('fp_error', 'Service temporarily unavailable. Please try again in a moment.');
                $this->session->set_flashdata('forgot_mobile', $mobile);
                redirect('webshop/forgot_password');
                return;
            }
            if (!$customer) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.send_otp.customer_not_found', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('fp_error', 'No account found for this mobile number.');
                $this->session->set_flashdata('error_field', 'mobile');
                $this->session->set_flashdata('forgot_mobile', $mobile);
                redirect('webshop/forgot_password');
                return;
            }

            $otp = $this->_generate_numeric_otp(6);
            if ($otp === '') {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.send_otp.otp_generation_failed', array('mobile' => $mobile));
                } else {
                    log_message('error', 'forgot_password: OTP generation returned empty (no entropy source available)');
                }
                $this->session->set_flashdata('fp_error', 'Could not generate a secure OTP. Please try again.');
                redirect('webshop/forgot_password');
                return;
            }

            $this->session->set_userdata('forgot_password_otp_data', array(
                'mobile'     => $mobile,
                'otp'        => $otp,
                'expires_at' => time() + 600,
                'attempts'   => 0,
            ));

            // Hand off delivery to ElintOm (WhatsApp + SMS + Email). Exceptions
            // from the HTTP layer must never reach the browser.
            // ElintOm passwordotpsend looks up sma_companies.phone exactly (same 10-digit local as register).
            try {
                $delivery = $this->webshop_api_model->send_password_otp(
                    $mobile,
                    $otp,
                    $phone_code,
                    isset($this->data['phone_local_digits']) ? (int) $this->data['phone_local_digits'] : 10
                );
            } catch (Exception $e) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.send_otp.delivery_exception', array(
                        'mobile' => $mobile,
                        'error'  => $e->getMessage(),
                    ));
                } else {
                    log_message('error', 'forgot_password: send_password_otp threw: ' . $e->getMessage());
                }
                $delivery = array('status' => 'ERROR', 'msg' => 'Unable to reach the messaging service. Please try again.', 'delivered' => array());
            }

            $delivered = isset($delivery['delivered']) && is_array($delivery['delivered']) ? $delivery['delivered'] : array();
            $channels = array();
            if (!empty($delivered['whatsapp'])) { $channels[] = 'WhatsApp'; }
            if (!empty($delivered['email']))    { $channels[] = 'Email'; }
            if (!empty($delivered['sms']))      { $channels[] = 'SMS'; }

            if ($delivery && isset($delivery['status']) && $delivery['status'] === 'SUCCESS' && !empty($channels)) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.send_otp.delivered', array(
                        'mobile'    => $mobile,
                        'channels'  => implode(',', $channels),
                        'delivered' => $delivered,
                    ));
                }
                $successMsg = 'OTP sent via ' . implode(' & ', $channels) . '. Please check your messages.';
                if (empty($delivered['whatsapp']) && !empty($delivered['sms'])) {
                    $successMsg = 'OTP sent via SMS'
                        . (!empty($delivered['email']) ? ' and Email' : '')
                        . '. WhatsApp was not delivered — check SMS (and email), or configure WhatsApp API key in ElintOm Settings.';
                } elseif (empty($delivered['whatsapp']) && !empty($delivered['email'])) {
                    $successMsg = 'OTP sent to your email. WhatsApp was not delivered — configure WhatsApp API key in ElintOm Settings.';
                } elseif (!empty($delivered['whatsapp'])) {
                    $successMsg = 'OTP sent via ' . implode(' & ', $channels)
                        . '. If WhatsApp does not arrive within a minute, check SMS and email (including spam).';
                }
                $this->session->set_flashdata('fp_message', $successMsg);
                $this->session->set_flashdata('otp_sent', true);
            } else {
                // Wipe the OTP so the user can retry cleanly.
                $this->session->unset_userdata('forgot_password_otp_data');
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.send_otp.delivery_failed', array(
                        'mobile'    => $mobile,
                        'status'    => isset($delivery['status']) ? $delivery['status'] : null,
                        'msg'       => isset($delivery['msg']) ? $delivery['msg'] : null,
                        'delivered' => $delivered,
                    ));
                } else {
                    log_message('error', 'forgot_password: delivery failed for ' . $this->_mask_secret($mobile)
                        . ' (' . (isset($delivery['msg']) ? $delivery['msg'] : 'no msg') . ')');
                }
                $errMsg = ($delivery && !empty($delivery['msg'])) ? (string) $delivery['msg'] : 'Unable to deliver OTP right now. Please try again.';
                $this->session->set_flashdata('fp_error', $errMsg);
            }
            $this->session->set_flashdata('forgot_mobile', $mobile);
            redirect('webshop/forgot_password');
            return;
        }

        // ── Step 2: verify OTP and reset password ──────────────────────
        if ($this->input->post('reset_password') !== false && $this->input->post('reset_password') !== null) {
            $mobile           = $this->_normalize_mobile($this->input->post('mobile'));
            $otp              = preg_replace('/\D/', '', (string) $this->input->post('otp'));
            $new_password     = (string) $this->input->post('new_password');
            $confirm_password = (string) $this->input->post('confirm_password');

            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('controller.reset_password.start', array('mobile' => $mobile));
            }

            if ($mobile === '' || $otp === '' || $new_password === '' || $confirm_password === '') {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.missing_fields', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('fp_error', 'All fields are required.');
                $this->session->set_flashdata('forgot_mobile', $mobile);
                $this->session->set_flashdata('otp_sent', true);
                redirect('webshop/forgot_password');
                return;
            }
            if (!$this->_is_valid_mobile($mobile)) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.invalid_mobile', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('fp_error', 'Invalid mobile number.');
                $this->session->set_flashdata('error_field', 'mobile');
                redirect('webshop/forgot_password');
                return;
            }
            if (strlen($otp) !== 6) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.bad_otp_length', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('fp_error', 'OTP must be exactly 6 digits.');
                $this->session->set_flashdata('error_field', 'otp');
                $this->session->set_flashdata('forgot_mobile', $mobile);
                $this->session->set_flashdata('otp_sent', true);
                redirect('webshop/forgot_password');
                return;
            }
            if ($new_password !== $confirm_password) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.password_mismatch', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('fp_error', 'Passwords do not match.');
                $this->session->set_flashdata('error_field', 'confirm_password');
                $this->session->set_flashdata('forgot_mobile', $mobile);
                $this->session->set_flashdata('otp_sent', true);
                redirect('webshop/forgot_password');
                return;
            }
            if (strlen($new_password) < 6) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.password_too_short', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('fp_error', 'Password must be at least 6 characters.');
                $this->session->set_flashdata('error_field', 'new_password');
                $this->session->set_flashdata('forgot_mobile', $mobile);
                $this->session->set_flashdata('otp_sent', true);
                redirect('webshop/forgot_password');
                return;
            }

            $otpData = $this->session->userdata('forgot_password_otp_data');
            if (!is_array($otpData) || empty($otpData['otp']) || empty($otpData['mobile']) || empty($otpData['expires_at'])) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.no_otp_session', array(
                        'mobile' => $mobile,
                        'has_session_data' => is_array($otpData),
                    ));
                }
                $this->session->set_flashdata('fp_error', 'OTP session expired. Please request a new OTP.');
                redirect('webshop/forgot_password');
                return;
            }
            if ($otpData['mobile'] !== $mobile) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.mobile_mismatch', array(
                        'posted_mobile'  => $mobile,
                        'session_mobile' => $otpData['mobile'],
                    ));
                }
                $this->session->set_flashdata('fp_error', 'OTP verification failed for this mobile number.');
                redirect('webshop/forgot_password');
                return;
            }
            if (time() > (int) $otpData['expires_at']) {
                $this->session->unset_userdata('forgot_password_otp_data');
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.otp_expired', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('fp_error', 'OTP has expired. Please request a new OTP.');
                redirect('webshop/forgot_password');
                return;
            }
            // Throttle brute-force on the 6-digit OTP — 5 wrong tries invalidates the session.
            $attempts = isset($otpData['attempts']) ? (int) $otpData['attempts'] : 0;
            if ((string) $otpData['otp'] !== $otp) {
                $attempts++;
                $otpData['attempts'] = $attempts;
                if ($attempts >= 5) {
                    $this->session->unset_userdata('forgot_password_otp_data');
                    if (function_exists('webshop_forgot_password_log')) {
                        webshop_forgot_password_log('controller.reset_password.otp_locked', array(
                            'mobile' => $mobile,
                            'attempts' => $attempts,
                        ));
                    }
                    $this->session->set_flashdata('fp_error', 'Too many invalid attempts. Please request a new OTP.');
                } else {
                    $this->session->set_userdata('forgot_password_otp_data', $otpData);
                    if (function_exists('webshop_forgot_password_log')) {
                        webshop_forgot_password_log('controller.reset_password.invalid_otp', array(
                            'mobile' => $mobile,
                            'attempts' => $attempts,
                        ));
                    }
                    $this->session->set_flashdata('fp_error', 'Invalid OTP. ' . (5 - $attempts) . ' attempt(s) left.');
                    $this->session->set_flashdata('error_field', 'otp');
                    $this->session->set_flashdata('forgot_mobile', $mobile);
                    $this->session->set_flashdata('otp_sent', true);
                }
                redirect('webshop/forgot_password');
                return;
            }

            // Hand off the password update to ElintOm (DB-less compliant).
            try {
                $reset = $this->webshop_api_model->reset_customer_password(
                    $mobile,
                    $new_password,
                    $phone_code,
                    isset($this->data['phone_local_digits']) ? (int) $this->data['phone_local_digits'] : 10
                );
            } catch (Exception $e) {
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.api_exception', array(
                        'mobile' => $mobile,
                        'error'  => $e->getMessage(),
                    ));
                } else {
                    log_message('error', 'forgot_password: reset_customer_password threw: ' . $e->getMessage());
                }
                $reset = array('status' => 'ERROR', 'msg' => 'Service temporarily unavailable. Please try again.');
            }

            if ($reset && isset($reset['status']) && $reset['status'] === 'SUCCESS') {
                $this->session->unset_userdata('forgot_password_otp_data');
                if (function_exists('webshop_forgot_password_log')) {
                    webshop_forgot_password_log('controller.reset_password.success', array('mobile' => $mobile));
                }
                $this->session->set_flashdata('message', 'Password has been changed successfully. Please login.');
                redirect('webshop/login');
                return;
            }

            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('controller.reset_password.api_failed', array(
                    'mobile' => $mobile,
                    'status' => isset($reset['status']) ? $reset['status'] : null,
                    'msg'    => isset($reset['msg']) ? $reset['msg'] : null,
                ));
            } else {
                log_message('error', 'forgot_password: reset returned ERROR for ' . $this->_mask_secret($mobile)
                    . ' msg=' . (isset($reset['msg']) ? $reset['msg'] : 'none'));
            }
            $this->session->set_flashdata('fp_error', ($reset && !empty($reset['msg'])) ? (string) $reset['msg'] : 'Failed to update password.');
            $this->session->set_flashdata('forgot_mobile', $mobile);
            $this->session->set_flashdata('otp_sent', true);
            redirect('webshop/forgot_password');
            return;
        }

        $view_path = $this->resolve_webshop_view_path('forgot_password');
        if (function_exists('webshop_forgot_password_log')) {
            webshop_forgot_password_log('controller.render_form', array(
                'view'         => $view_path,
                'theme'        => $theme_name,
                'phone_code'   => $phone_code,
                'local_digits' => isset($this->data['phone_local_digits']) ? $this->data['phone_local_digits'] : null,
                'country'      => $country_setting,
                'api_base'     => $this->config->item('elintom_api_base_url', 'elintom_api'),
            ));
        }

        try {
            $this->load_view('forgot_password', $this->data);
        } catch (Exception $e) {
            if (function_exists('webshop_forgot_password_log')) {
                webshop_forgot_password_log('controller.render_form.exception', array(
                    'view'  => $view_path,
                    'error' => $e->getMessage(),
                ));
            }
            show_error('Forgot password page could not be loaded. Check application/logs for [FP_TRACE] entries.');
        }
    }

    private function _normalize_mobile($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return '';
        }

        $phone_code = $this->_get_cached_phone_code();
        $local_digits = function_exists('webshop_settings_local_phone_length')
            ? webshop_settings_local_phone_length()
            : 10;

        if (function_exists('webshop_phone_digit_variants')) {
            $variants = webshop_phone_digit_variants($s, $phone_code, $local_digits);
            if (!empty($variants)) {
                return $variants[0];
            }
        }

        $digits = preg_replace('/\D/', '', $s);
        if ($phone_code && $local_digits > 0 && strlen($digits) === $local_digits) {
            return $digits;
        }
        if ($phone_code && strpos($digits, $phone_code) !== 0 && strlen($digits) === $local_digits) {
            return $digits;
        }
        return $digits;
    }

    /**
     * Phone for OTP/SMS gateways — prefer international (dial + local).
     */
    private function _mobile_for_otp_delivery($local_mobile)
    {
        $local = preg_replace('/\D/', '', (string) $local_mobile);
        $dial = $this->_get_cached_phone_code();
        if ($dial === '' || $local === '') {
            return $local;
        }
        if (strpos($local, $dial) === 0) {
            return $local;
        }
        return $dial . $local;
    }

    private function _get_cached_phone_code() {
        if (isset($this->_memo_phone_code)) {
            return $this->_memo_phone_code;
        }
        $this->_memo_phone_code = function_exists('webshop_settings_phone_dial_code')
            ? webshop_settings_phone_dial_code()
            : '91';
        return $this->_memo_phone_code;
    }

    /**
     * AJAX: Check if mobile exists (used by Restaurant theme modals)
     */
    public function check_mobile() {
        $mobile = $this->_normalize_mobile($this->input->post('mobile'));
        if ($mobile === '') {
            echo json_encode(array('status' => 'error', 'msg' => 'Invalid mobile'));
            return;
        }
        
        try {
            $customer = $this->webshop_api_model->get_customer(array('phone' => $mobile));
            if ($customer) {
                echo json_encode(array('status' => 'success', 'mobile' => $mobile));
            } else {
                echo json_encode(array('status' => 'error', 'msg' => 'Not found'));
            }
        } catch (Exception $e) {
            echo json_encode(array('status' => 'error', 'msg' => $e->getMessage()));
        }
    }

    /**
     * AJAX forgot-password OTP (restaurant theme). Same ElintOm path as send_otp — not order WhatsApp.
     */
    public function send_whatsapp_otp() {
        $this->load->model('webshop_api_model');
        
        try {
            $mobile = $this->_normalize_mobile($this->input->post('MobileNo'));
            if ($mobile === '') {
                echo json_encode(array('status' => 'error', 'msg' => 'Please enter a valid mobile number.'));
                return;
            }

            $otp = $this->_generate_numeric_otp(6);
            
            // Store in session for verification later
            $this->session->set_userdata('forgot_password_otp_data', array(
                'mobile'     => $mobile,
                'otp'        => $otp,
                'expires_at' => time() + 600, // 10 minutes
                'attempts'   => 0,
            ));

            $res = $this->webshop_api_model->send_password_otp($mobile, $otp);
            
            if ($res && isset($res['status']) && strtoupper((string)$res['status']) === 'SUCCESS') {
                echo json_encode(array(
                    'status' => 'success', 
                    'OTP'    => $otp, 
                    'msg'    => isset($res['msg']) ? $res['msg'] : 'OTP sent successfully.'
                ));
            } else {
                $err = isset($res['msg']) ? $res['msg'] : 'The messaging service returned an unknown error.';
                echo json_encode(array('status' => 'error', 'msg' => $err));
            }
        } catch (Exception $e) {
            log_message('error', 'send_whatsapp_otp: ' . $e->getMessage());
            echo json_encode(array('status' => 'error', 'msg' => 'Internal Error: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX: Login (used by Restaurant theme modals)
     */
    public function ajax_login() {
        $mobile = $this->_normalize_mobile($this->input->post('mobile'));
        $password = $this->input->post('password');
        
        $res = $this->webshop_api_model->login_customer($mobile, $password);
        if ($res && isset($res->status) && strtoupper($res->status) === 'SUCCESS') {
            // Set session etc. (usually handled in a separate private method)
            $this->_handle_login_success($res); 
            echo json_encode(array('status' => 'success', 'redirect' => base_url('webshop/index')));
        } else {
            echo json_encode(array('status' => 'error', 'msg' => 'Invalid credentials'));
        }
    }

    /**
     * AJAX: Check if password was used before (used by Restaurant theme modals)
     */
    public function check_existing_password() {
        echo json_encode(array('exists' => false)); // Stub for UI compatibility
    }

    private function _is_valid_mobile($mobile)
    {
        $digits = preg_replace('/\D/', '', (string) $mobile);
        $len = strlen($digits);
        return $len >= 10 && $len <= 15;
    }

    /**
     * Mask a secret value for log lines — keeps the first 2 and last 2 chars,
     * replaces the middle with asterisks. Matches the rule in
     * .cursor/rules/security-and-secrets.mdc (no full mobiles / keys in logs).
     */
    private function _mask_secret($value)
    {
        $s = (string) $value;
        $n = strlen($s);
        if ($n <= 4) {
            return str_repeat('*', $n);
        }
        return substr($s, 0, 2) . str_repeat('*', $n - 4) . substr($s, -2);
    }

    /**
     * Generate an N-digit numeric OTP. PHP 5.6 compatible — random_int() is
     * PHP 7+ only, so this falls through to openssl_random_pseudo_bytes()
     * (CSPRNG on Windows/Linux when openssl is built in) and finally to
     * mt_rand() as a last resort. Returns '' if no source produced a value.
     */
    private function _generate_numeric_otp($length = 6)
    {
        $length = max(4, min(8, (int) $length));
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_pad('9', $length, '9');

        if (function_exists('random_int')) {
            try {
                return (string) random_int($min, $max);
            } catch (Exception $e) {
                // Fall through to next source.
            }
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = @openssl_random_pseudo_bytes(4, $strong);
            if ($bytes !== false && strlen($bytes) === 4) {
                $unpacked = @unpack('N', $bytes);
                if (is_array($unpacked) && isset($unpacked[1])) {
                    $num = (int) $unpacked[1];
                    if ($num < 0) { $num = -$num; }
                    return (string) ($min + ($num % ($max - $min + 1)));
                }
            }
        }

        // mt_rand is not cryptographically secure but the OTP is short-lived
        // (10 min TTL + 5-attempt lockout), so it's an acceptable last resort.
        if (function_exists('mt_rand')) {
            return (string) mt_rand($min, $max);
        }

        return '';
    }

    /**
     * Deferred online orders use TMP_* placeholders until the gateway confirms payment.
     * The real cart payload lives in session — but many gateways return via cross-site POST,
     * so the session cookie is often empty. Mirror the payload on disk keyed by reference_no
     * and TMP id so callbacks can still create the sale.
     */
    private function _pending_order_payload_cache_dir()
    {
        $dir = APPPATH . 'cache' . DIRECTORY_SEPARATOR . 'pending_orders';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function _pending_order_payload_cache_ttl()
    {
        $t = (int) $this->config->item('sess_expiration');
        return $t > 0 ? $t : 7200;
    }

    private function _pending_order_payload_cache_put(array $payload, $tmp_order_id, $reference_no)
    {
        if (empty($payload['order']) || empty($payload['products']) || !is_array($payload['order'])) {
            return;
        }
        $saved = array('saved_at' => time(), 'payload' => $payload);
        $json = json_encode($saved);
        if ($json === false) {
            return;
        }
        $dir = $this->_pending_order_payload_cache_dir();
        $ref = trim((string) $reference_no);
        if ($ref !== '') {
            @file_put_contents($dir . DIRECTORY_SEPARATOR . 'po_ref_' . sha1($ref) . '.json', $json, LOCK_EX);
        }
        $tmp = trim((string) $tmp_order_id);
        if (strpos($tmp, 'TMP_') === 0) {
            @file_put_contents($dir . DIRECTORY_SEPARATOR . 'po_tmp_' . sha1($tmp) . '.json', $json, LOCK_EX);
        }
    }

    private function _pending_order_payload_cache_read_path($path)
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['payload'], $decoded['saved_at'])) {
            return null;
        }
        if ((time() - (int) $decoded['saved_at']) > $this->_pending_order_payload_cache_ttl()) {
            @unlink($path);
            return null;
        }
        $pl = $decoded['payload'];
        return is_array($pl) ? $pl : null;
    }

    private function _pending_order_payload_cache_get($reference_no = '', $tmp_order_id = '')
    {
        $dir = $this->_pending_order_payload_cache_dir();
        $ref = trim((string) $reference_no);
        if ($ref !== '') {
            $got = $this->_pending_order_payload_cache_read_path($dir . DIRECTORY_SEPARATOR . 'po_ref_' . sha1($ref) . '.json');
            if (is_array($got)) {
                return $got;
            }
        }
        $tmp = trim((string) $tmp_order_id);
        if (strpos($tmp, 'TMP_') === 0) {
            $got2 = $this->_pending_order_payload_cache_read_path($dir . DIRECTORY_SEPARATOR . 'po_tmp_' . sha1($tmp) . '.json');
            if (is_array($got2)) {
                return $got2;
            }
        }
        return null;
    }

    private function _pending_order_payload_cache_delete(array $payload, $tmp_order_id)
    {
        $dir = $this->_pending_order_payload_cache_dir();
        $ref = '';
        if (isset($payload['order']) && is_array($payload['order'])) {
            $ref = trim((string) (isset($payload['order']['reference_no']) ? $payload['order']['reference_no'] : ''));
        }
        if ($ref !== '') {
            @unlink($dir . DIRECTORY_SEPARATOR . 'po_ref_' . sha1($ref) . '.json');
        }
        $tmp = trim((string) $tmp_order_id);
        if (strpos($tmp, 'TMP_') === 0) {
            @unlink($dir . DIRECTORY_SEPARATOR . 'po_tmp_' . sha1($tmp) . '.json');
        }
    }

    /**
     * @param string $tmp_id
     * @param array  $responseHints  Optional map (e.g. CCAvenue) with reference_no / merchant_param1.
     * @return array|null
     */
    private function _resolve_pending_order_payload_for_tmp($tmp_id, array $responseHints = array())
    {
        $payload = $this->session->userdata('pending_order_payload');
        if (is_array($payload) && !empty($payload['order'])) {
            return $payload;
        }
        $refHint = '';
        foreach (array('merchant_param1', 'reference_no', 'merchant_param2') as $k) {
            if (isset($responseHints[$k]) && trim((string) $responseHints[$k]) !== '') {
                $refHint = trim((string) $responseHints[$k]);
                break;
            }
        }
        $fromdisk = $this->_pending_order_payload_cache_get($refHint, $tmp_id);
        if (is_array($fromdisk) && !empty($fromdisk['order'])) {
            return $fromdisk;
        }
        return null;
    }

    /**
     * Instamojo Payment Gateway
     */
    public function payment_instamojoResponseHandler()
    {
        $payment_request_id = $this->input->get('payment_request_id');
        $payment_id = $this->input->get('payment_id');

        $this->data['payment_id'] = $payment_id;

        if (empty($payment_request_id) || empty($payment_id)):
            $this->data['error'] = 'Error in payment process';
            $this->load_view('/decline_order', $this->data);
        endif;

        $this->load->library('instamojo');

        $Transaction = $this->webshop_model->getInstamojoEshopTransaction(array('request_id' => $payment_request_id));

        $order_id = $Transaction->order_id;
        $res12 = $this->webshop_model->updateInstamojoEshopTransaction($payment_request_id, array('payment_id' => $payment_id));


        $this->load->library('instamojo');
        $ci = get_instance();

        $ci->config->load('payment_gateways', TRUE);

        $payment_config = $ci->config->item('payment_gateways');

        $instamojo_credential = $payment_config['instamojo'];

        try {
            $api = new Instamojo($instamojo_credential['API_KEY'], $instamojo_credential['AUTH_TOKEN'], $instamojo_credential['API_URL']);
            $paymentDetail = $api->paymentDetail($payment_id);

            if (is_array($paymentDetail)):
                $pay_res = serialize($paymentDetail);
                $this->webshop_model->updateInstamojoEshopTransaction($payment_request_id, array('success_response' => $pay_res));
                if (isset($paymentDetail["status"]) && in_array($paymentDetail["status"], array('Credit', 'credit', 'Completed'))):
                    
                    // If this was a deferred order (TMP_ prefix), create it in ElintOm now.
                    if (strpos((string) $order_id, 'TMP_') === 0) {
                        $tmp_was = (string) $order_id;
                        $payload = $this->_resolve_pending_order_payload_for_tmp($tmp_was, array());
                        if ($payload && is_array($payload)) {
                            $real_oid = $this->webshop_api_model->add_order($payload['order'], $payload['products']);
                            if ($real_oid) {
                                $order_id = $real_oid;
                                $this->_pending_order_payload_cache_delete($payload, $tmp_was);
                            }
                        }
                    }

                    if (strpos((string) $order_id, 'TMP_') === 0) {
                        log_message('error', 'Instamojo: payment credited but deferred order snapshot missing for ' . $order_id);
                        $this->data['error'] = 'Payment received but your order could not be created. Please contact support with your Instamojo payment id.';
                        $this->load_view('decline_order', $this->data);
                        return;
                    }

                    $res = $this->webshop_model->instomojoEshopAfterSale($paymentDetail, $order_id);
                    if ($res):
                        if ($this->webshop_api_model->uses_elintom_api_for_orders() && ctype_digit((string) $order_id)) {
                            $this->_notify_order_placed_customer((int) $order_id);
                        }
                        $this->data['sale'] = $this->webshop_model->get_order_by_id($order_id);
                        $this->data['success'] = 'Payment done successfully';
 
                        unset($_SESSION['cart']);
                        redirect("webshop/order_success?order=$order_id"); //&customer=$customer_id
                    endif;
                endif;
                $this->data['error'] = 'Payment process under review';
                $this->webshop_model->deleteSale($order_id);
                $this->load_view('decline_order', $this->data);
            endif;
        } catch (Exception $e) {
            $this->data['error'] = $e->getMessage();
            $this->webshop_model->deleteSale($order_id);
            $this->load_view('decline_order', $this->data);
        }
        $this->webshop_model->deleteSale($order_id);
        $this->data['error'] = 'Payment process under review';
        $this->load_view('decline_order', $this->data);
    }

    /**
     * End Instamojo Payment Gateway
     *   
     */
    /**
     * Paytm Payment Gateway
     */

    /**
     * Paytm Payment Gatway
     * @return type
     */
    public function paytm_init($paytmpayment)
    {
        $order_id = isset($paytmpayment['order_id']) ? $paytmpayment['order_id'] : 0;
        $order = null;
        $customer = null;

        if (strpos((string) $order_id, 'TMP_') === 0) {
            $payload = $this->_resolve_pending_order_payload_for_tmp((string) $order_id, array());
            if ($payload && is_array($payload)) {
                $order = (object) $payload['order'];
                $customer_data = $payload['customer'];
                $customer = is_array($customer_data) ? (object) $customer_data : $customer_data;
            }
        } elseif ((int) $order_id > 0) {
            $order = $this->site->getSaleByIDEshop($order_id);
            if ($order && isset($order->customer_id)) {
                $customer = $this->site->getCompanyByID($order->customer_id);
            }
        }

        if ($order) {


                $customer = $this->site->getCompanyByID($order->customer_id);

                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');

                $paytm_credential = $payment_config['paytm'];

                $this->load->library('paytm', $paytm_credential);

                $PAYTM_MERCHANT_KEY = isset($paytm_credential['PAYTM_MERCHANT_KEY']) && !empty($paytm_credential['PAYTM_MERCHANT_KEY']) ? $paytm_credential['PAYTM_MERCHANT_KEY'] : '';

                $PAYTM_MERCHANT_MID = isset($paytm_credential['PAYTM_MERCHANT_MID']) && !empty($paytm_credential['PAYTM_MERCHANT_MID']) ? $paytm_credential['PAYTM_MERCHANT_MID'] : '';

                $API_URL = isset($paytm_credential['PAYTM_TXN_URL']) && !empty($paytm_credential['PAYTM_TXN_URL']) ? $paytm_credential['PAYTM_TXN_URL'] : '';

                $PAYTM_MERCHANT_WEBSITE = isset($paytm_credential['PAYTM_MERCHANT_WEBSITE']) && !empty($paytm_credential['PAYTM_MERCHANT_WEBSITE']) ? $paytm_credential['PAYTM_MERCHANT_WEBSITE'] : '';

                $arr['tid'] = time();


                $paramList["MID"] = $PAYTM_MERCHANT_MID;
                $paramList["ORDER_ID"] = (strpos((string) $order_id, 'TMP_') === 0)
                    ? (string) $order_id
                    : (string) (isset($order->id) ? $order->id : $order_id);
                $paramList["CUST_ID"] = $customer->id;
                $paramList["INDUSTRY_TYPE_ID"] = 'Retail';
                $paramList["CHANNEL_ID"] = 'WEB';
                $paramList["TXN_AMOUNT"] = $this->sma->formatDecimal($order->grand_total);
                $paramList["WEBSITE"] = $PAYTM_MERCHANT_WEBSITE;
                $paramList["MSISDN"] = $customer->phone; //Mobile number of customer
                $paramList["EMAIL"] = $customer->email;  //Email ID of customer
                $paramList["VERIFIED_BY"] = "EMAIL"; //
                $paramList["IS_USER_VERIFIED"] = "YES"; //
                $paramList['CALLBACK_URL'] = base_url('webshop/payment_paytmResponseHandler');

                try {

                    $checkSum = $this->paytm->getChecksumFromArray($paramList, $PAYTM_MERCHANT_KEY);

                    //$this->data['merchant_id']  = $merchant_id;
                    // $this->data['paytm_access_code'] = $access_code;
                    $this->data['paramList'] = $paramList;
                    $this->data['PAYTM_TXN_URL'] = $API_URL;
                    $this->data['CHECKSUMHASH'] = $checkSum;

                    $this->webshop_model->addpaytmTransaction(array('order_id' => $order_id, 'req_data' => $paramList));



                    $this->load_view('paytm', $this->data);
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
        }
    }

    /**
     * Paytm Payment
     */
    public function payment_paytmResponseHandler()
    {

        $this->load->library('paytm');

        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');

        $paytm_credential = $payment_config['paytm'];

        $PAYTM_MERCHANT_KEY = isset($paytm_credential['PAYTM_MERCHANT_KEY']) && !empty($paytm_credential['PAYTM_MERCHANT_KEY']) ? $paytm_credential['PAYTM_MERCHANT_KEY'] : '';

        $PAYTM_MERCHANT_MID = isset($paytm_credential['PAYTM_MERCHANT_MID']) && !empty($paytm_credential['PAYTM_MERCHANT_MID']) ? $paytm_credential['PAYTM_MERCHANT_MID'] : '';

        $API_URL = isset($paytm_credential['API_URL']) && !empty($paytm_credential['API_URL']) ? $paytm_credential['API_URL'] : '';

        $MID = $this->input->post('MID') ? $this->input->post('MID') : null;

        $ORDERID = $this->input->post('ORDERID') ? $this->input->post('ORDERID') : null;

        if ($ORDERID):
            $this->webshop_model->updatePaytmTransaction($ORDERID, array('response_data' => serialize($_POST), 'update_time' => date('Y-m-d H:i:s')));
        endif;

        $STATUS = $this->input->post('STATUS') ? $this->input->post('STATUS') : null;
        $RESPMSG = $this->input->post('RESPMSG') ? $this->input->post('RESPMSG') : null;

        if ($_POST['STATUS'] != 'TXN_SUCCESS') {
            $this->session->set_flashdata('error', $_POST['RESPMSG']);
            if ((int) $ORDERID > 0):
                $getorderdetails = $this->site->getSaleByIDEshop($ORDERID);
                $ref_No = $getorderdetails->reference_no;

                unset($_SESSION['cart']);
                redirect("webshop/order_success?order=$ORDERID");
            else:
                redirect("webshop");
            endif;
        }

        try {
            $api = new Paytm($paytm_credential);
            $requestParamList = array("MID" => $PAYTM_MERCHANT_MID, "ORDERID" => $ORDERID);

            $responseParamList = $api->getTxnStatus($requestParamList);

            $_ORDERID = $responseParamList['ORDERID'] ? $responseParamList['ORDERID'] : null;
            $_STATUS = $responseParamList['STATUS'] ? $responseParamList['STATUS'] : null;
            $_RESPMSG = $responseParamList['RESPMSG'] ? $responseParamList['RESPMSG'] : null;
            $_TXNID = $responseParamList['TXNID'] ? $responseParamList['TXNID'] : null;
            if ($_ORDERID == $ORDERID && $_STATUS == 'TXN_SUCCESS'):

                $msg = 'success';
                $sid = $ORDERID;
                $tracking_id = $_TXNID;

                // If this was a deferred order (TMP_ prefix), create it in ElintOm now.
                if (strpos((string) $sid, 'TMP_') === 0) {
                    $tmp_was = (string) $sid;
                    $payload = $this->_resolve_pending_order_payload_for_tmp($tmp_was, array());
                    if ($payload && is_array($payload)) {
                        $real_oid = $this->webshop_api_model->add_order($payload['order'], $payload['products']);
                        if ($real_oid) {
                            $sid = (string) $real_oid;
                            $this->_pending_order_payload_cache_delete($payload, $tmp_was);
                        }
                    }
                }

                if (strpos((string) $sid, 'TMP_') === 0) {
                    log_message('error', 'Paytm: TXN_SUCCESS but deferred checkout snapshot missing for ' . $sid);
                    $this->session->set_flashdata(
                        'error_message',
                        'Payment was received but your order could not be created. Please contact support with your Paytm transaction id.'
                    );
                    redirect(base_url('webshop/checkout'));
                    return;
                }

                if (!$this->webshop_api_model->uses_elintom_api_for_orders() && ctype_digit((string) $sid) && isset($this->site)) {
                    $getorderdetails = $this->site->getSaleByIDEshop($sid);
                }

                $res = $this->webshop_model->PaytmAfterSale($responseParamList, $sid);
                if ($res):
                    if ($this->webshop_api_model->uses_elintom_api_for_orders() && ctype_digit((string) $sid)) {
                        $this->_notify_order_placed_customer((int) $sid);
                    }
                    $this->session->set_flashdata('message', lang('payment_done'));
                    unset($_SESSION['cart']);
                    redirect("webshop/order_success?order=$sid");

                endif;

                $this->session->set_flashdata('message', $_RESPMSG);
                unset($_SESSION['cart']);
                redirect("webshop/order_success?order=$sid");

            else:
                $this->session->set_flashdata('error', $_RESPMSG);
                unset($_SESSION['cart']);
                redirect("webshop/order_success?order=$sid");

            endif;
        } catch (Exception $e) {
            $this->session->set_flashdata('message', $e->getMessage());
            redirect("webshop");
        }
    }

    /**
     * End Paytm Payment Gatway
     * @return type
     */
    /**
     * End Paytm Payment Gateway
     */

    /**
     * Razorpay Payment Gateway
     */
    public function razorpay_init($data)
    {

        $sale_id = isset($data['order_id']) ? $data['order_id'] : 0;
        $sale = null;
        $customer = null;

        if (strpos((string) $sale_id, 'TMP_') === 0) {
            $payload = $this->_resolve_pending_order_payload_for_tmp((string) $sale_id, array());
            if ($payload && is_array($payload)) {
                $sale = (object) $payload['order'];
                $customer_data = $payload['customer'];
                $customer = is_array($customer_data) ? (object) $customer_data : $customer_data;
            }
        } elseif ((int) $sale_id > 0) {
            $sale = $this->site->getSaleByIDEshop($sale_id);
            if ($sale) {
                $customer = $this->site->getCompanyByID($sale->customer_id);
            }
        }

        if ($sale) {


                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];
                //                	$api = new Api('rzp_test_nEc2AabwdiJ6xf', '0vfdxx3UBkZrjfJL1hg9KrT5');
                //                	$api = new Api('rzp_test_nEc2AabwdiJ6xf', '0vfdxx3UBkZrjfJL1hg9KrT5');
                $api = new Api($paymentData['RAZORPAY_KEY'], $paymentData['RAZORPAY_SECRET']);

                /**
                 * You can calculate payment amount as per your logic
                 * Always set the amount from backend for security reasons
                 */
                $_SESSION['payable_amount'] = $sale->grand_total;
                $rzCur = $this->resolve_payment_currency_iso();
                if ($rzCur === '') {
                    $rzCur = isset($this->Settings->default_currency) ? (string) $this->Settings->default_currency : 'INR';
                }
                $_SESSION['currency'] = $rzCur;

                $receipt = isset($sale->reference_no) ? (string) $sale->reference_no : (string) $sale_id;
                if (function_exists('mb_strlen') && mb_strlen($receipt) > 40) {
                    $receipt = mb_substr($receipt, 0, 40);
                } elseif (strlen($receipt) > 40) {
                    $receipt = substr($receipt, 0, 40);
                }

                $razorpayOrder = $api->order->create(array(
                    'receipt' => $receipt,
                    'amount' => $sale->grand_total * 100,
                    'currency' => $rzCur,
                    'payment_capture' => 1, // auto capture
                ));



                $amount = $razorpayOrder['amount'];

                $razorpayOrderId = $razorpayOrder['id'];


                $_SESSION['razorpay_order_id'] = $razorpayOrderId;
                $datapass = $this->prepareData($amount, $razorpayOrderId);

                $datapass['prefill'] = array(
                    'email' => $customer->email,
                    'contact' => $customer->phone,
                    'name' => $customer->name,
                    'description' => 'sales'
                );
                $custAddr = isset($customer->address) ? (string) $customer->address : '';
                $datapass['notes'] = array(
                    'address' => $custAddr,
                    'merchant_order_id' => (string) $sale_id,
                );
                $datapass['name'] = $this->Settings->site_name;
                $datapass['description'] = '#Order: ' . (isset($sale->reference_no) ? (string) $sale->reference_no : (string) $sale_id);


                $this->data['data'] = $datapass;
                // exit;
                $this->load_view('razorpay', $this->data);
        } else {
            redirect('webshop/checkout');
        }
    }

    /**
     * This function preprares payment parameters
     * @param $amount
     * @param $razorpayOrderId
     * @return array
     */
    public function prepareData($amount, $razorpayOrderId)
    {

        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];
        $data = array(
            "key" => $paymentData['RAZORPAY_KEY'],
            "amount" => $amount,
            "name" => $this->Settings->site_name,
            "theme" => array(
                "color" => "#3868f1"
            ),
            "order_id" => $razorpayOrderId,
        );
        return $data;
    }

    /**
     * This function verifies the payment,after successful payment
     */
    public function razorpay_verify()
    {
        $sid = $this->input->get('sid');
        $sid = $this->input->get('sid');
        $success = true;

        $error = "payment_failed";
        if (empty($_POST['razorpay_payment_id']) === false) {
            $ci = get_instance();
            $ci->config->load('payment_gateways', true);
            $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];

            $api = new Api($paymentData['RAZORPAY_KEY'], $paymentData['RAZORPAY_SECRET']);


            try {

                $attributes = array(
                    'razorpay_order_id' => $_SESSION['razorpay_order_id'],
                    'razorpay_payment_id' => $_POST['razorpay_payment_id'],
                    'razorpay_signature' => $_POST['razorpay_signature'],
                    'amount' => $_SESSION['payable_amount'],
                    'currency' => $_SESSION['currency'],
                );
                $api->utility->verifyPaymentSignature($attributes);
            } catch (SignatureVerificationError $e) {
                $success = false;
                $error = 'Razorpay_Error : ' . $e->getMessage();
            }
        }


        if ($success === true) {
            if (strpos((string) $sid, 'TMP_') === 0) {
                $tmp_was = (string) $sid;
                $payload = $this->_resolve_pending_order_payload_for_tmp($tmp_was, array());
                if ($payload && is_array($payload)) {
                    $real_oid = $this->webshop_api_model->add_order($payload['order'], $payload['products']);
                    if ($real_oid) {
                        $sid = (string) $real_oid;
                        $this->_pending_order_payload_cache_delete($payload, $tmp_was);
                    }
                }
            }
            if (strpos((string) $sid, 'TMP_') === 0) {
                log_message('error', 'Razorpay: signature OK but deferred checkout snapshot missing for ' . $sid);
                $this->session->set_flashdata(
                    'error_message',
                    'Payment was verified but your order could not be created. Please contact support with your Razorpay payment id.'
                );
                redirect(base_url('webshop/checkout'));
                return;
            }
            $res = $this->webshop_model->RazorPayAfterSale($attributes, $sid);

            if ($res):
                if ($this->webshop_api_model->uses_elintom_api_for_orders() && ctype_digit((string) $sid)) {
                    $this->_notify_order_placed_customer((int) $sid);
                }
                $this->session->set_flashdata('message', lang('payment_done'));
                unset($_SESSION['cart']);
                redirect("webshop/order_success?order=$sid");
            endif;
            $this->webshop_model->deleteSale($sid);
            $this->webshop_model->deleteSale($sid);
            $this->data['error'] = 'The transaction has been declined.';
            $this->load_view('decline_order', $this->data);
        }
    }

    /**
     * End Razorpay
     */

    public function action()
    {

        $orderId = $this->input->post('order_id');
        $action = $this->input->post('action');
        $reason = $this->input->post('reason');

        $action = ($action == 'cancel') ? 'cancelled' : 'returned';

        if ($this->webshop_model->orderAction($orderId, ['sale_status' => $action, 'note' => $reason])) {
            $response = [
                'status_code' => 200,
                'status'    => 'success',
                'messages'       => 'Your order status has been changes'
            ];
        } else {
            $response = [
                'status_code' => 500,
                'status'    => 'error',
                'messages'       => 'Sorry, Please try again'
            ];
        }

        $this->json_response($response);
    }


    /**
     * Get Pincode Charges
     */
    public function getpincodecharges()
    {
        $pincode = $this->input->get('pincode');
        $result = $this->webshop_model->pincodecharges($pincode);
        if ($result) {
            $response = [
                'status' => 'success',
            ];
        } else {
            $response = [
                'status' => 'error',
                'charges' => '0',
            ];
        }
        $this->json_response($response);
    }
    public function about_us()
    {
        $this->_render_cms_storefront_page('about_us', 'about_us');
    }

    /**
     * Render using ElintOm CMS admin (sma_pages.url + sections) → plane_vanila index.
     *
     * @param string $storefrontSlug  URI segment (about_us, privacy-policy, custom-slug)
     * @param string $legacyDataKey   View data key when CMS row is missing
     * @return bool
     */
    private function _render_cms_storefront_page($storefrontSlug, $legacyDataKey = '')
    {
        $storefrontSlug = trim((string) $storefrontSlug, '/');
        if ($legacyDataKey === '') {
            $legacyDataKey = str_replace('-', '_', $storefrontSlug);
        }

        // Prefer exact URL from CMS admin nav list (getcmspages) for this page only.
        $navRow = $this->webshop_model->find_cms_nav_page_by_storefront_slug($storefrontSlug);
        $candidates = $this->webshop_model->cms_url_candidates_from_storefront_slug($storefrontSlug);
        if (is_array($navRow) && !empty($navRow['url'])) {
            $adminUrl = '/' . ltrim((string) $navRow['url'], '/');
            array_unshift($candidates, $adminUrl);
            $unique = array();
            foreach ($candidates as $p) {
                $norm = '/' . ltrim((string) $p, '/');
                if ($norm === '//') {
                    $norm = '/';
                }
                $unique[$norm] = $norm;
            }
            $candidates = array_values($unique);
        }

        $cmsPage = $this->webshop_model->find_published_cms_page($candidates);
        if (is_object($cmsPage)) {
            $canonicalSlug = $this->webshop_model->cms_storefront_slug_from_page($cmsPage);
            if ($canonicalSlug === '') {
                $canonicalSlug = $storefrontSlug;
            }
            $this->cms_page($canonicalSlug);
            return true;
        }

        if (is_array($navRow) && !empty($navRow['title'])) {
            $this->data['page_title'] = (string) $navRow['title'];
            $this->data['cms_page_load_error'] = true;
        }

        return $this->_render_legacy_static_fallback($storefrontSlug, $legacyDataKey);
    }

    /**
     * @return bool
     */
    private function _render_legacy_static_fallback($storefrontSlug, $legacyDataKey)
    {
        $theme = isset($this->webshop_settings->webshop_theme)
            ? trim((string) $this->webshop_settings->webshop_theme)
            : '';

        $this->config->load('elintom_api', true);
        $usePlaneVanila = in_array($theme, array('gulfpharmacy', 'nw'), true)
            || trim((string) $this->config->item('elintom_theme_view_folder', 'elintom_api')) !== '';

        $urlPath = '/' . str_replace('_', '-', $storefrontSlug);

        if ($usePlaneVanila) {
            $this->data['is_dynamic_cms_page'] = true;
            $this->data['dynamic_cms_slug'] = $urlPath;
            $this->data['home_has_category_grid'] = false;
            $this->data['home_has_product_grid'] = false;
            $this->_hydrate_legacy_static_model($legacyDataKey);
            $this->hydrate_static_cms_payload($urlPath, $legacyDataKey);
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            $this->load_view('index', $this->data);
            return true;
        }

        $this->_hydrate_legacy_static_model($legacyDataKey);
        $this->hydrate_static_cms_payload($urlPath, $legacyDataKey);
        $this->data['website_setting'] = $this->webshop_model->get_website_setting();

        $viewBase = ($legacyDataKey !== '') ? $legacyDataKey : $storefrontSlug;
        if ($theme === 'restaurant') {
            $this->load_view('webshop_restaurant_t1/' . $viewBase, $this->data);
            return true;
        }
        if ($theme === 'nw') {
            $this->load_view('nw_theme/' . $viewBase, $this->data);
            return true;
        }
        $this->load_view($viewBase, $this->data);
        return true;
    }

    private function _hydrate_legacy_static_model($legacyDataKey)
    {
        switch ((string) $legacyDataKey) {
            case 'about_us':
                $this->data['about_us'] = $this->webshop_model->about_usdata('aboutus');
                break;
            case 'terms_and_conditions':
                $this->data['terms_and_conditions'] = $this->webshop_model->terms_conditions('terms_conditions');
                break;
            case 'privacy_policy':
                $this->data['privacy_policy'] = $this->webshop_model->privacy_policy('policy');
                break;
            case 'contact_us':
                $this->data['contact_us'] = $this->webshop_model->contact_usdata('contact');
                break;
            default:
                break;
        }
    }

    public function terms_and_conditions()
    {
        $this->_render_cms_storefront_page('terms_and_conditions', 'terms_and_conditions');
    }

    public function privacy_policy()
    {
        $this->_render_cms_storefront_page('privacy_policy', 'privacy_policy');
    }

    public function contact_us()
    {
        $this->_render_cms_storefront_page('contact_us', 'contact_us');
    }

    public function blogs()
    {
        $blogTable = $this->resolve_blog_table();
        $this->data['blog_module_missing'] = $blogTable === null;
        $this->data['blogs'] = [];
        $activeTheme = $this->get_active_webshop_theme();

        if ($blogTable !== null) {
            $this->db->where('is_active', 1);
            if ($this->db->field_exists('webshop_theme', $blogTable)) {
                $this->db
                    ->group_start()
                    ->where('webshop_theme', $activeTheme)
                    ->or_where('webshop_theme IS NULL', null, false)
                    ->or_where('webshop_theme', '')
                    ->group_end();
            }
            $this->data['blogs'] = $this->db
                ->order_by('updated_at', 'DESC')
                ->order_by('id', 'DESC')
                ->get($blogTable)
                ->result_array();
        }

        if ($this->webshop_settings->webshop_theme == 'restaurant') {
            $this->load_view("webshop_restaurant_t1/blogs", $this->data);
        } elseif ($this->webshop_settings->webshop_theme == 'nw') {
            $this->load_view("nw_theme/blogs", $this->data);
        } elseif ($this->webshop_settings->webshop_theme == 'gulfpharmacy') {
            $this->load_view("blogs", $this->data);
        } else {
            $this->load_view("blogs", $this->data);
        }
    }

    public function blog_rss()
    {
        $this->load->helper('url');
        $blogTable = $this->resolve_blog_table();
        if ($blogTable === null) {
            show_404();
            return;
        }

        $activeTheme = $this->get_active_webshop_theme();
        $this->db->where('is_active', 1);
        if ($this->db->field_exists('webshop_theme', $blogTable)) {
            $this->db
                ->group_start()
                ->where('webshop_theme', $activeTheme)
                ->or_where('webshop_theme IS NULL', null, false)
                ->or_where('webshop_theme', '')
                ->group_end();
        }
        $rows = $this->db
            ->order_by('updated_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(50)
            ->get($blogTable)
            ->result_array();

        $channelTitle = !empty($this->webshop_settings->meta_title)
            ? (string)$this->webshop_settings->meta_title
            : 'Blog';
        $channelDesc = !empty($this->webshop_settings->meta_description)
            ? (string)$this->webshop_settings->meta_description
            : '';
        $link = site_url('blogs');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "<channel>\n";
        $xml .= '<title>' . htmlspecialchars($channelTitle, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</title>\n";
        $xml .= '<link>' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</link>\n";
        $xml .= '<description>' . htmlspecialchars($channelDesc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</description>\n";
        $xml .= '<atom:link href="' . htmlspecialchars(site_url('blog-rss.xml'), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($rows as $row) {
            $slug = isset($row['slug']) ? trim((string)$row['slug']) : '';
            if ($slug === '') {
                continue;
            }
            $title = isset($row['title']) ? (string)$row['title'] : '';
            $pub = !empty($row['updated_at']) ? date('r', strtotime((string)$row['updated_at'])) : date('r');
            $itemUrl = site_url('blog/' . rawurlencode($slug));
            $desc = isset($row['content']) ? strip_tags((string)$row['content']) : '';
            $desc = htmlspecialchars(mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 500), ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $xml .= "<item>\n";
            $xml .= '<title>' . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</title>\n";
            $xml .= '<link>' . htmlspecialchars($itemUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</link>\n";
            $xml .= '<guid isPermaLink="true">' . htmlspecialchars($itemUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</guid>\n";
            $xml .= '<pubDate>' . htmlspecialchars($pub, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</pubDate>\n";
            $xml .= '<description>' . $desc . "</description>\n";
            $xml .= "</item>\n";
        }

        $xml .= "</channel>\n</rss>";

        $this->output->set_content_type('application/rss+xml', 'UTF-8');
        $this->output->set_output($xml);
    }

    public function blog($slug = null)
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            show_404();
            return;
        }

        $blogTable = $this->resolve_blog_table();
        if ($blogTable === null) {
            show_404();
            return;
        }

        $activeTheme = $this->get_active_webshop_theme();
        $this->db->where('slug', $slug)->where('is_active', 1);
        if ($this->db->field_exists('webshop_theme', $blogTable)) {
            $this->db
                ->group_start()
                ->where('webshop_theme', $activeTheme)
                ->or_where('webshop_theme IS NULL', null, false)
                ->or_where('webshop_theme', '')
                ->group_end();
        }
        $blog = $this->db->get($blogTable)->row_array();

        if (empty($blog)) {
            show_404();
            return;
        }

        $this->data['blog'] = $blog;

        if ($this->webshop_settings->webshop_theme == 'restaurant') {
            $this->load_view("webshop_restaurant_t1/blog_detail", $this->data);
        } elseif ($this->webshop_settings->webshop_theme == 'nw') {
            $this->load_view("nw_theme/blog_detail", $this->data);
        } elseif ($this->webshop_settings->webshop_theme == 'gulfpharmacy') {
            $this->load_view("blog_detail", $this->data);
        } else {
            $this->load_view("blog_detail", $this->data);
        }
    }

    public function track_order($order_id)
    {
        // URL: /webshop/track_order/{md5(id)} from WhatsApp, or reference_no, or numeric id when logged in.
        $identifier = trim((string) $order_id);
        $this->data['order_id']   = $identifier;
        $this->data['identifier'] = $identifier;

        $session_user_id = $this->_get_webshop_session_user_id();
        $this->data['is_logged_in']       = (bool) $session_user_id;
        $this->data['tracking_order']    = array();
        $this->data['tracking_items']    = array();
        $this->data['tracking_error']    = '';
        $this->data['tracking_is_guest'] = false;

        $this->load->model('webshop_api_model');

        if ($identifier === '') {
            $this->data['tracking_error'] = 'Missing tracking reference.';
        } elseif (!$session_user_id) {
            // Guest: only opaque MD5 token or public reference — not raw numeric order id.
            $is_public_token = (bool) preg_match('/^[a-f0-9]{32}$/i', $identifier)
                || (bool) preg_match('/^ES-/i', $identifier);
            if ($is_public_token) {
                $this->data['tracking_is_guest'] = true;
                $tracking = $this->webshop_api_model->get_order_for_tracking_public($identifier);
                if (is_array($tracking) && !empty($tracking['order'])) {
                    $this->data['tracking_order'] = $tracking['order'];
                    $this->data['tracking_items'] = isset($tracking['items']) && is_array($tracking['items']) ? $tracking['items'] : array();
                } else {
                    $this->data['tracking_error'] = 'This tracking link is invalid or has expired.';
                }
            } else {
                $this->data['tracking_error'] = 'Sign in to track this order, or open the link from your order confirmation message.';
            }
        } else {
            $tracking = $this->webshop_api_model->get_order_for_tracking($identifier, $session_user_id);
            if (is_array($tracking) && !empty($tracking['order'])) {
                $this->data['tracking_order'] = $tracking['order'];
                $this->data['tracking_items'] = isset($tracking['items']) && is_array($tracking['items']) ? $tracking['items'] : array();
            } else {
                $this->data['tracking_error'] = 'We could not find an order matching this reference.';
            }
        }

        $theme = isset($this->webshop_settings->webshop_theme) ? (string) $this->webshop_settings->webshop_theme : '';
        if ($theme === 'restaurant') {
            $this->load_view('webshop_restaurant_t1/tracking_order', $this->data);
        } elseif ($theme === 'nw' && !$this->is_plane_vanila_storefront()) {
            $this->load_view('nw_theme/tracking_order', $this->data);
        } else {
            $this->load_view('tracking_order', $this->data);
        }
    }

    private function resolve_blog_table()
    {
        $CI = get_instance();
        if (!isset($CI->db) || !is_object($CI->db)) {
            return null;
        }
        if ($CI->db->table_exists('webshop_blogs')) {
            return 'webshop_blogs';
        }
        if ($CI->db->table_exists('sma_webshop_blogs')) {
            return 'sma_webshop_blogs';
        }
        return null;
    }

    private function get_active_webshop_theme()
    {
        $CI = get_instance();
        if (!isset($CI->db) || !is_object($CI->db)) {
            $ws = $this->webshop_settings;
            return $ws && !empty($ws->webshop_theme) ? (string) $ws->webshop_theme : 'default';
        }
        $ws = $CI->db->select('webshop_theme')->get('webshop_settings')->row();
        return $ws && !empty($ws->webshop_theme) ? (string) $ws->webshop_theme : 'default';
    }

    private function has_active_blogs()
    {
        $blogTable = $this->resolve_blog_table();
        if ($blogTable === null) {
            return false;
        }
        $CI = get_instance();
        if (!isset($CI->db) || !is_object($CI->db)) {
            return false;
        }

        $activeTheme = $this->get_active_webshop_theme();
        $CI->db->where('is_active', 1);
        if ($CI->db->field_exists('webshop_theme', $blogTable)) {
            $CI->db
                ->group_start()
                ->where('webshop_theme', $activeTheme)
                ->or_where('webshop_theme IS NULL', null, false)
                ->or_where('webshop_theme', '')
                ->group_end();
        }

        return ((int) $CI->db->count_all_results($blogTable)) > 0;
    }
    /////////////////////// Whats App Integration //////////////////////////
    public function getShippingAddress($shipping_address_id)
    {
        $this->db->select('*');
        $this->db->from('sma_addresses');
        $this->db->where('id', $shipping_address_id);
        $query = $this->db->get();
        return $query->row();
    }
    public function getcountryCode($contryName)
    {
        $this->db->select('*');
        $this->db->from('sma_country_master');
        $this->db->where('name', $contryName);
        $query = $this->db->get();
        return $query->row()->code;
    }
    /**
     * WhatsApp router: API mode → ElintOm notifywebshoporderwhatsapp; else local Whatsapp_model.
     * Used by legacy checkout, Urbanpiper call_whatsapp_api (Ready), and Cheerio YES/NO webhook get_order_reply.
     *
     * @param string $phone      Ignored in API mode (ElintOm loads phone from order address)
     * @param string $orderflag  'true' | 'YES' | 'NO' | 'Ready'
     */
    public function call_whatsapp_cheerio($phone, $order_id, $orderflag)
    {
        $this->load->model('webshop_api_model');
        $order_id = (int) $order_id;
        if ($this->webshop_api_model->uses_elintom_api_for_orders() && $order_id > 0) {
            return $this->webshop_api_model->notify_order_placed_whatsapp_remote($order_id, (string) $orderflag);
        }
        $this->load->model('Whatsapp_model');
        return $this->Whatsapp_model->send_order_whatsapp_message($phone, $order_id, $orderflag);
    }
    public function getTrackingData()
    {
        $order_id = $this->input->post('order_id');
        if (!$order_id) {
            $this->json_response(['status' => 'error', 'message' => 'Missing order ID']);
            return;
        }
        $trackingData = $this->webshop_model->getFullOrderDatahashkey($order_id);
        $fulladdress = '';
        if (!empty($trackingData['order']['billing_address_id'])) {
            $this->load->model('webshop_api_model');
            if ($this->webshop_api_model->uses_elintom_api_for_orders() && !empty($trackingData['address'])) {
                $addr = is_array($trackingData['address']) ? (object) $trackingData['address'] : $trackingData['address'];
                $this->load->helper('webshop_whatsapp');
                $fulladdress = webshop_whatsapp_format_address($addr);
            } else {
                $this->load->model('Whatsapp_model');
                $fulladdress = $this->Whatsapp_model->get_full_address($trackingData['order']['billing_address_id']);
            }
        }
        $trackingData['order']['deliver_to'] = $fulladdress ? $fulladdress : '';
        if ($trackingData) {
            $this->json_response(['status' => 'success', 'tracking' => $trackingData]);
        } else {
            $this->json_response(['status' => 'error', 'message' => 'No tracking data found']);
        }
    }

    /**
     * Lightweight JSON poller used by the Gulf Pharmacy tracking page. Returns just
     * the current sale_status so the client-side stepper can advance without
     * re-fetching the whole order row.
     */
    public function track_order_status()
    {
        $session_user_id = $this->_get_webshop_session_user_id();
        if (!$session_user_id) {
            $this->json_response(array('status' => 'FAIL', 'error' => 'Unauthorized'));
            return;
        }

        $identifier = trim((string) $this->input->post('identifier'));
        if ($identifier === '') {
            $identifier = trim((string) $this->input->post('order_id'));
        }
        if ($identifier === '') {
            $this->json_response(array('status' => 'FAIL', 'error' => 'Missing identifier'));
            return;
        }

        $this->load->model('webshop_api_model');
        $tracking = $this->webshop_api_model->get_order_for_tracking($identifier, $session_user_id);
        if (!is_array($tracking) || empty($tracking['order'])) {
            $this->json_response(array('status' => 'FAIL', 'error' => 'Order not found'));
            return;
        }

        $order = $tracking['order'];
        $this->json_response(array(
            'status'         => 'OK',
            'sale_status'    => isset($order['sale_status']) ? (string) $order['sale_status'] : '',
            'payment_status' => isset($order['payment_status']) ? (string) $order['payment_status'] : '',
            'csrf_hash'      => $this->security->get_csrf_hash(),
        ));
    }
    /** AJAX: order status → WhatsApp (e.g. Ready for pickup). Routes through call_whatsapp_cheerio(). */
    public function call_whatsapp_api($order_id = null)
    {
        $order_id = $this->input->post('order_id');
        $order_status = $this->input->post('order_status');

        $response = '';
        if ($order_status == 'Ready') {

            $trackingData = $this->webshop_model->getFullOrderData($order_id);
            $shipping_address_id = $trackingData['order']['billing_address_id'];
            $customer = $this->getShippingAddress($shipping_address_id);
            $country_code = $this->getcountryCode($customer->country);
            $phone = $country_code . $customer->phone;

            $response = $this->call_whatsapp_cheerio($phone, $order_id, $order_status);
        }
        if ($response) {
            $this->json_response(['status' => 'success', 'tracking' => $trackingData]);
        } else {
            $this->json_response(['status' => 'success', 'tracking' => $trackingData]);
        }
    }

    private function _get_webshop_session_user_id()
    {
        $ws_sess = $this->session->userdata('webshop');
        if (!$ws_sess) {
            return 0;
        }
        return (int) (is_object($ws_sess)
            ? (isset($ws_sess->user_id) ? $ws_sess->user_id : 0)
            : (isset($ws_sess['user_id']) ? $ws_sess['user_id'] : 0));
    }

    public function getCartTotal()
    {
        $cart = $this->session->userdata('cart');
        $carttotal = 0;
        $charges = 0;
        $total = $this->sma->formatMoney(0);
        if (!is_array($cart)) {
            $this->json_response(['total' => $total, 'charges' => $this->sma->formatMoney($charges)]);
            return;
        }
        foreach ($cart as $item) {
            $price = isset($item['price']) ? (float) $item['price'] : 0;
            $qty = isset($item['quantity']) ? (float) $item['quantity'] : 1;
            $carttotal += $price * $qty;
            $total = $this->sma->formatMoney($carttotal);
            $charges = isset($item['charges']) ? (float) $item['charges'] : $charges;
        }
        $this->json_response(['total' => $total, 'charges' => $this->sma->formatMoney($charges)]);
    }
    /**
     * Webhook: customer YES/NO on order summary → WhatsApp via call_whatsapp_cheerio().
     */
    public function get_order_reply()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->json_response(['status' => 'error', 'message' => 'Invalid JSON format.'], 400);
            return;
        }

        $mobile = $data['mobile'];
        $status = $data['order_msg_response'];
        $order_id = $data['order_id'];

        if ($status == 'YES' || $status == 'NO') {
            $response = $this->call_whatsapp_cheerio($mobile, $order_id, $status);
        } else {
            $this->json_response(['status' => 'error', 'message' => 'Invalid status.']);
            return;
        }
    }
    ///////////////////////////////////// Contact Submission ////////////////////////////////////////

    public function submit_contact()
    {
        if ($this->input->post()) {
            $this->load->library('form_validation');
            $this->form_validation->set_rules('name', 'Name', 'required|trim');
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email|trim');
            $this->form_validation->set_rules('phone', 'Phone', 'trim');
            $this->form_validation->set_rules('subject', 'Subject', 'required|trim');
            $this->form_validation->set_rules('message', 'Message', 'required|trim');

            if ($this->form_validation->run() == true) {
                $name = $this->input->post('name', TRUE);
                $email = $this->input->post('email', TRUE);
                $phone = $this->input->post('phone', TRUE);
                $subject = $this->input->post('subject', TRUE);
                $message = $this->input->post('message', TRUE);

                // Get admin email from settings
                $admin_email = $this->Settings->default_email;
                if (empty($admin_email)) {
                    $admin_email = $this->Settings->email;
                }

                // var_dump($admin_email);exit;

                // Prepare email content for admin
                $email_content = "
                <h3>New Contact Form Submission</h3>
                <p><strong>Name:</strong> {$name}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Phone:</strong> {$phone}</p>
                <p><strong>Subject:</strong> {$subject}</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <p><small>Submitted on: " . date('Y-m-d H:i:s') . "</small></p>
                ";

                // Send email to admin
                $subject_line = "Contact Form: " . $subject;
                if ($this->sma->send_email($admin_email, $subject_line, $email_content)) {

                    // Send confirmation email to user
                    $user_content = "
                    <h3>Thank you for contacting us!</h3>
                    <p>Dear {$name},</p>
                    <p>We have received your message and will get back to you shortly.</p>
                    <p><strong>Your message details:</strong></p>
                    <p><strong>Subject:</strong> {$subject}</p>
                    <p><strong>Message:</strong></p>
                    <p>" . nl2br(htmlspecialchars($message)) . "</p>
                    <p>Best regards,<br>Herbinn Micro Medicines Team</p>
                ";
                    $this->sma->send_email($email, "Thank you for contacting us", $user_content);

                    $this->session->set_flashdata('success', 'Thank you for contacting us. We will get back to you soon!');
                } else {
                    $this->session->set_flashdata('error', 'Sorry, there was an error sending your message. Please try again.');
                }
            } else {
                $this->session->set_flashdata('error', validation_errors());
            }
        }
        redirect('webshop/contact_us');
    }
}

//end Class