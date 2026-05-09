<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Render orchestration entry point (Phase 2 no-op integration).
 *
 * This class is intentionally conservative:
 * - gathers dynamic metadata
 * - returns advisory data only
 * - does not alter output path in this phase
 */
class Webshop_render_engine
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('webshop_dynamic_model');
        $this->CI->load->library('webshop_theme_engine');
        $this->CI->load->library('webshop_section_engine');
        $this->CI->load->library('webshop_meta_engine');
    }

    public function inspect_page_render_context($method, $data = array())
    {
        $slug = $this->resolve_slug_from_method($method);
        $controller = $this->CI->router->class;
        $action = $this->CI->router->method;

        $pageData = $this->CI->webshop_dynamic_model->getPageData($slug);
        $pageConfig = isset($pageData['page']) && is_array($pageData['page']) ? $pageData['page'] : array();
        $sections = isset($pageData['sections']) && is_array($pageData['sections']) ? $pageData['sections'] : array();
        $tags = isset($pageData['tags']) && is_array($pageData['tags']) ? $pageData['tags'] : array();

        $activeTheme = $this->CI->webshop_theme_engine->resolve_active_theme(
            isset($data['webshop_settings']) ? $data['webshop_settings'] : null
        );

        $sectionSummary = $this->CI->webshop_section_engine->summarize_sections($sections);
        $metaContext = array(
            'page_title' => isset($pageConfig['page_name']) ? (string) $pageConfig['page_name'] : '',
            'slug' => $slug,
        );
        $metaHtml = $this->CI->webshop_meta_engine->render_meta_html($tags, $metaContext);

        return array(
            'enabled' => !empty($pageData['available']),
            'slug' => $slug,
            'controller' => (string) $controller,
            'method' => (string) $action,
            'theme' => $activeTheme,
            'layout' => isset($pageConfig['layout_name']) ? $pageConfig['layout_name'] : null,
            'page_config' => $pageConfig,
            'sections' => $sections,
            'tags' => $tags,
            'meta_html' => $metaHtml,
            'section_summary' => $sectionSummary,
        );
    }

    /**
     * Runtime resolver (Phase 4, guarded by config).
     * Applies dynamic section-to-legacy mapping for safe page targets.
     */
    public function resolve_runtime_render($method, $data = array())
    {
        $result = array(
            'applied' => false,
            'method' => $method,
            'data' => is_array($data) ? $data : array(),
            'reason' => '',
            'inspection' => array(),
        );

        if (!$this->is_dynamic_runtime_enabled()) {
            $result['reason'] = 'runtime_disabled';
            return $result;
        }
        if (!$this->is_safe_runtime_target($method)) {
            $result['reason'] = 'method_not_allowed';
            return $result;
        }

        $inspection = $this->inspect_page_render_context($method, $result['data']);
        $result['inspection'] = $inspection;
        if (!$this->is_safe_runtime_slug(isset($inspection['slug']) ? $inspection['slug'] : '/')) {
            $result['reason'] = 'slug_not_allowed';
            return $result;
        }
        if (empty($inspection['enabled'])) {
            $result['reason'] = 'page_not_enabled';
            return $result;
        }

        $seed = array(
            'home_has_header_section' => isset($result['data']['home_has_header_section']) ? (bool) $result['data']['home_has_header_section'] : true,
            'home_has_category_grid' => isset($result['data']['home_has_category_grid']) ? (bool) $result['data']['home_has_category_grid'] : true,
            'home_has_product_grid' => isset($result['data']['home_has_product_grid']) ? (bool) $result['data']['home_has_product_grid'] : true,
            'home_has_footer_section' => isset($result['data']['home_has_footer_section']) ? (bool) $result['data']['home_has_footer_section'] : true,
            'home_section_html_block' => isset($result['data']['home_section_html_block']) ? (string) $result['data']['home_section_html_block'] : '',
            'home_category_grid_title' => isset($result['data']['home_category_grid_title']) ? (string) $result['data']['home_category_grid_title'] : 'Shop by Category',
            'home_product_grid_title' => isset($result['data']['home_product_grid_title']) ? (string) $result['data']['home_product_grid_title'] : '',
        );

        $patch = $this->CI->webshop_section_engine->map_sections_to_home_patch(
            isset($inspection['sections']) ? $inspection['sections'] : array(),
            true,
            $seed
        );
        if (is_array($patch)) {
            foreach ($patch as $k => $v) {
                $result['data'][$k] = $v;
            }
        }

        if (!empty($inspection['meta_html'])) {
            $existingMeta = isset($result['data']['meta_tags']) ? trim((string) $result['data']['meta_tags']) : '';
            $dynamicMeta  = trim((string) $inspection['meta_html']);
            if ($existingMeta !== '') {
                // Avoid duplicating properties already set by entity/CMS tags.
                // Strip <title>, meta name="description", and all og:* from dynamic
                // model output before merging, so the entity-system values win.
                $dynamicMeta = preg_replace('/<title\b[^>]*>.*?<\/title>/is', '', $dynamicMeta);
                $dynamicMeta = preg_replace('/<meta\s[^>]*\bname\s*=\s*["\']description["\'][^>]*>/i', '', $dynamicMeta);
                $dynamicMeta = preg_replace('/<meta\s[^>]*\bproperty\s*=\s*["\']og:[a-z_:]+["\'][^>]*>/i', '', $dynamicMeta);
                $dynamicMeta = trim($dynamicMeta);
            }
            $result['data']['meta_tags'] = $dynamicMeta !== ''
                ? trim($existingMeta . "\n" . $dynamicMeta)
                : $existingMeta;
        }

        if (!empty($inspection['page_config']['page_name']) && empty($result['data']['page_title'])) {
            $result['data']['page_title'] = (string) $inspection['page_config']['page_name'];
        }

        $result['applied'] = true;
        $result['reason'] = 'applied';
        return $result;
    }

    private function resolve_slug_from_method($method)
    {
        $method = trim((string) $method, '/');
        if ($method === '' || $method === 'index') {
            return '/';
        }

        $parts = explode('/', $method);
        $slug = trim((string) end($parts));
        // Theme home views like "nw_theme/index" should resolve to root.
        if ($slug === '' || strtolower($slug) === 'index') {
            return '/';
        }
        $slug = trim((string) $slug);
        if ($slug === '') {
            return '/';
        }
        return '/' . $slug;
    }

    private function is_dynamic_runtime_enabled()
    {
        return (bool) $this->CI->config->item('webshop_dynamic_render_enabled', 'elintom_api');
    }

    private function is_safe_runtime_target($method)
    {
        $method = trim((string) $method, '/');
        if ($method === '' || $method === 'index') {
            return true;
        }
        $defaultAllowed = array('index', 'cms_page', 'nw_theme/index', 'gulfpharmacy_theme/index', 'webshop_restaurant_t1/index');
        if (in_array($method, $defaultAllowed, true)) {
            return true;
        }

        $csv = (string) $this->CI->config->item('webshop_dynamic_runtime_allowed_methods', 'elintom_api');
        if ($csv === '') {
            return false;
        }
        $parts = array_map('trim', explode(',', $csv));
        if (in_array('*', $parts, true) || in_array('all', $parts, true)) {
            return true;
        }
        return in_array($method, $parts, true);
    }

    private function is_safe_runtime_slug($slug)
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            $slug = '/';
        }
        if ($slug[0] !== '/') {
            $slug = '/' . $slug;
        }

        // Default is home page only for safest rollout.
        $defaultAllowed = array('/');
        if (in_array($slug, $defaultAllowed, true)) {
            return true;
        }

        $csv = (string) $this->CI->config->item('webshop_dynamic_runtime_allowed_slugs', 'elintom_api');
        if ($csv === '') {
            return false;
        }

        $parts = array_filter(array_map('trim', explode(',', $csv)));
        if (in_array('*', $parts, true) || in_array('all', $parts, true)) {
            return true;
        }
        $normalized = array();
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] !== '/') {
                $part = '/' . $part;
            }
            $normalized[] = $part;
        }
        return in_array($slug, $normalized, true);
    }
}
