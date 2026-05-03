<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH . "libraries/razorpay/razorpay-php/Razorpay.php");

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class Webshop extends MY_Controller
{

    public $assets;
    public $viewpath;
    public $data;
    public $webshop_settings;
    public $is_admin_login;
    private $themePageSeoStore = APPPATH . 'cache/theme_page_seo.json';
    private $seoExtendedStore = APPPATH . 'cache/seo_extended_settings.json';

    public function __construct()
    {
        parent::__construct();

        $this->load->library('sma');

        // $this->load->model('site'); // Disabled in DB-less mode

        $this->load->model('webshop_api_model');
        $this->webshop_model = $this->webshop_api_model;

        $this->load->helper('webshop_helper');

        $mediaBase = $this->webshop_api_model->get_media_uploads_base();
        $this->data['uploads'] = $mediaBase;
        $this->data['thumbs'] = $mediaBase . 'thumbs/';
        $this->data['images'] = $mediaBase . 'images/';

        $this->data['assets'] = base_url('assets/webshop/');

        $this->data['is_admin_login'] = $this->is_admin_login = ($this->loggedIn && ($this->Owner || $this->Admin)) ? true : false;

        $this->active_webshop = $this->webshop_api_model->get_active_webshop_flag();
       
        if (!$this->active_webshop && $this->uri->segment(2) != 'service_off') {
            redirect('webshop/service_off');
        }
       
        $this->data['webshop_settings'] = $this->webshop_settings = $this->webshop_model->get_webshop_settings();

        $this->data['home_page'] = $this->webshop_settings->home_page;

        $this->data['theme_color'] = !empty($this->webshop_settings->theme_color) ? $this->webshop_settings->theme_color : 'orange';

        $this->data['strip_color'] = !empty($this->webshop_settings->header_strip_style) ? $this->webshop_settings->header_strip_style : 1;

        $this->data['categories'] = $this->webshop_model->get_categories();
        if (!is_array($this->data['categories'])) {
            $this->data['categories'] = ['main' => []];
        }
        $this->data['main_categories'] = isset($this->data['categories']['main']) && is_array($this->data['categories']['main']) ? $this->data['categories']['main'] : [];

        $this->data['webshop_pos_settings'] = $this->webshop_model->get_webshop_pos_settings();

        $category_id = isset($_GET['catid']) && $_GET['catid'] != '' ? $_GET['catid'] : (($this->uri->segment(2) == "category_products" && !empty($this->uri->segment(3))) ? $this->uri->segment(3) : null);

        $this->data['category_brands'] = $this->webshop_model->get_category_brands($category_id);

        if (!empty($this->data['category_brands'])) {
            $this->data['brands_list'] = $this->get_brand_list($this->data['category_brands']);
        }

        $this->data['all_brands'] = $this->webshop_model->get_all_brands();

        $this->data['cart_items'] = [];
        $this->data['cart_data'] = [];
        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            $this->data['cart_items'] = $_SESSION['cart'];
            $this->data['cart_data'] = $this->webshop_model->get_cart_data();
        }

        $webshopSession = isset($this->session->webshop) && is_object($this->session->webshop) ? $this->session->webshop : null;
        $webshopUserId = ($webshopSession && isset($webshopSession->user_id)) ? $webshopSession->user_id : null;
        $this->data['wishlist_count'] = $this->webshop_model->get_wishlist_count($webshopUserId);

        $this->data['custom_pages'] = $this->webshop_model->getCustomPages();
        $this->data['header_theme_pages'] = $this->get_theme_navigation_pages('header');
        $this->data['footer_theme_pages'] = $this->get_theme_navigation_pages('footer');
        $this->data['has_active_blogs'] = $this->has_active_blogs();

        $this->data['restaurant_is_active'] = $this->webshop_model->restaurantWorking();

        if ($this->webshop_settings->webshop_theme == 'nw' || $this->webshop_settings->webshop_theme == 'gulfpharmacy') {
            $this->data['about_us'] = $this->webshop_model->about_usdata($page_key = 'aboutus');
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
        }
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
     * Resolve storefront view: try, in order,
     *   webshop/                    (application/views/webshop/ — primary)
     *   views/webshop/            (application/views/views/webshop/ — older duplicate tree)
     *   default/views/webshop/     (legacy bundle)
     */
    private function resolve_webshop_view_path($method)
    {
        $method = trim((string) $method);
        if ($method === '') {
            return 'webshop/';
        }
        $candidates = array(
            array(VIEWPATH . 'webshop/' . $method . '.php', 'webshop/' . $method),
            array(VIEWPATH . 'views/webshop/' . $method . '.php', 'views/webshop/' . $method),
            array(VIEWPATH . 'default/views/webshop/' . $method . '.php', 'default/views/webshop/' . $method),
        );
        foreach ($candidates as $pair) {
            if (is_file($pair[0])) {
                return $pair[1];
            }
        }
        return 'webshop/' . $method;
    }

    public function load_view($method = '', $data = array())
    {
        $seoKey = $this->get_theme_page_seo_key($method);
        $data['page_seo'] = $this->get_theme_page_seo($seoKey);
        if (isset($data['page_seo']['is_active']) && (int)$data['page_seo']['is_active'] === 0) {
            show_404();
            return;
        }
        $html = $this->load->view($this->resolve_webshop_view_path($method), $data, true);
        $html = $this->inject_page_seo($html, $data['page_seo'], $data, $method);
        $this->output->set_output($html);
    }

    public function _remap($method, $params = [])
    {
        if ($method !== '_remap' && method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $params);
        }

        if (empty($params) && $this->render_theme_slug_page($method)) {
            return;
        }

        show_404();
    }

    private function render_theme_slug_page($method)
    {
        $slug = preg_replace('/[^a-z0-9_]/i', '', (string)$method);
        if ($slug === '' || in_array($slug, ['index', 'login', 'register', 'cart', 'checkout'], true)) {
            return false;
        }

        $themeFolder = $this->resolve_theme_folder();
        $candidate = ($themeFolder ? $themeFolder . '/' : '') . $slug;
        $flatPath = VIEWPATH . 'webshop/' . $candidate . '.php';
        $dupPath = VIEWPATH . 'views/webshop/' . $candidate . '.php';
        $legacyPath = VIEWPATH . 'default/views/webshop/' . $candidate . '.php';
        if (is_file($flatPath)) {
            $fullPath = $flatPath;
        } elseif (is_file($dupPath)) {
            $fullPath = $dupPath;
        } elseif (is_file($legacyPath)) {
            $fullPath = $legacyPath;
        } else {
            $fullPath = null;
        }
        $seoData = $this->get_theme_page_seo($candidate . '.php');

        if ($fullPath === null || !is_file($fullPath)) {
            return false;
        }
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
        $theme = isset($this->webshop_settings->webshop_theme) ? $this->webshop_settings->webshop_theme : '';
        if ($theme === 'restaurant') {
            return 'webshop_restaurant_t1';
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
        if (!is_string($html)) {
            return $html;
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
                $html = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>' . $safeTitle . '</title>', $html, 1);
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
            $html = preg_replace('/<\/head>/i', $seoBlock . "\n</head>", $html, 1);
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

        $html = preg_replace('/<link\s+[^>]*\brel\s*=\s*["\']canonical["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<meta\s+[^>]*\bname\s*=\s*["\']robots["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<meta\s+[^>]*\bproperty\s*=\s*["\']og:[a-z_:]+["\'][^>]*>/i', '', $html);

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
            return preg_replace('/<\/head>/i', $block . '</head>', $html, 1);
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

        $action = $_POST['action'];
        $postData = $_POST;

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

            case "apply_coupon":

                $this->apply_coupon($postData);

                break;

            case "manage_address":

                $this->manage_address($postData);

                break;

            case "manage_eshop_category":

                $this->manage_eshop_category($postData);

                break;

            default:
                break;
        } //end switch.
    }

    public function index()
    {
        if (!$this->active_webshop) {
            $this->load_view("service_off", $this->data);
        } else {
            $this->data['themeSections'] = $themeSections = $this->webshop_model->get_theme_sections($this->webshop_settings->home_page);

            $this->set_theme_sections_data($themeSections);

            $this->data['sliders'] = $this->webshop_model->get_sliders();

            $this->data['features'] = $this->webshop_model->get_features();

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            $theme = $this->input->get('theme');
            if ($theme) {
                $this->webshop_model->setTheme($theme);
            }

            if ($this->webshop_settings->webshop_theme == 'restaurant') {
                $this->load_view("webshop_restaurant_t1/index", $this->data);
            } else if ($this->webshop_settings->webshop_theme == 'nw') {
                $this->load_view("nw_theme/index", $this->data);
            } else if ($this->webshop_settings->webshop_theme == 'gulfpharmacy') {
                $this->load_view("gulfpharmacy_theme/index", $this->data);
            } else {
                $this->load_view("index", $this->data);
            }
        }
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

        $page = (isset($_GET['page']) && !empty($_GET['page'])) ? $_GET['page'] : 1;
        $limit = 12;

        if ($_GET['q'] == "cetegory") {
            $idHash = $_GET['id'];
            $data = $this->webshop_model->get_products_list('category', $idHash, $usedHash = TRUE, $limit, $page);

            $this->data['items_total'] = $data['items_total'];
            $this->data['listItems'] = $data['items'];
            //$this->data['product_variants']   = $data['product_variants'];
        }

        if ($_GET['q'] == "brand") {
            $idHash = $_GET['id'];
            $data = $this->webshop_model->get_products_list('brand', $idHash, $usedHash = TRUE, $limit, $page);

            $this->data['items_total'] = $data['items_total'];
            $this->data['listItems'] = $data['items'];
        }

        $this->data['idHash'] = $idHash;
        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $this->load_view("products", $this->data);
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
        $this->data['product_variants'] = $productDetails['variants'];
        $this->data['gallary_images'] = $productDetails['images'];

        $this->data['active_search_category'] = $productDetails['item']['category_id'];

        $this->webshop_model->set_recent_viewed_product($productDetails['item']['id']);

        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $categoryHash = ($product['subcategory_id']) ? md5($product['subcategory_id']) : md5($product['category_id']);
        $reletedItems = $this->webshop_model->get_products_list('category', $categoryHash, $usedHash = TRUE, 20);
        $this->data['related_products'] = $reletedItems['items'];

        $productStatus = $this->webshop_model->productAvailable($product['id']);
        $this->data['product']['product_is_active'] = $productStatus[0];
        $this->data['product']['product_info_text'] = $productStatus[1];
        $this->data['product']['category_is_active'] = $this->webshop_model->categoryActive($product['category_id']);
        $this->data['website_setting'] = $this->webshop_model->get_website_setting();
        $raw_settings = (isset($this->data['website_setting']) && (is_array($this->data['website_setting']) || is_object($this->data['website_setting'])))
            ? $this->data['website_setting']
            : [];
        $setting_map = [];
        foreach ($raw_settings as $row) {
            $setting_map[$row->fields] = $row->value;
        }

        if ($this->webshop_settings->webshop_theme == 'restaurant') {
            $this->load_view("webshop_restaurant_t1/product_details", $this->data);
        } else if ($this->webshop_settings->webshop_theme == 'nw') {
            $this->load_view("nw_theme/product_details", $this->data);
        } else if ($this->webshop_settings->webshop_theme == 'gulfpharmacy') {
            $this->load_view("gulfpharmacy_theme/product_details", $this->data);
        } else {
            $this->load_view("product_details", $this->data);
        }
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

        $page = (isset($_GET['page']) && !empty($_GET['page'])) ? (int) $_GET['page'] : 1;
        $limit = 12;
        $data = $this->webshop_model->get_products_list('category', $idHash, $usedHash = TRUE, $limit, $page);

        $products = [];
        $specialItemsList = [];
        $restaurantWorking = $this->webshop_model->restaurantWorking();
        $restaurantOpen = isset($restaurantWorking['is_working']) ? $restaurantWorking['is_working'] : "true";
        $restaurantStatusText = isset($restaurantWorking['working_flag_text']) ? $restaurantWorking['working_flag_text'] : "Open";
        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                list($available, $availabilityText) = $product_available_data = $this->webshop_model->productAvailable($item['id']);
                list($categoryActive, $categoryInfoText) = $this->webshop_model->categoryActive($item['category_id']);

                $item['product_is_active'] = $available;
                $item['product_info_text'] = $availabilityText;
                $item['category_is_active'] = $categoryActive;
                $item['category_info_text'] = $categoryInfoText;
                $item['restaurant_is_active'] = $restaurantOpen;
                $item['restaurant_status_text'] = $restaurantStatusText;

                if (!in_array($item['id'], $specialItemsId, $strict = false)) {
                    $products[] = $item;
                } else {
                    $specialItemsList[] = $item;
                }
            }
        }

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

        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();


        if ($this->webshop_settings->webshop_theme == 'restaurant') {

            foreach ($this->data['listItems'] as &$item) {
                $item['proudctIdHash'] = md5($item['id']);
                $item['formatedPrice'] = $this->sma->formatMoney($item['price']);
            }

            foreach ($this->data['special_items'] as &$special_item) {
                $special_item['proudctIdHash'] = md5($special_item['id']);
                $special_item['formatedPrice'] = $this->sma->formatMoney($special_item['special_price']);
            }
            echo json_encode($this->data);
        } else if ($this->webshop_settings->webshop_theme == 'nw') {
            foreach ($this->data['listItems'] as &$item) {
                $item['proudctIdHash'] = md5($item['id']);
                $item['formatedPrice'] = $this->sma->formatMoney($item['price']);
            }
            $this->load_view("nw_theme/category_products", $this->data);
        } else if ($this->webshop_settings->webshop_theme == 'gulfpharmacy') {
            foreach ($this->data['listItems'] as &$item) {
                $item['proudctIdHash'] = md5($item['id']);
                $item['formatedPrice'] = $this->sma->formatMoney($item['price']);
            }
            $this->load_view("gulfpharmacy_theme/category_products", $this->data);
        } else {
            $this->load_view("category_products", $this->data);
        }
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

        $this->load_view("search_products", $this->data);
    }

    public function wishlist()
    {

        $wishlist = $this->webshop_model->get_wishlist($this->session->webshop->user_id);

        foreach ($wishlist as $list) {
            $products[] = $list->product_id;

            $this->data['wishlist_variants'][$list->product_id][] = $list->option_id;
        }

        $this->data['wishlist'] = $this->webshop_model->get_products_list('products', $products, true);

        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $theme = $this->webshop_settings->webshop_theme;
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

    public function cart()
    {

        // var_dump($this->data);
        // exit;

        $theme = $this->webshop_settings->webshop_theme;
        if ($theme == 'restaurant') {
            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
            $this->data['state_list'] = $this->webshop_model->get_state();
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            $setting_map = [];
            foreach ($raw_settings as $row) {
                $setting_map[$row->fields] = $row->value;
            }
            // if($this->input->get("getCart") == "1"){
            // print_r($this->data);
            //     echo json_encode($this->data);
            //     return;
            // }
            $this->load_view("webshop_restaurant_t1/cart", $this->data);
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
            $this->load_view("nw_theme/cart", $this->data);
        } else if ($theme == 'gulfpharmacy') {
            // $hideCategories = ['Veterinary Nutraceuticals', 'Softgel Capsules'];
            // foreach ($this->data['cart_items'] as &$item) {
            //     $idHash = md5($item['product_id']);
            //     $productDetails = $this->webshop_model->get_product_by_hash($idHash);
            //     $categoryId = $productDetails['item']['category_id'];
            //     $catName = rtrim($this->data['categories']['main'][$categoryId]->name);
            //     $item['show_price'] = !in_array($catName, $hideCategories);
            // }
            // unset($item);
            $this->load_view("gulfpharmacy_theme/cart", $this->data);
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

            if (md5(date('Y-m-d H')) == $this->input->post('submit_order')) {

                if ($this->input->post('default_shipping_address') && $this->input->post('customer_id')) {
                    $customer_id = $this->input->post('customer_id');
                    $address_id = $this->input->post('default_shipping_address');
                    $address = $this->webshop_model->get_customer_address($customer_id, $address_id);
                    $billing_address = $address[$address_id];
                    $shipping_address = $address[$address_id];

                    $customer = $this->webshop_model->get_customer(['id' => $customer_id]);

                    $shipping_address_id = $billing_address_id = $address_id;
                } else {
                    if (isset($_POST['billing_address_1'])) {
                        $billing_stateData = explode('~', $this->input->post('billing_state'));

                        $billing_phone = $this->input->post('billing_phone');
                        $billing_email = $this->input->post('billing_email');
                        $customer = $this->webshop_model->get_customer(['phone' => $billing_phone]);

                        if (!$customer) {

                            $account_password = NULL;
                            if (!empty($this->input->post('account_password'))) {
                                $account_password = md5($this->input->post('account_password'));
                            }
                            $country_code_raw = $_POST['billing_country'];
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

                            $this->send_welcome_mail($customerData);
                        }
                    } else {
                        $customer_id = $this->input->post('customer_id');
                        $customer = $this->webshop_model->get_customer(['id' => $customer_id]);

                        $address_id =   $this->webshop_model->getAddressDefault($this->input->post('customer_id'), 'default');
                        $shipping_address_id = $billing_address_id = $address_id;
                    }


                    if (!empty($this->input->post('billing_address_id'))) {
                        $billing_address_id = $this->input->post('billing_address_id');
                    } else {
                        if (isset($_POST['billing_address_1'])) {
                            $country_code_raw = $_POST['billing_country'];
                            if ($country_code_raw) {
                                $country_parts = explode('~', $country_code_raw);
                                $country = $country_parts[1];
                            }
                            $billing_address = array(
                                "company_id" => $customer['id'],
                                "address_name" => $this->input->post('billing_first_name') . ' ' . $this->input->post('billing_last_name'),
                                "company_name" => $this->input->post('billing_company'),
                                "line1" => $this->input->post('billing_address_1'),
                                "line2" => $this->input->post('billing_address_2'),
                                "city" => $this->input->post('billing_city'),
                                "postal_code" => $this->input->post('billing_postcode'),
                                "state" => $billing_stateData[0],
                                "state_code" => $billing_stateData[1],
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

                            $shipping_address = $billing_address;
                            $shipping_address['address_type'] = 'shipping';

                            $shipping_address_id = $billing_address_id;
                        } else {
                            if (isset($_POST['shipping_address_1'])) {
                                $shipping_stateData = explode('~', $this->input->post('shipping_state'));
                                $country_code_raw = $_POST['billing_country'];
                                if ($country_code_raw) {
                                    $country_parts = explode('~', $country_code_raw);
                                    $country = $country_parts[1];
                                }
                                $shipping_address = array(
                                    "company_id" => $customer['id'],
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
                                $shipping_address_id =   $this->webshop_model->getAddressDefault($customer['id'], 'shipping');
                            }
                        }
                    } //end if.
                } //end else

                $warehouse_id = $this->webshop_settings->warehouse_id;
                $biller_id = $this->webshop_settings->biller_id;
                $biller = $this->webshop_model->get_company_by_id($biller_id);
                if (!is_array($biller)) {
                    $biller = [];
                }
                $biller += ['name' => '', 'state_code' => ''];

                if ((!empty($customer['state_code']) && !empty($biller['state_code'])) && $customer['state_code'] != $biller['state_code']) {
                    $interStateTax = true;
                } else {
                    $interStateTax = false;
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

                        $option_id = $cart_options[$key];
                        $option_price = (float) $cart_option_price[$key];
                        $quintity = (float) $cart_item_quantity[$key];
                        $unit_quantity =  $quintity *  $cart_item_unit_quantity[$key];

                        $unit_price = (float) $cart_item_unit_price[$key];
                        $tax_rate = (float) $cart_item_tax_rate[$key];
                        $tax_method = $cart_item_tax_method[$key];
                        $promotion_price = (float) $cart_item_promotion_price[$key];
                        $item_price = (float) $cart_item_product_price[$key];

                        $selectData = "id,code,article_code,name,unit AS unit_id,eshop_price As price,weight,cf1,cf2,tax_rate AS tax_id , tax_method, type AS product_type, sale_unit AS sale_unit_id, mrp, hsn_code, storage_type, promotion, promo_price,start_date,end_date";

                        $productData = $this->webshop_model->get_product_by_id($product_id, $selectData);

                        $product = $productData[$product_id];

                        $product['tax_rate'] = $tax_rate;

                        $unit_code = $units[$product['sale_unit_id']]['code'];
                        $variant_price = array('1' => $option_price); //Send para value in array
                        //Helper Function
                        $item_count = count($cart_item_unit_quantity);
                        $discount_value = is_numeric($discount) ? (float) $discount : 0.0;
                        $perproductdiscount = ($item_count > 0) ? ($discount_value / $item_count) : 0.0;
                        $productPrice = product_sale_price_webshop($product, $variant_price, $perproductdiscount, $unit_quantity);
                        $invoice_unit_price = $productPrice['net_unit_price'];
                        // $invoice_net_unit_price = $productPrice['net_unit_price'] + $productPrice['unit_discount'] + $productPrice['unit_tax'];
                        $invoice_net_unit_price = $productPrice['net_unit_price'] + $productPrice['unit_discount'];
                        $net_price = $unit_quantity * $product['mrp'];
                        $invoice_total_net_unit_price = $invoice_net_unit_price * $unit_quantity;
                        $item_tax = $productPrice['unit_tax'] * $unit_quantity;
                        // $item_discount = $productPrice['unit_discount'] * (float) $unit_quantity;
                        $item_discount = $productPrice['unit_discount'];
                        // $subtotal = (($productPrice['net_unit_price'] * (float) $unit_quantity) + (float) $item_tax);
                        $subtotal = ($productPrice['net_unit_price'] * $unit_quantity);
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

                        $products[] = array(
                            "product_id" => $product_id,
                            "product_code" => $product['code'],
                            "article_code" => $product['article_code'],
                            "product_name" => $product['name'],
                            "product_type" => $product['product_type'],
                            "option_id" => $option_id,
                            "net_unit_price" => $this->sma->formatDecimal($productPrice['net_unit_price'], 4),
                            "unit_discount" => $productPrice['unit_discount'],
                            "unit_tax" => $this->sma->formatDecimal($productPrice['unit_tax'], 4),
                            "invoice_unit_price" => $this->sma->formatDecimal($invoice_unit_price, 4),
                            "invoice_net_unit_price" => $this->sma->formatDecimal($invoice_net_unit_price, 4),
                            "unit_price" => $productPrice['unit_price'],
                            "quantity" => $unit_quantity,
                            "net_price" => $net_price,
                            "invoice_total_net_unit_price" => $invoice_total_net_unit_price,
                            "warehouse_id" => $warehouse_id,
                            "item_tax" => $this->sma->formatDecimal($item_tax, 4),
                            "tax_method" => $tax_method,
                            "tax_rate_id" => $product['tax_id'],
                            "tax" => $productPrice['tax_rate'],
                            "discount" => $productPrice['discount_rate'],
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
                            "item_weight" => $unit_quantity,
                        );

                        $total_items++;

                        $sale_cgst += $item_cgst;
                        $sale_sgst += $item_sgst;
                        $sale_igst += $item_igst;

                        // $total += ((float) $productPrice['net_unit_price'] * (float) $unit_quantity);
                        $total += ($productPrice['net_unit_price'] * $unit_quantity);
                        $total_item_tax += (float) $item_tax;
                        $total_item_discount += (float) $item_discount;
                    } //end foreach.

                    $reference = $this->site->getReferenceNumber('eshop');
                    $date = date('Y-m-d H:i:s');
                    $customer_id = $customer['id'];
                    $customer_name = $customer['name'];
                    // $note = $this->db->escape($this->input->post('order_comments'));
                    $shipping = (($this->input->post('shipping_charges')) ? $this->input->post('shipping_charges') : 0);
                    $shipping = is_numeric($shipping) ? (float) $shipping : 0.0;
                    $order_discount_id = NULL;
                    $order_tax_id = NULL;
                    $order_tax = 0;
                    $discount = is_numeric($discount) ? (float) $discount : 0.0;

                    $total_discount = $total_item_discount + $discount;
                    $total_tax = $total_item_tax + $order_tax;
                    // $grand_total = (($total + $total_tax + $shipping) - $order_discount);
                    $grand_total = $this->input->post('cart_subtotal_amt');
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

                    $order_id = $this->webshop_api_model->add_order($order, $products);
                    $this->session->set_userdata('order_id', $order_id);


                    if ($order_id) {
                        //    $this->load->model('Whatsapp_model');
                        $customer = $this->getShippingAddress($order['billing_address_id']);
                        $country_code = $this->getcountryCode($customer->country);
                        $phone = $country_code . $customer->phone;
                        $orderflag = "true";
                        $data = $this->call_whatsapp_cheerio($phone, $order_id, $orderflag);
                        //$this->send_invoice_by_email(['order_id'=>$order_id, 'customer'=>$customer]);
                        $this->session->unset_userdata('cart');
                        if ($this->input->post('payment_method') == 'cod') {
                            unset($_SESSION['cart']);
                            redirect("webshop/order_success?order=$order_id&customer=$customer_id");
                        } else if ($this->input->post('payment_method') == 'razorpay' || $this->input->post('payment_method') == 'ccavenue') {

                            redirect("webshop/payments?order=$order_id&customer=$customer_id");
                            //  $this->razorpay_init([
                            //     'order_id' => $order_id,
                            //     'customer_id' => $order['customer_id'],
                            //     'date' => $order['date'],
                            //     'reference_no' => $order['reference_no'],
                            //     'language' => "ENG",
                            //     'amount' => $order['grand_total'],
                            //     'currency' => "INR", // Assuming 'currency' is a valid key in $order
                            //     'billing_name' => $this->input->post('billing_first_name') . ' ' . $this->input->post('billing_last_name'),
                            //     'billing_company' => $this->input->post('billing_company'),
                            //     'billing_address' => $this->input->post('billing_address_1'),
                            //     'billing_city' => $this->input->post('billing_city'),
                            //     'billing_state' => $billing_stateData[0],
                            //     'billing_country' => $this->input->post('billing_country'),
                            //     'billing_zip' => $this->input->post('billing_postcode'),
                            //     'billing_tel' => $this->input->post('billing_phone'),
                            //     'billing_email' => $this->input->post('billing_email'),
                            //     'payment_gatway' => $this->input->post('payment_method'),
                            //     'submit' => "payment_gatway"
                            // ]);
                            // exit;
                            redirect("webshop/order_success?order=$order_id&customer=$customer_id");
                        } else {
                            redirect("webshop?order_status=success");
                            return;
                        }
                    }
                }
            } else {
                $this->session->set_flashdata('error_message', 'Request Timeout');
                $_SESSION['postdata'] = $this->input->post();
                redirect('webshop/checkout/timeout');
            }
        } else {
            $this->session->set_flashdata('error_message', 'Invalid Request');
            redirect('webshop/cart/invalid');
        }
    }

    public function send_invoice_by_email(array $para)
    {

        $order_id = $para['order_id'];
        $customer = $para['customer'];

        $to_email = $customer['email'];
        $to_name = $customer['name'];

        /* Email Code write here */
    }

    public function send_welcome_mail($customer)
    {

        $to_email = $customer['email'];
        $to_name = $customer['name'];

        /* Email Code write here */
    }

    public function payment_ccavResponseHandler()
    {

        $this->load->helper('crypto_helper');
        $ci = get_instance();
        $ci->config->load('payment_gateways', TRUE);
        $payment_config = $ci->config->item('payment_gateways');
        $ccavenue = $payment_config['ccavenue'];
        $workingKey = isset($ccavenue['API_KEY']) ? $ccavenue['API_KEY'] : '';
        $encResponse = isset($_POST["encResp"]) ? $_POST["encResp"] : '';
        if ($workingKey === '' || $encResponse === '') {
            $this->session->set_flashdata('error_message', 'Payment response is invalid.');
            $this->load_view('payment_declined', []);
            return;
        }

        $rcvdString = decrypt($encResponse, $workingKey);
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

        // 2. If success, insert into payment table
        if ($order_status === 'Success') {
            $order_id =  $this->webshop_model->CcavenuePayAfterSale($responseMap);
            $this->load_view('payment_success', $responseMap);
        } else {
            $order = isset($responseMap['order_id']) ? $responseMap['order_id'] : null;
            if ($order) {
                $this->db->where('id', $order);
                $this->db->update('sma_orders', ['payment_status' => 'Failed']);
            }
            $response_data = $responseMap;
            $response_data['error_message'] = isset($responseMap['status_message']) ? $responseMap['status_message'] : 'Payment declined or failed.';
            $this->load_view('payment_declined', $response_data);
        }
    }

    public function payment_ccavRequestHandler($postData = null)
    {
        // Load necessary helper
        $this->load->helper('crypto_helper');
        $ci = get_instance();
        $ci->config->load('payment_gateways', TRUE);
        $payment_config = $ci->config->item('payment_gateways');
        $ccavenue = isset($payment_config['ccavenue']) ? $payment_config['ccavenue'] : [];

        $getField = function ($key) use ($postData) {
            if (is_array($postData) && array_key_exists($key, $postData)) {
                return $postData[$key];
            }
            return $this->input->post($key);
        };

        // Always trust server config first; POST values only as fallback.
        $working_key = isset($ccavenue['API_KEY']) && $ccavenue['API_KEY'] !== '' ? (string) $ccavenue['API_KEY'] : (string) $this->input->post('API_KEY');
        $access_code = isset($ccavenue['ACCESS_CODE']) && $ccavenue['ACCESS_CODE'] !== '' ? (string) $ccavenue['ACCESS_CODE'] : (string) $this->input->post('ACCESS_CODE');
        $api_url = isset($ccavenue['API_URL']) && $ccavenue['API_URL'] !== '' ? (string) $ccavenue['API_URL'] : (string) $this->input->post('API_URL');
        $merchant_id = isset($ccavenue['MERCHANT_ID']) && $ccavenue['MERCHANT_ID'] !== '' ? (string) $ccavenue['MERCHANT_ID'] : (string) $this->input->post('MERCHANT_ID');

        if ($working_key === '' || $access_code === '' || $merchant_id === '') {
            $this->session->set_flashdata('error_message', 'CCAvenue configuration is missing.');
            $this->load_view('payment_declined', []);
            return;
        }

        // Collect form data
        $postData = array(
            "integration_type" => 'iframe_normal',
            "reference_no" => $getField('reference_no'),
            "customer_id" => $getField('customer_id'),
            "date" => $getField('date'),
            "language" => $getField('language'),
            "amount" => $getField('amount'),
            "currency" => $getField('currency'),
            "billing_name" => htmlspecialchars((string) $getField('billing_name')),
            "billing_company" => htmlspecialchars((string) $getField('billing_company')),
            "billing_address" => htmlspecialchars((string) $getField('billing_address')),
            "billing_city" => htmlspecialchars((string) $getField('billing_city')),
            "billing_state" => htmlspecialchars((string) $getField('billing_state')),
            "billing_country" => htmlspecialchars((string) $getField('billing_country')),
            "billing_zip" => htmlspecialchars((string) $getField('billing_zip')),
            "billing_tel" => htmlspecialchars((string) $getField('billing_tel')),
            "billing_email" => htmlspecialchars((string) $getField('billing_email')),
            "redirect_url" => htmlspecialchars((string) $getField('redirect_url')),
            "cancel_url" => htmlspecialchars((string) $getField('cancel_url')),
            "merchant_id" => $merchant_id,
            "order_id" => $getField('order_id')
        );

        // Build the merchant data string
        $merchant_data = '';
        foreach ($postData as $key => $value) {
            $merchant_data .= $key . '=' . urlencode($value) . '&';
        }

        // Encrypt the merchant data
        $encrypted_data = encrypt($merchant_data, $working_key, $merchant_id);
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
        $data = '';
        $order_id = $this->session->userdata('order_id');
        if ($order_id) {
            $this->db->where('id', $order_id);
            $this->db->update('sma_orders', ['payment_status' => 'Failed']);
        }
        $this->load_view("payment_declined", $data);
    }

    public function payments()
    {

        $order_id = $this->input->get('order');

        $customer_id = $this->input->get('customer');

        if (!empty($this->input->post('submit'))) {

            if (!empty($this->input->post('payment_gatway'))) {

                switch ($this->input->post('payment_gatway')) {

                    case "ccavenue":

                        $this->payment_ccavRequestHandler();

                        break;

                    case "instamojo":

                        $pay_result = $this->webshop_model->instamojoEshop($_POST);


                        if (isset($pay_result['longurl']) && !empty($pay_result['longurl'])):
                            redirect($pay_result['longurl'], 'refresh');

                        else:
                            $this->webshop_model->deleteSale($order_id);
                            $result['msg'] = $this->instamojo_error($pay_result['error']);
                            return $result;
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






                    default:
                        break;
                } //end switch
            }
        } else {

            $ci = get_instance();
            $ci->config->load('payment_gateways', true);

            $this->data['payment_config'] = $ci->config->item('payment_gateways');

            /* echo "@@@@@@@@@@@";
              print_r($this->data['payment_config']); */

            $this->data['customer_id'] = $customer_id;

            $this->data['order'] = $order = $this->webshop_model->get_order_by_id($order_id);

            $this->data['payments_gatway'] = $this->webshop_model->get_payment_gatways();

            $this->data['billing_address'] = $this->webshop_model->get_address_by_id($order['billing_address_id']);

            // Directly open gateway when CCAvenue is enabled; skip rendering payments page.
            if (!empty($this->data['payments_gatway']->ccavenue) && is_array($order) && is_array($this->data['billing_address'])) {
                $ccData = [
                    'reference_no' => isset($order['reference_no']) ? $order['reference_no'] : '',
                    'customer_id' => $customer_id,
                    'date' => isset($order['date']) ? $order['date'] : '',
                    'language' => 'EN',
                    'amount' => $this->sma->formatDecimal(isset($order['grand_total']) ? $order['grand_total'] : 0),
                    'currency' => 'AED',
                    'billing_name' => isset($this->data['billing_address']['address_name']) ? $this->data['billing_address']['address_name'] : '',
                    'billing_company' => isset($this->data['billing_address']['company_name']) ? $this->data['billing_address']['company_name'] : '',
                    'billing_address' => trim((isset($this->data['billing_address']['line1']) ? $this->data['billing_address']['line1'] : '') . ' ' . (isset($this->data['billing_address']['line2']) ? $this->data['billing_address']['line2'] : '')),
                    'billing_city' => isset($this->data['billing_address']['city']) ? $this->data['billing_address']['city'] : '',
                    'billing_state' => isset($this->data['billing_address']['state']) ? $this->data['billing_address']['state'] : '',
                    'billing_country' => isset($this->data['billing_address']['country']) ? $this->data['billing_address']['country'] : '',
                    'billing_zip' => isset($this->data['billing_address']['postal_code']) ? $this->data['billing_address']['postal_code'] : '',
                    'billing_tel' => isset($this->data['billing_address']['phone']) ? $this->data['billing_address']['phone'] : '',
                    'billing_email' => isset($this->data['billing_address']['email_id']) ? $this->data['billing_address']['email_id'] : '',
                    'redirect_url' => base_url("webshop/payment_ccavResponseHandler"),
                    'cancel_url' => base_url("webshop/payment_cancel"),
                    'order_id' => isset($order['id']) ? $order['id'] : '',
                ];
                $this->payment_ccavRequestHandler($ccData);
                return;
            }

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

    public function order_success()
    {

        $order_id = $this->input->get('order');

        $this->data['order'] = $this->webshop_model->get_order_by_id($order_id);
        $this->data['items'] = $this->webshop_model->get_order_items_by_order_id($order_id);
        if ($this->webshop_settings->webshop_theme == 'restaurant') {
            redirect("webshop?order_status=success", $this->data);
            return;
        }

        $this->load_view("order_success", $this->data);
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

    public function add_to_wishlist($postData)
    {

        // if ($this->webshop_settings->webshop_theme == 'restaurant') {
        //     $this->load_view("webshop_restaurant_t1/index", $this->data);
        //     // xxxxxxxxxxxxxxxxxxxxxxxxxx
        //     $product_id = $postData['product_id'];
        //     $variant_id = !empty($postData['variant_id']) ? $postData['variant_id'] : 0;
        //     $variant_price = !empty($postData['variant_price']) ? $postData['variant_price'] : 0;
        //     $unit_quantity = !empty($postData['variant_unit_quantity']) ? $postData['variant_unit_quantity'] : 1;
        //     $product_unit_price = $postData['product_price'];
        //     $quantity = $postData['quantity'];
        //     $tax_rate = $postData['tax_rate'];
        //     $tax_method = $postData['tax_method'];
        //     $price = $postData['price'];
        //     $promotion_price = $postData['promotion_price'];

        //     $item_key = ((int) $variant_id) ? $product_id . "_" . $variant_id : $product_id;

        //     if (!isset($_SESSION['wishlist_items'])) {
        //         $_SESSION['wishlist_items'] = [];
        //     }

        //     if (isset($_SESSION['wishlist_items'][$item_key])) {

        //         // $_SESSION['cart'][$item_key]['quantity'] += $quantity;

        //         $data['wishlist_count'] = count($_SESSION['wishlist_items']);
        //         $data['status'] = 'SUCCESS';
        //     } else {

        //         $_SESSION['wishlist_items'][$item_key] = [
        //             "product_id" => $product_id,
        //             "variant_id" => $variant_id,
        //             "variant_price" => $variant_price,
        //             "unit_quantity" => $unit_quantity,
        //             "product_price" => $product_unit_price,
        //             "quantity" => $quantity,
        //             "tax_rate" => $tax_rate,
        //             "tax_method" => $tax_method,
        //             "price" => $price,
        //             "promotion_price" => $promotion_price,
        //         ];

        //         $data['wishlist_count'] = count($_SESSION['wishlist_items']);
        //         $data['status'] = 'SUCCESS';
        //     }//end else
        //     // $subtotal = 0;
        //     // foreach ($_SESSION['cart'] as $key => $item) {
        //     //     $subtotal += (float) $item['product_price'] * (float) $item['quantity'];
        //     // }

        //     // $data['cart_total'] = number_format($subtotal, 2);


        //     echo json_encode($data);

        //     // xxxxxxxxxxxxxxxxxxxxxxxx

        // } else{
        $data['product_id'] = $postData['product_id'];
        $data['option_id'] = !empty($postData['variant_id']) ? $postData['variant_id'] : 0;
        if (isset($this->session->webshop) && $this->session->webshop->is_login && $this->session->webshop->user_id) {
            $data['user_id'] = $this->session->webshop->user_id;

            $wishlist = $this->webshop_model->add_to_wishlist($data);

            $result['count'] = count($wishlist);
            $result['status'] = 'SUCCESS';
            $result['items'] = $wishlist;

            echo json_encode($result);
        } else {
            $result['status'] = 'FAIL';
            $result['error'] = 'User session invalide';
            echo json_encode($result);
        }
        // }    
    }

    public function remove_from_wishlist($postData)
    {

        $data['product_id'] = $postData['product_id'];
        $data['option_id'] = ($postData['variant_id']) ? $postData['variant_id'] : 0;

        if (isset($this->session->webshop) && $this->session->webshop->is_login && $this->session->webshop->user_id) {
            $data['user_id'] = $this->session->webshop->user_id;

            $wishlist = $this->webshop_model->remove_from_wishlist($data);

            $result['count'] = count($wishlist);
            $result['status'] = 'SUCCESS';
            $result['items'] = $wishlist;

            echo json_encode($result);
        } else {
            $result['status'] = 'FAIL';
            $result['error'] = 'User session invalide';
            echo json_encode($result);
        }
    }

    public function add_to_cart($postData)
    {

        $product_id = $postData['product_id'];
        $variant_id = !empty($postData['variant_id']) ? $postData['variant_id'] : 0;
        $variant_price = !empty($postData['variant_price']) ? $postData['variant_price'] : 0;
        $unit_quantity = !empty($postData['variant_unit_quantity']) ? $postData['variant_unit_quantity'] : 1;
        $product_unit_price = $postData['product_price'];
        $quantity = $postData['quantity'];
        $tax_rate = $postData['tax_rate'];
        $tax_method = $postData['tax_method'];
        $price = $postData['price'];
        $promotion_price = $postData['promotion_price'];
        // $product_desc = $postData['product_desc'] ? $postData['product_desc'] : "";


        $item_key = ((int) $variant_id) ? $product_id . "_" . $variant_id : $product_id;

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$item_key])) {

            $_SESSION['cart'][$item_key]['quantity'] += $quantity;

            $data['cart_items'] = count($_SESSION['cart']);
            $data['status'] = 'SUCCESS';
        } else {

            $_SESSION['cart'][$item_key] = [
                "product_id" => $product_id,
                "variant_id" => $variant_id,
                "variant_price" => $variant_price,
                "unit_quantity" => $unit_quantity,
                "product_price" => $product_unit_price,
                "quantity" => $quantity,
                "tax_rate" => $tax_rate,
                "tax_method" => $tax_method,
                "price" => $price,
                "promotion_price" => $promotion_price,
                // "product_desc" => $product_desc,
            ];

            $data['cart_count'] = count($_SESSION['cart']);
            $data['status'] = 'SUCCESS';
        } //end else
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $key => $item) {
            $subtotal += (float) $item['product_price'] * (float) $item['quantity'];
        }

        $data['cart_total'] = number_format($subtotal, 2);


        echo json_encode($data);
    }

    public function update_cart($postData)
    {

        $item_key = $postData['itemKey'];
        $itemQty = $postData['itemQty'];
        // $itemUnitPrice  = $postData['itemUnitPrice']; 

        if (!isset($_SESSION['cart'])) {
            return FALSE;
        }

        if (isset($_SESSION['cart'][$item_key])) {

            $_SESSION['cart'][$item_key]['quantity'] = $itemQty;

            echo 'SUCCESS';
        }
    }

    public function load_header_cart_items()
    {

        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            $this->data['cart_items'] = $_SESSION['cart'];
            $this->data['cart_data'] = $this->webshop_model->get_cart_data();

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
                    $this->load_view("section_features_list", $this->data);
                }
                break;

            case 'section_top_categories':

                $this->load_view("section_top_categories", $this->data);
                break;


            default:
                break;
        } //end switch
    }

    public function storeInfo()
    {

        $this->load->model('settings_model');

        $res = $this->eshop_model->getPosSettings();

        $config = $this->ci->config;
        $merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : null;
        $res->merchant_phone = $merchant_phone;

        if (is_object($res)):
            $data = array();
            foreach ($res as $key => $value) {
                $data[$key] = $value;
            }
            return $data;
        endif;

        return false;

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
        if ($this->input->post('submit_login')) {

            $username = $this->input->post('webshop_username');
            $passwdHash = md5($this->input->post('webshop_password'));
            $return_page = $this->input->post('return_page');
            $data['phone'] = $this->input->post('phone');
            $logdata['first'] = $this->input->post('first');
            $logdata['last']  = $this->input->post('last');
            $phone = $this->input->post('phone');

            // $authData = $this->webshop_model->authenticate_user($username, $passwdHash);
            $authData = $this->webshop_model->authenticate_user_password($phone, $passwdHash);

            if (!empty($authData)) {
                $authData->user_id = $authData->id;
                $authData->is_login = TRUE;
                if (isset($_SESSION['cart'])) {
                    $this->session->cart = $_SESSION['cart'];
                    $return_page = site_url('webshop/checkout');
                } else {
                    unset($_SESSION['cart']);
                }
                $this->session->webshop = $authData;
                $name_parts = explode(' ', trim($authData->name), 2);
                $first_name = $name_parts[0];
                $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                $register_session_data = array(
                    'user_id'       => $authData->id,
                    'first'         => $first_name,
                    'last'          => $last_name,
                    'email'         => $authData->email,
                    'phone'         => $authData->phone,
                );
                $this->session->set_userdata('customer_register', $register_session_data);
                if ($theme == 'restaurant') {
                    redirect("webshop?msg=login_success");
                    return;
                }
                if ($theme == 'nw') {
                    redirect("webshop?msg=login_success");
                    return;
                }
                redirect($return_page .  "?msg=auth_success");
            } else {
                // redirect("webshop/login?msg=error");
                $this->data['phone_error'] = "Invalid phone number or password.";
                $this->data['validated'] = false;
                $this->data['return_page'] = $return_page;

                if ($theme == 'restaurant') {
                    $this->load_view("webshop_restaurant_t1/sign_in.php", $this->data);
                } else if ($theme == "nw") {
                    $this->load_view("nw_theme/login.php", $this->data);
                } else if ($theme == "gulfpharmacy") {
                    $this->load_view("gulfpharmacy_theme/login.php", $this->data);
                } else {
                    $this->load_view("login_registration", $this->data);
                }
            }
        } else {
            if ($this->session->webshop->is_login && $this->session->webshop->user_id) {
                redirect("webshop/index");
            }
            // $theme = $this->webshop_settings->webshop_theme;
            $this->data['return_page'] = str_replace(base_url(), '', $_SERVER['HTTP_REFERER']);
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            $setting_map = [];
            foreach ($raw_settings as $row) {
                $setting_map[$row->fields] = $row->value;
            }
            if ($theme == 'restaurant') {
                $this->load_view("webshop_restaurant_t1/sign_in.php", $this->data);
            } else if ($theme == "nw") {
                $this->load_view("nw_theme/login.php", $this->data);
            } else if ($theme == "gulfpharmacy") {
                $this->load_view("gulfpharmacy_theme/login.php", $this->data);
            } else {
                $this->load_view("login_registration", $this->data);
            }
        }
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
        $theme = $this->webshop_settings->webshop_theme;

        if ($this->input->post('submit_register')) {

            $data['group_id'] = 3;
            $data['group_name'] = 'customer';
            $data['customer_group_id'] = 1;
            $data['customer_group_name'] = 'General';
            // $data['name'] = $this->input->post('name');
            $data['email'] = $this->input->post('email');
            $data['phone'] = $this->input->post('phone');
            $data['password'] = md5($this->input->post('passwd'));
            $logdata['first'] = $this->input->post('first');
            $logdata['last']  = $this->input->post('last');
            $data['name'] = $this->input->post('first') . ' ' . $this->input->post('last');
            $country_code_raw = $_POST['country_code'];
            if ($country_code_raw) {
                $country_parts = explode('~', $country_code_raw);
                $data['country'] = $country_parts[1];
            }
            /*
             * 
             * Write Validation Code & Check Customer email/Phone not exists
             *              
             */
            $phone = $this->input->post('phone');
            //    if (!empty($phone) && $this->webshop_model->authenticate_user_mobile($phone)) {
            //         echo "<script>alert('An account already exists for this mobile number.'); window.history.back();</script>";
            //         return;
            //     }
            if (!empty($phone) && $this->webshop_model->authenticate_user_mobile($phone)) {
                $this->session->set_flashdata('toast_error', 'An account already exists for this mobile number.');
                redirect(current_url());
                return;
            }
            if (empty($phone)) {
                $this->session->unset_userdata('phone_error');
            }

            if ($this->webshop_model->add_customer($data)) {

                if ($this->send_registration_email()) {

                    $username = $this->input->post('email');
                    $passwdHash = md5($this->input->post('passwd'));
                    $return_page = 'webshop';
                    $authData = $this->webshop_model->authenticate_user($username, $passwdHash);

                    if (!empty($authData)) {
                        $authData->user_id = $authData->id;
                        $authData->is_login = TRUE;
                        if (isset($_SESSION['cart'])) {
                            $this->session->cart = $_SESSION['cart'];
                            $return_page = site_url('webshop/checkout');
                        } else {
                            unset($_SESSION['cart']);
                        }
                        $this->session->webshop = $authData;
                        $register_session_data = array(
                            'user_id'       => $authData->id,
                            'first'          => $logdata['first'],
                            'last'          => $logdata['last'],
                            'email'         => $data['email'],
                            'phone'         => $data['phone'],
                            'group_id'      => $data['group_id'],
                            'group_name'    => $data['group_name'],
                            'customer_group_id'   => $data['customer_group_id'],
                            'customer_group_name' => $data['customer_group_name'],
                        );
                        $this->session->set_userdata('customer_register', $register_session_data);
                        if ($theme == "nw") {
                            redirect('webshop?msg=auth_success');
                        }
                        redirect($return_page . "?msg=auth_success");
                    } else {
                        if ($theme == "nw") {
                            redirect('webshop?msg=error');
                        }
                        redirect("webshop/login?msg=error");
                    }
                }
            }
        } else {
            if ($this->session->webshop->is_login && $this->session->webshop->user_id) {
                redirect("webshop/index");
            }
            // Append your theme-based logic here
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            $setting_map = [];
            foreach ($raw_settings as $row) {
                $setting_map[$row->fields] = $row->value;
            }
            if ($this->webshop_settings->webshop_theme == 'restaurant') {
                $this->data['country'] = $this->webshop_model->getCountry();
                $this->load_view("webshop_restaurant_t1/sign_up.php", $this->data);
            } else if ($theme == "nw") {
                $this->data['country'] = $this->webshop_model->getCountry();
                $this->load_view("nw_theme/register.php", $this->data);
            } else if ($theme == "gulfpharmacy") {
                $this->data['country'] = $this->webshop_model->getCountry();
                $this->load_view("gulfpharmacy_theme/register.php", $this->data);
            } else {
                $this->load_view("login_registration", $this->data);
            }
        }
    }
    public function send_registration_email()
    {

        /*
         * Write Send Email Code Here
         */
        return TRUE;
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

    //     echo json_encode($data);
    // }
    public function apply_coupon($postData)
    {

        $coupon_code = $postData['coupon_code'];
        $cart_amount = $postData['cart_amount'];

        $couponData = $this->webshop_model->get_coupon_data($coupon_code);

        $data['status'] = 'failed';
        $data['msg'] = "Invalid coupon code.";

        if ($couponData && $couponData->coupon_code === $coupon_code) {
            //Block coupon if not active (based on `is_active` field)
            if ((int)$couponData->is_active !== 1) {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " is not active.";
                echo json_encode($data);
                return;
            }
            if (strtotime($couponData->expiry_date) < strtotime(date('Y-m-d')) && $couponData->status != 'expired') {
                $this->db->where('id', $couponData->id)->update('discount_coupons', ['status' => 'expired']);
                $couponData->status = 'expired';
            }
            if (
                $couponData->status == 'used' &&
                $couponData->max_coupons > 0 &&
                $couponData->used_coupons >= $couponData->max_coupons
            ) {
                $this->db->where('id', $couponData->id)->update('discount_coupons', ['status' => 'expired']);
                $couponData->status = 'expired';
            }

            $user_id = isset($this->session->webshop->user_id) ? $this->session->webshop->user_id : '';
            $user_group_id = isset($this->session->webshop->customer_group_id) ? $this->session->webshop->customer_group_id : '';
            $is_guest = empty($user_id); // guest check

            //  Block guest from applying customer-specific or group-specific coupon
            if ($is_guest && (!empty($couponData->customer_id) || !empty($couponData->customer_group_id))) {
                $data['status'] = 'failed';
                $data['msg'] = "This coupon is not valid for guest users.";
            } elseif (!empty($couponData->minimum_cart_amount) && $couponData->minimum_cart_amount > $cart_amount) {
                $data['status'] = 'failed';
                $data['msg'] = "Cart amount is less than required for this coupon.";
            } elseif ((strtotime($couponData->expiry_date) < strtotime(date('Y-m-d')) || $couponData->status == 'expired')) {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " has expired.";
                echo json_encode($data);
                return;
            } elseif ($couponData->status != 'active') {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " is not active.";
            } elseif (
                !empty($couponData->max_coupons) &&
                $couponData->max_coupons > 0 &&
                $couponData->used_coupons >= $couponData->max_coupons &&
                $user_id != ''
            ) {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " has reached its usage limit.";
                echo json_encode($data);
                return;
            } elseif (
                !empty($couponData->customer_id) &&
                $user_id !== '' &&
                (int)$couponData->customer_id !== (int)$user_id
            ) {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " belongs to another customer.";
            } elseif (
                !empty($couponData->customer_group_id) &&
                !empty($user_group_id) &&
                (int)$couponData->customer_group_id !== (int)$user_group_id &&
                !empty($user_id)
            ) {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " is assigned to another customer group.";
            } else {
                // Apply discount
                if (!empty($couponData->discount_rate)) {
                    $discount = $couponData->discount_rate;
                    $dpos = strpos($discount, '%');

                    if ($dpos !== false) {
                        $cup_ds = explode("%", $discount);
                        $coupon_discount = $this->sma->formatDecimal(((float)$cart_amount * (float)$cup_ds[0]) / 100, 4);
                    } else {
                        $coupon_discount = $this->sma->formatDecimal($discount, 4);
                    }

                    if (
                        !empty($couponData->maximum_discount_amount) &&
                        $couponData->maximum_discount_amount > 0 &&
                        $coupon_discount > $couponData->maximum_discount_amount
                    ) {
                        $coupon_discount = $couponData->maximum_discount_amount;
                    }

                    $couponData->aplied_discount_amount = $coupon_discount;

                    $data['status'] = 'success';
                    $data['msg'] = "Coupon applied successfully.";
                    $data['coupon_data'] = $couponData;
                }
            }
        }

        echo json_encode($data);
    }

    public function page($PageKey, $pageHashId)
    {

        $this->data['page_data'] = $this->webshop_model->getCustomPages($pageHashId);

        $this->load_view("page", $this->data);
    }

    public function your_account()
    {
        $theme = $this->webshop_settings->webshop_theme;

        if (! isset($this->session->webshop) || !$this->session->webshop->user_id) {
            if ($theme == 'restaurant') {
                $this->load_view("webshop_restaurant_t1/index", $this->data);
                return;
            }
            if ($theme == 'nw') {
                $this->load_view("nw_theme/index", $this->data);
                return;
            }
            if ($theme == 'gulfpharmacy') {
                $this->load_view("gulfpharmacy_theme/index", $this->data);
                return;
            }
        }
        $this->data['website_setting'] = $this->webshop_model->get_website_setting();
        $setting_map = [];
        foreach ($raw_settings as $row) {
            $setting_map[$row->fields] = $row->value;
        }
        $this->data['state_list'] = $this->webshop_model->get_state();
        $this->data['country'] = $this->webshop_model->getCountry();
        $this->data['orders'] = $this->webshop_model->get_customer_orders($customer_id);
        if ($theme == 'restaurant') {
            // $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            // $setting_map = [];
            // foreach ($raw_settings as $row) {
            //     $setting_map[$row->fields] = $row->value;
            // }
            // $this->data['state_list'] = $this->webshop_model->get_state();
            // $this->data['country'] = $this->webshop_model->getCountry();
            // $this->data['orders'] = $this->webshop_model->get_customer_orders($customer_id);
            $this->load_view("webshop_restaurant_t1/my_account", $this->data);
            return;
        }
        if ($theme == 'nw') {
            $this->load_view("nw_theme/my_account", $this->data);
            return;
        }
        if ($theme == 'gulfpharmacy') {
            $this->load_view("gulfpharmacy_theme/my_account", $this->data);
            return;
        }
        $this->load_view("your_account", $this->data);
    }

    public function your_address()
    {

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {
            $theme = $this->webshop_settings->webshop_theme;

            $this->data['state_list'] = $this->webshop_model->get_state();

            $customer_id = (int) $this->session->webshop->user_id;
            $this->data['customer_id'] = $customer_id;
            $this->data['addresses'] = $this->webshop_model->get_customer_address($customer_id);
            if ($theme == 'restaurant' || $theme == "nw") {
                echo json_encode($this->data);
                return;
            }
            $this->load_view("your_address", $this->data);
        } else {
            redirect("webshop/login");
        }
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
                    redirect("webshop/your_address");
                }
            } else if ($addressAction == 'edit') {
                $addressId = $this->input->post('addressModalActionId');
                $this->webshop_model->update_customer_address($data, $addressId);
                $this->session->set_flashdata('message', "Address Updated Successfully.");
                redirect("webshop/your_address");
            }
        }
    }

    public function manage_address_webshop()
    {

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        $userId = $this->session->webshop->user_id;
        $address_id = $input['address_id'];
        $address_name = $input['address_name'];
        $company_name = $input['company_name'];
        $line1 = $input['line1'];
        $line2 = $input['line2'];
        $city = $input['city'];
        $postal_code = $input['postal_code'];
        $state = $input['state'];
        $country = $input['country'];
        $phone = $input['phone'];
        $email_id = $input['email_id'];
        $is_default = $input['is_default'];
        $addressAction = $input['addressAction'];
        $address_id = $input['address_id'];

        $data = [
            'company_id' => $userId,
            'address_name' => $address_name,
            'company_name' => $company_name,
            'line1' => $line1,
            'line2' => $line2,
            'city' => $city,
            'postal_code' => $postal_code,
            'state' => $state,
            'country' => $country,
            'phone' => $phone,
            'email_id' => $email_id,
        ];

        if ($addressAction == "edit") {
            $this->webshop_model->update_customer_address($data, $address_id);
            if ($is_default == 1) {
                $this->webshop_model->set_address_default($userId, $address_id);
            }
            echo json_encode(['statusMessage' => "success"]);
            return;
        } elseif ($addressAction == "add") {
            if ($address_id = $this->webshop_model->set_customer_address($data)) {
                if ($is_default == 1) {
                    $this->webshop_model->set_address_default($userId, $address_id);
                }
            }
            echo json_encode(['statusMessage' => "success"]);
            return;
        }
    }

    public function address_set_default($customer_id, $address_id)
    {

        if ($this->webshop_model->set_address_default($customer_id, $address_id)) {

            $this->session->set_flashdata('message', "Default Address Set Successfully.");
            redirect("webshop/your_address");
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

        if ($this->webshop_model->delete_address($address_id)) {

            $this->session->set_flashdata('message', "Address Deleted Successfully.");
            redirect("webshop/your_address");
        }
    }

    public function your_orders()
    {

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {
            $theme = $this->webshop_settings->webshop_theme;

            $this->webshop_model->set_recent_viewed_product($productDetails['item']['id']);

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

            $customer_id = (int) $this->session->webshop->user_id;
            $this->data['customer_id'] = $customer_id;

            $this->data['orders'] = $this->webshop_model->get_customer_orders($customer_id);

            if ($theme == 'restaurant' || $theme == "nw") {
                $this->data['addresses'] = $this->webshop_model->get_customer_address($customer_id);
                echo json_encode($this->data);
                return;
            }
            $this->load_view("your_orders", $this->data);
        } else {
            // $theme = $this->webshop_settings->webshop_theme;
            // if ($theme == 'restaurant') {
            //     redirect("webshop/login", $this->data);
            // }
            // if ($theme == 'nw') {
            //     redirect("nw/login", $this->data);
            // }
            redirect("webshop/login");
        }
    }

    public function order_details($order_id)
    {

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {

            if (empty($order_id) || $order_id == '') {
                redirect("webshop/your_orders");
            }

            $this->webshop_model->set_recent_viewed_product($productDetails['item']['id']);

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

            $customer_id = (int) $this->session->webshop->user_id;
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

        if (isset($_POST['upload_image'])) {

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

            $fileconfig['file_name'] = md5($this->session->webshop->user_id);

            $this->load->library('upload', $fileconfig);
            $this->upload->overwrite = true;
            if (!$this->upload->do_upload('profile_image')) {

                $this->session->set_flashdata('error', $this->upload->display_errors());
                redirect("webshop/your_profile");
            } else {

                $upload_data = $this->upload->data(); //Returns array of containing all of the data related to the file you uploaded.
                $file_name = $upload_data['file_name'];

                if ($this->webshop_model->set_profile_photo($file_name, $this->session->webshop->user_id)) {

                    $this->session->set_flashdata('message', "Profile Image Uploaded Successfully.");
                    redirect("webshop/your_profile");
                }
            }
        } else if (isset($_POST['submitProfle'])) {

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
                extract($_POST);

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

                if ($this->webshop_model->update_profile($data, $this->session->webshop->user_id)) {

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

    public function profile_update_webshop()
    {
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        $fName = $input['fName'] ? $input['fName'] : '';
        $dob   = $input['dob'] ? $input['dob'] : '';
        $email = $input['email'] ? $input['email'] : '';
        $data = [
            'name' => $fName,
            'email' => $email,
            'dob' => $dob,
        ];
        $userId = $this->session->webshop->user_id;
        if ($userId) {
            $update = $this->webshop_model->update_profile($data, $userId);
            if ($update) {
                // $fullName = explode(' ', $fName);
                $this->session->webshop->name = $fName;
                $this->session->webshop->email = $email;
                $this->session->webshop->name = $fName;
                echo json_encode(['statusMessage' => "success"]);
                return;
            }
            echo json_encode(["statusMessage" => "failed"]);
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

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {
            $theme = $this->webshop_settings->webshop_theme;

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

            $this->data['customer'] = $customer = $this->webshop_model->get_customer(['id' => $this->session->webshop->user_id]);
            // var_dump($customer);
            // exit;

            $this->data['images'] = base_url("assets/images/customers/");
            $this->data['action'] = $action;
            if ($theme == 'restaurant' || $theme == "nw") {
                echo json_encode($this->data);
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

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {

            if (isset($_POST['changePassword'])) {

                $this->load->library('form_validation');
                $this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');

                $this->form_validation->set_rules('current_password', 'current password', 'required');
                $this->form_validation->set_rules('newpassword', 'newpassword', 'trim|required|min_length[8]|max_length[22]|differs[current_password]');
                $this->form_validation->set_rules('confirm', 'confirm', 'trim|required|matches[newpassword]');

                if ($this->form_validation->run() == FALSE) {
                    $this->session->set_flashdata('error', 'Validation Errors!');
                    $this->load_view("change_password", $this->data);
                } else {
                    $current_password = $this->input->post('current_password');
                    $newpassword      = md5($this->input->post('newpassword'));

                    if ($this->webshop_model->is_valid_current_password($this->session->webshop->user_id, $current_password) === false) {
                        $this->session->set_flashdata('error', 'Invalid Current Password');
                        redirect("webshop/change_password/error");
                    } else {

                        if ($this->webshop_model->update_new_password($this->session->webshop->user_id, $newpassword)) {

                            $this->session->set_flashdata('message', 'Password has been changed successfully.');
                            redirect("webshop/change_password/success");
                        } else {
                            $this->session->set_flashdata('error', 'Sql error!');
                            redirect("webshop/change_password/error");
                        }
                    }
                }
            } else {

                $this->load_view("change_password", $this->data);
            }
        } else {
            redirect("webshop/login");
        }
    }

    public function forgot_password()
    {

        $theme = $this->webshop_settings->webshop_theme;
        if ($this->session->webshop->is_login && $this->session->webshop->user_id) {
            redirect("webshop/index");
        }
        // print_r($_POST['reset_password']);
        // exit;

        if (isset($_POST['reset_password'])) {
            $mobile = $this->input->post('mobile');
            $confirm_password = md5($this->input->post('confirm_password'));
            if ($this->webshop_model->update_company_password($mobile, $confirm_password)) {
                $this->session->set_flashdata('message', 'Password has been changed successfully.');
            } else {
                $this->session->set_flashdata('error', 'Failed to update password.');
            }
            redirect("webshop");
        } else {

            if ($theme == "nw") {
                $this->load_view("nw_theme/forgot_password", $this->data);
                return;
            }
            if ($theme == "gulfpharmacy") {
                $this->load_view("gulfpharmacy_theme/forgot_password", $this->data);
                return;
            }

            $this->load_view("forgot_password", $this->data);
        }
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
                    $res = $this->webshop_model->instomojoEshopAfterSale($paymentDetail, $order_id);
                    if ($res):
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

        $order_id = $paytmpayment['order_id'];


        if ((int) $order_id > 0):
            $_req = $this->webshop_model->getPaytmTransaction(array('order_id' => $order_id));
            if ($_req->id):
                $this->session->set_flashdata('error', "Paytm" . lang('payment_process_already_initiated'));
                redirect('webshop');
            endif;
            $order = $this->site->getSaleByIDEshop($order_id);

            if ($order->id == $order_id):


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
                $paramList["ORDER_ID"] = $order->id;
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
            endif;
        endif;
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

                $getorderdetails = $this->site->getSaleByIDEshop($sid);
                $ref_No = $getorderdetails->reference_no;

                $res = $this->webshop_model->PaytmAfterSale($responseParamList, $sid);
                if ($res):
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

        $sale_id = $data['order_id'];
        //        $this->input->get('sid');
        if ((int) $sale_id > 0) {

            $sale = $this->site->getSaleByIDEshop($sale_id);
            if ($sale->id == $sale_id) {

                $customer = $this->site->getCompanyByID($sale->customer_id);


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
                $_SESSION['currency'] = $this->Settings->default_currency;


                $razorpayOrder = $api->order->create(array(
                    'receipt' => $sale->invoice_no,
                    'amount' => $sale->grand_total * 100,
                    'currency' => $this->Settings->default_currency,
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
                $datapass['notes'] = array(
                    'address' => $customer->address,
                    'merchant_order_id' => $sale->id
                );
                $datapass['name'] = $this->Settings->site_name;
                $datapass['description'] = '#Order No: ' . $sale->id;


                $this->data['data'] = $datapass;
                // exit;
                // $this->load->view("views/webshop/razorpay", $this->data);
                $this->load_view('razorpay', $this->data);
                // $this->load->view('default/views/webshop/webshop_restaurant_t1/razorpay', $this->data);
            } else {
                redirect('pos');
            }
        } else {
            redirect('pos');
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

            $res = $this->webshop_model->RazorPayAfterSale($attributes, $sid);

            if ($res):
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

        echo json_encode($response);
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
        echo json_encode($response);
    }
    public function about_us()
    {
        if ($this->webshop_settings->webshop_theme == 'restaurant') {
            $this->data['about_us'] = $this->webshop_model->about_usdata($page_key = 'aboutus');
            $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            $this->load_view("webshop_restaurant_t1/about_us", $this->data);
        }
        if ($this->webshop_settings->webshop_theme == 'nw') {
            $this->load_view("nw_theme/about_us", $this->data);
        }
        if ($this->webshop_settings->webshop_theme == 'gulfpharmacy') {
            $this->load_view("gulfpharmacy_theme/about_us", $this->data);
        }
    }

    public function terms_and_conditions()
    {
        $theme = $this->webshop_settings->webshop_theme;
        $this->data['terms_and_conditions'] = $this->webshop_model->terms_conditions($page_key = 'terms_conditions');
        $this->data['website_setting'] = $this->webshop_model->get_website_setting();
        if ($this->webshop_settings->webshop_theme == 'restaurant') {
            // $this->data['terms_and_conditions'] = $this->webshop_model->terms_conditions($page_key = 'terms_conditions');
            // $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            // $setting_map = [];
            // foreach ($raw_settings as $row) {
            //     $setting_map[$row->fields] = $row->value;
            // }
            $this->load_view("webshop_restaurant_t1/terms_and_conditions", $this->data);
        } else if ($theme == "nw") {
            $this->load_view("nw_theme/terms_and_conditions", $this->data);
        } else if ($theme == "gulfpharmacy") {
            $this->load_view("gulfpharmacy_theme/terms_and_conditions", $this->data);
        }
    }

    public function privacy_policy()
    {
        $theme = $this->webshop_settings->webshop_theme;
        $this->data['privacy_policy'] = $this->webshop_model->privacy_policy($page_key = 'policy');
        $this->data['website_setting'] = $this->webshop_model->get_website_setting();
        if ($this->webshop_settings->webshop_theme == 'restaurant') {
            // $this->data['privacy_policy'] = $this->webshop_model->privacy_policy($page_key = 'policy');
            // $this->data['website_setting'] = $this->webshop_model->get_website_setting();
            // $setting_map = [];
            // foreach ($raw_settings as $row) {
            //     $setting_map[$row->fields] = $row->value;
            // }
            $this->load_view("webshop_restaurant_t1/privacy_policy", $this->data);
        } else if ($theme == "nw") {
            $this->load_view("nw_theme/privacy_policy", $this->data);
        } else if ($theme == "gulfpharmacy") {
            $this->load_view("gulfpharmacy_theme/privacy_policy", $this->data);
        }
    }

    public function contact_us()
    {
        $theme = $this->webshop_settings->webshop_theme;
        $this->data['website_setting'] = $this->webshop_model->get_website_setting();
        if ($theme == 'restaurant') {
            $this->load_view("webshop_restaurant_t1/contact_us", $this->data);
        } else if ($theme == 'nw') {
            $this->load_view("nw_theme/contact_us", $this->data);
        } else if ($theme == 'gulfpharmacy') {
            $this->load_view("gulfpharmacy_theme/contact_us", $this->data);
        }
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
            $this->load_view("gulfpharmacy_theme/blogs", $this->data);
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
            $this->load_view("gulfpharmacy_theme/blog_detail", $this->data);
        }
    }

    public function track_order($order_id)
    {
        $this->data['order_id'] = $order_id;
        if ($this->webshop_settings->webshop_theme == 'restaurant') {
            $this->load_view("webshop_restaurant_t1/tracking_order", $this->data);
        } else if ($this->webshop_settings->webshop_theme == 'nw') {
            $this->load_view("nw_theme/tracking_order", $this->data);
        } else if ($this->webshop_settings->webshop_theme == 'gulfpharmacy') {
            $this->load_view("gulfpharmacy_theme/tracking_order", $this->data);
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
    public function call_whatsapp_cheerio($phone, $order_id, $orderflag)
    {
        $this->load->model('Whatsapp_model');
        $response = $this->Whatsapp_model->send_order_whatsapp_message($phone, $order_id, $orderflag);
        return $response;
    }
    public function getTrackingData()
    {
        $this->load->model('Whatsapp_model');
        $order_id = $this->input->post('order_id');
        if (!$order_id) {
            echo json_encode(['status' => 'error', 'message' => 'Missing order ID']);
            return;
        }
        $trackingData = $this->webshop_model->getFullOrderDatahashkey($order_id);
        $shipping_address_id = $trackingData['order']['billing_address_id'];
        $fulladdress = $this->Whatsapp_model->get_full_address($shipping_address_id);
        // var_dump($fulladdress);
        $trackingData['order']['deliver_to'] = $fulladdress;
        if ($trackingData) {
            echo json_encode(['status' => 'success', 'tracking' => $trackingData]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No tracking data found']);
        }
    }
    // every order status trigger below function 
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
            echo json_encode(['status' => 'success', 'tracking' => $trackingData]);
        } else {
            echo json_encode(['status' => 'success', 'tracking' => $trackingData]);
        }
    }
    public function getCartTotal()
    {
        $cart = $this->session->userdata('cart');
        foreach ($cart as $item) {
            $carttotal += $item['price'] * $item['quantity'];
            $total = $this->sma->formatMoney($carttotal);
            $charges = $this->sma->formatMoney($item['charges']);
        }
        echo json_encode(['total' => $total, 'charges' => $charges]);
    }
    public function get_order_reply()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON format.']);
            return;
        }

        $mobile = $data['mobile'];
        $status = $data['order_msg_response'];
        $order_id = $data['order_id'];

        if ($status == 'YES') {
            $this->load->model('Whatsapp_model');
            $response = $this->Whatsapp_model->send_order_whatsapp_message($mobile, $order_id, $status);
        } else if ($status == 'NO') {
            $this->load->model('Whatsapp_model');
            $response = $this->Whatsapp_model->send_order_whatsapp_message($mobile, $order_id, $status);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid status.']);
            return;
        }
    }
    /////////////////////////////////////// User login and Registration /////////////////////////////////////////////
    public function check_mobile()
    {
        $mobile = $this->input->post('mobile');
        $exists = $this->webshop_model->authenticate_user_mobile($mobile);
        if ($exists) {
            echo json_encode(['status' => 'success', 'exists' => $exists, 'mobile' => $mobile]);
        } else {
            echo json_encode(['status' => 'error', 'exists' => $exists]);
        }
    }
    public function ajax_login()
    {
        $mobile = $this->input->post('mobile');
        $password = md5($this->input->post('password'));
        $authData = $this->webshop_model->authenticate_user_password($mobile, $password);

        if (!empty($authData)) {
            $authData->user_id = $authData->id;
            $authData->is_login = TRUE;
            $this->session->webshop = $authData;

            $name_parts = explode(' ', trim($authData->name), 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            $register_session_data = array(
                'user_id' => $authData->id,
                'first' => $first_name,
                'last' => $last_name,
                'email' => $authData->email,
                'phone' => $authData->phone,
            );
            $this->session->set_userdata('customer_register', $register_session_data);

            if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                $this->session->cart = $_SESSION['cart'];
                $redirect_url = site_url('webshop/checkout');
            } else {
                unset($_SESSION['cart']);
                $redirect_url = site_url('webshop/index');
            }

            echo json_encode([
                'status' => 'success',
                'redirect' => $redirect_url,
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid login credentials',
            ]);
        }
    }
    public function clear_phone_error()
    {
        $phone = $this->input->post('phone');
        if (empty($phone)) {
            $this->session->unset_userdata('phone_error');
            echo json_encode([
                'status' => 'success',
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
            ]);
        }
    }
    public function check_existing_password()
    {
        $mobile = $this->input->post('mobile');
        $password = md5($this->input->post('password'));
        $exists = $this->webshop_model->authenticate_user_mobile($mobile, $password);
        if ($exists && $exists->password === $password) {
            echo json_encode(['exists' => true]);
        } else {
            echo json_encode(['exists' => false]);
        }
    }
    ///////////////////////////////////// Send Whatsapp OTP ////////////////////////////////////////
    public function send_whatsapp_otp()
    {
        // Accept MobileNo from POST data
        $MobileNo = $this->input->post('MobileNo', true);
        // You can also fetch the token if needed: $token = $this->input->post('token', true);
        if (!$MobileNo) {
            echo json_encode(['status' => 'error', 'message' => 'Mobile number is required']);
            return;
        }
        // Generate OTP
        $OTP = rand(100000, 999999);
        // Build URL for OTP verification endpoint that exists in receipt controller.
        $urlpass = site_url('receipt/verify_mobile?code=' . $OTP . '&phone=' . urlencode($MobileNo));

        $response = [
            'success' => true,
            'OTP' => $OTP,
            'url' => $urlpass
        ];

        echo json_encode($response);
    }

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