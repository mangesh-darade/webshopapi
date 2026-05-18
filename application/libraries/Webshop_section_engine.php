<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Centralized section normalization + legacy storefront adapter.
 *
 * Phase 3 goal:
 * - keep current view variable contract unchanged
 * - map dynamic CMS sections into existing home-page flags/titles/html blocks
 */
class Webshop_section_engine
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }
    /**
     * Convert CMS section rows into a patch array for legacy home variables.
     *
     * @param array $sections
     * @param bool  $reset_visibility
     * @param array $seed Existing values to start from.
     * @return array
     */
    public function map_sections_to_home_patch($sections, $reset_visibility, $seed = array())
    {
        $patch = is_array($seed) ? $seed : array();
        if (!is_array($sections)) {
            return $patch;
        }

        if ($reset_visibility && !empty($sections)) {
            $patch['home_has_header_section'] = false;
            $patch['home_has_category_grid'] = false;
            $patch['home_has_product_grid'] = false;
            $patch['home_has_footer_section'] = false;
            $patch['home_section_html_block'] = '';
        }

        $htmlBlocks = array();
        foreach ($sections as $section) {
            $sec = is_object($section) ? (array) $section : (is_array($section) ? $section : array());
            $type = $this->normalize_section_type($sec);
            $cfg = $this->decode_config($this->section_row_config($sec));

            // Header/Footer types no longer trigger specific layout flags.
            // They will be treated as regular sections if they have content.
            //
            // For category/product types we only propagate the configured *title*
            // — the section itself is rendered as HTML through `render_components()`
            // and merged into `home_section_html_block`. Setting `home_has_category_grid`
            // or `home_has_product_grid` to true here would re-trigger the legacy
            // hard-coded "Shop by Category" / "Featured Products" panels in the
            // theme `index.php`, causing the same section to render twice.
            if ($type === 'category_grid' || $type === 'category_carousel') {
                if (isset($cfg['title']) && trim((string) $cfg['title']) !== '') {
                    $patch['home_category_grid_title'] = (string) $cfg['title'];
                }
                continue;
            }
            if ($type === 'product_grid' || $type === 'product_carousel') {
                if (isset($cfg['title']) && trim((string) $cfg['title']) !== '') {
                    $patch['home_product_grid_title'] = (string) $cfg['title'];
                }
                continue;
            }
            if ($type === 'html_block') {
                $merged = $this->merged_html_block_cfg($sec);
                $chunk = isset($merged['content']) ? trim((string) $merged['content']) : '';
                if ($chunk === '' && isset($merged['html'])) {
                    $chunk = trim((string) $merged['html']);
                }
                if ($chunk !== '') {
                    $htmlBlocks[] = $chunk;
                }
            }
        }

        if (!empty($htmlBlocks)) {
            $patch['home_section_html_block'] = implode("\n", $htmlBlocks);
        }

        return $patch;
    }

    public function summarize_sections($sections)
    {
        $summary = array(
            'total' => 0,
            'types' => array(),
        );
        if (!is_array($sections)) {
            return $summary;
        }
        foreach ($sections as $section) {
            $sec = is_object($section) ? (array) $section : (is_array($section) ? $section : array());
            $type = $this->normalize_section_type($sec);
            if ($type === '') {
                $type = 'unknown';
            }
            if (!isset($summary['types'][$type])) {
                $summary['types'][$type] = 0;
            }
            $summary['types'][$type]++;
            $summary['total']++;
        }
        return $summary;
    }

    public function decode_config($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_object($raw)) {
            return (array) $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return array();
    }

    /**
     * Resolve html_block section config when CMS stores JSON in config_json, raw HTML in section_contain,
     * or raw markup in config_json instead of a JSON object (ElintOm variants).
     *
     * @param array $section
     * @return array
     */
    private function merged_html_block_cfg(array $section)
    {
        $config = $this->section_row_config($section);
        $cfg = $this->decode_config($config);
        if (empty($cfg) && $config !== null && $config !== '') {
            if (is_string($config)) {
                $trim = trim($config);
                if ($trim !== '') {
                    $tryAssoc = json_decode($trim, true);
                    if (is_array($tryAssoc)) {
                        $cfg = $tryAssoc;
                    } else {
                        $scalar = json_decode($trim);
                        if (is_string($scalar)) {
                            $cfg['content'] = $scalar;
                        } elseif (isset($trim[0]) && $trim[0] !== '{' && $trim[0] !== '[') {
                            $cfg['content'] = $config;
                        }
                    }
                }
            }
        }
        if (isset($cfg['html']) && trim((string) $cfg['html']) !== ''
            && (!isset($cfg['content']) || trim((string) $cfg['content']) === '')) {
            $cfg['content'] = (string) $cfg['html'];
        }
        $haveContent = isset($cfg['content']) && trim((string) $cfg['content']) !== '';
        if (!$haveContent && isset($section['section_contain'])) {
            $sc = trim((string) $section['section_contain']);
            if ($sc !== '') {
                $try = json_decode($sc, true);
                if (is_array($try)) {
                    $cfg = array_merge($cfg, $try);
                    if ((!isset($cfg['content']) || trim((string) $cfg['content']) === '') && isset($cfg['html'])) {
                        $cfg['content'] = (string) $cfg['html'];
                    }
                } else {
                    $scalarSc = json_decode($sc);
                    if (is_string($scalarSc)) {
                        $cfg['content'] = $scalarSc;
                    } else {
                        $cfg['content'] = $sc;
                    }
                }
            }
        }
        return $cfg;
    }

    /**
     * View variables for html_block component.
     *
     * @param array $section
     * @return array
     */
    private function build_html_block_view_data(array $section)
    {
        $cfg = $this->merged_html_block_cfg($section);
        $content = isset($cfg['content']) ? (string) $cfg['content'] : '';
        if (trim($content) === '' && isset($cfg['html'])) {
            $content = (string) $cfg['html'];
        }
        $title = isset($cfg['title']) && trim((string) $cfg['title']) !== ''
            ? (string) $cfg['title']
            : (isset($cfg['heading']) ? (string) $cfg['heading'] : '');
        return array(
            'title' => $title,
            'content' => $content,
            'config' => $cfg,
        );
    }

    /**
     * Render section components dynamically (type -> view file).
     *
     * @param array $sections
     * @param array $data
     * @return string
     */
    public function render_components($sections, $data = array())
    {
        if (!is_array($sections) || !isset($this->CI->load)) {
            return '';
        }
        $sections = $this->sort_sections($sections);
        $html = array();
        foreach ($sections as $section) {
            $sec = is_object($section) ? (array) $section : (is_array($section) ? $section : array());
            $type = $this->normalize_section_type($sec);

            // `header` and `footer` are page chrome — the theme's header.php /
            // footer.php already render them. Rendering them again as a body
            // section produces visible duplicate footers/headers on the page.
            // The admin dropdown keeps these options for a future "override
            // site chrome" feature; for now they are no-ops in the body.
            if ($type === 'header' || $type === 'footer') {
                continue;
            }

            $view = $this->resolve_component_view($type);
            if ($view === '') {
                continue;
            }
            $sectionData = $this->build_section_data($sec, $data);
            if (isset($data['uploads'])) {
                $sectionData['uploads'] = $data['uploads'];
            }
            if (isset($data['thumbs'])) {
                $sectionData['thumbs'] = $data['thumbs'];
            }
            if (isset($data['Settings'])) {
                $sectionData['Settings'] = $data['Settings'];
            }
            if (!$this->should_render_section_heading($type)) {
                if (isset($sectionData['title'])) {
                    $sectionData['title'] = '';
                }
                if (isset($sectionData['heading'])) {
                    $sectionData['heading'] = '';
                }
            }
            if (!$this->should_render_catalog_section($type, $sectionData)) {
                continue;
            }
            $sectionData = $this->merge_component_theme_globals($sectionData, $data);
            $html[] = $this->CI->load->view($view, $sectionData, true);
        }
        return implode("\n", $html);
    }

    /**
     * Pass layout globals into CMS section partials (Assets_directory_name, view prefix, …).
     *
     * @param array $sectionData
     * @param array $data
     * @return array
     */
    protected function merge_component_theme_globals(array $sectionData, array $data)
    {
        $keys = array(
            'Assets_directory_name',
            'assets',
            'webshop_settings',
            'Settings',
            'uploads',
            'thumbs',
            'Customer_assets',
            'plane_vanila_theme_folder',
            'plane_vanila_view_prefix',
        );
        foreach ($keys as $key) {
            if ((!isset($sectionData[$key]) || $sectionData[$key] === '' || $sectionData[$key] === null)
                && isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                $sectionData[$key] = $data[$key];
            }
        }
        if (function_exists('webshop_theme_assets_base_url')) {
            if (!isset($sectionData['assets']) || (string) $sectionData['assets'] === '') {
                $sectionData['assets'] = webshop_theme_assets_base_url();
            }
        }
        if (function_exists('webshop_theme_assets_directory_name')) {
            if (!isset($sectionData['Assets_directory_name']) || (string) $sectionData['Assets_directory_name'] === '') {
                $sectionData['Assets_directory_name'] = webshop_theme_assets_directory_name();
            }
        }
        if (function_exists('webshop_plane_vanila_theme_folder')) {
            if (!isset($sectionData['plane_vanila_theme_folder']) || (string) $sectionData['plane_vanila_theme_folder'] === '') {
                $sectionData['plane_vanila_theme_folder'] = webshop_plane_vanila_theme_folder();
            }
        }
        if (function_exists('webshop_plane_vanila_view_prefix')) {
            if (!isset($sectionData['plane_vanila_view_prefix']) || (string) $sectionData['plane_vanila_view_prefix'] === '') {
                $sectionData['plane_vanila_view_prefix'] = webshop_plane_vanila_view_prefix(
                    isset($sectionData['plane_vanila_theme_folder']) ? $sectionData['plane_vanila_theme_folder'] : null
                );
            }
        }
        return $sectionData;
    }

    /**
     * Stable sort by sort_order (or order) ascending; preserves original order when equal.
     *
     * @param array $sections
     * @return array
     */
    public function sort_sections($sections)
    {
        if (!is_array($sections) || $sections === array()) {
            return is_array($sections) ? $sections : array();
        }
        $order_key = function ($row) {
            $s = is_object($row) ? (array) $row : (is_array($row) ? $row : array());
            if (isset($s['sort_order']) && $s['sort_order'] !== '' && $s['sort_order'] !== null) {
                return (int) $s['sort_order'];
            }
            if (isset($s['order']) && $s['order'] !== '' && $s['order'] !== null) {
                return (int) $s['order'];
            }
            return 0;
        };
        $indexed = array();
        $i = 0;
        foreach ($sections as $section) {
            $indexed[] = array('k' => $i++, 'ord' => $order_key($section), 'row' => $section);
        }
        usort($indexed, function ($a, $b) {
            if ($a['ord'] === $b['ord']) {
                return $a['k'] - $b['k'];
            }
            return $a['ord'] - $b['ord'];
        });
        $out = array();
        foreach ($indexed as $item) {
            $out[] = $item['row'];
        }
        return $out;
    }

    public function getProductGridData($config, $seed = array())
    {
        $cfg = $this->decode_config($config);
        $items = isset($seed['products']) && is_array($seed['products']) ? $seed['products'] : array();
        if (empty($items)) {
            $items = $this->fetch_products_for_section_config($cfg);
        }
        if (!empty($items) && isset($this->CI->webshop_model)
            && method_exists($this->CI->webshop_model, 'enrich_product_list_items_with_stock')) {
            $catId = (isset($cfg['category_id']) && is_numeric($cfg['category_id'])) ? (int) $cfg['category_id'] : 0;
            $items = $this->CI->webshop_model->enrich_product_list_items_with_stock($items, $catId);
        }
        return array(
            'title' => isset($cfg['title']) && trim((string) $cfg['title']) !== '' ? (string) $cfg['title'] : '',
            'products_per_page' => isset($cfg['products_per_page']) ? (int) $cfg['products_per_page'] : 8,
            'columns_desktop' => isset($cfg['columns_desktop']) ? (int) $cfg['columns_desktop'] : 4,
            'items' => $items,
        );
    }

    public function getCategoryGridData($config, $seed = array())
    {
        $cfg = $this->decode_config($config);
        $items = array();
        if (isset($seed['categories']) && is_array($seed['categories'])) {
            $items = $this->normalize_category_seed_items($seed['categories']);
        }
        if (empty($items) && isset($seed['main_categories']) && is_array($seed['main_categories'])) {
            $items = $this->normalize_category_seed_items($seed['main_categories']);
        }
        if (empty($items)) {
            $items = $this->fetch_categories_for_section_config($cfg);
        }
        return array(
            'title' => isset($cfg['title']) && trim((string) $cfg['title']) !== '' ? (string) $cfg['title'] : '',
            'columns_desktop' => isset($cfg['columns_desktop']) ? (int) $cfg['columns_desktop'] : 4,
            'items' => $items,
        );
    }

    public function getBannerData($config, $seed = array())
    {
        $cfg = $this->decode_config($config);
        return array(
            'title' => isset($cfg['title']) ? (string) $cfg['title'] : '',
            'content' => isset($cfg['content']) ? (string) $cfg['content'] : '',
            'image' => isset($cfg['image']) ? (string) $cfg['image'] : (isset($seed['image']) ? (string) $seed['image'] : ''),
            'link' => isset($cfg['link']) ? (string) $cfg['link'] : '#',
        );
    }

    private function build_section_data(array $section, array $seed = array())
    {
        $type = $this->normalize_section_type($section);
        $config = $this->section_row_config($section);
        if ($type === 'product_grid') {
            return $this->getProductGridData($config, $seed);
        }
        if ($type === 'product_carousel') {
            return $this->getProductCarouselData($config, $seed);
        }
        if ($type === 'category_grid') {
            return $this->getCategoryGridData($config, $seed);
        }
        if ($type === 'category_carousel') {
            return $this->getCategoryCarouselData($config, $seed);
        }
        if ($type === 'html_block') {
            return $this->build_html_block_view_data($section);
        }
        if ($type === 'banner' || $type === 'hero_banner') {
            return $this->getBannerData($config, $seed);
        }
        if ($type === 'header') {
            return array(
                'cms_nav_pages' => isset($seed['cms_nav_pages']) && is_array($seed['cms_nav_pages']) ? $seed['cms_nav_pages'] : array(),
                'page_title' => isset($seed['page_title']) ? (string) $seed['page_title'] : '',
            );
        }
        if ($type === 'footer') {
            return array(
                'cms_nav_pages' => isset($seed['cms_nav_pages']) && is_array($seed['cms_nav_pages']) ? $seed['cms_nav_pages'] : array(),
                'page_title' => isset($seed['page_title']) ? (string) $seed['page_title'] : '',
            );
        }
        return array(
            'section' => $section,
            'config' => $this->decode_config($config),
        );
    }

    /**
     * Carousel uses the same product payload as grid; markup/CSS differs.
     */
    public function getProductCarouselData($config, $seed = array())
    {
        $base = $this->getProductGridData($config, $seed);
        $base['carousel_variant'] = 'horizontal';
        return $base;
    }

    /**
     * Same category rows as grid; carousel layout in view.
     */
    public function getCategoryCarouselData($config, $seed = array())
    {
        $cfg = $this->decode_config($config);
        $base = $this->getCategoryGridData($config, $seed);
        return $base;
    }

    /**
     * Public alias for section type normalization (used by Webshop controller guards).
     *
     * @param array $section
     * @return string
     */
    public function normalized_section_type(array $section)
    {
        return $this->normalize_section_type($section);
    }

    /**
     * Only selected dynamic sections should show optional heading text.
     *
     * @param string $type
     * @return bool
     */
    private function should_render_section_heading($type)
    {
        $type = strtolower(trim((string) $type));
        return in_array($type, array(
            'html_block',
            'product_grid',
            'product_carousel',
            'category_grid',
            'category_carousel',
        ), true);
    }

    /**
     * Skip rendering catalog blocks when API/store returned zero rows (avoids duplicate empty "Shop by Category" headings).
     *
     * @param string $type
     * @param array  $sectionData
     * @return bool
     */
    private function should_render_catalog_section($type, array $sectionData)
    {
        if (!in_array($type, array('category_grid', 'category_carousel', 'product_grid', 'product_carousel'), true)) {
            return true;
        }
        $items = isset($sectionData['items']) && is_array($sectionData['items']) ? $sectionData['items'] : array();
        foreach ($items as $item) {
            $row = is_array($item) ? $item : (array) $item;
            if (!empty($row['id']) && (int) $row['id'] > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $section
     * @return mixed
     */
    private function section_row_config(array $section)
    {
        if (isset($section['config_json'])) {
            return $section['config_json'];
        }
        if (isset($section['section_contain'])) {
            return $section['section_contain'];
        }
        return null;
    }

    /**
     * @param array $cfg
     * @return array
     */
    private function fetch_products_for_section_config(array $cfg)
    {
        if (!isset($this->CI->webshop_model)) {
            return array();
        }
        $limit = isset($cfg['limit']) ? (int) $cfg['limit'] : 0;
        if ($limit < 1) {
            $limit = isset($cfg['products_per_page']) ? (int) $cfg['products_per_page'] : 0;
        }
        if ($limit < 1) {
            $limit = 12;
        }
        $m = $this->CI->webshop_model;
        $cid = isset($cfg['category_id']) ? $cfg['category_id'] : null;
        $res = null;
        if ($cid !== null && $cid !== '') {
            $res = $m->get_products_list('category', $cid, false, $limit, 1);
        } else {
            $res = $m->get_products_list(null, null, false, $limit, 1);
        }
        if (!is_array($res) || empty($res['items'])) {
            $tree = $m->get_categories();
            if (is_array($tree) && !empty($tree['main'])) {
                $firstId = key($tree['main']);
                $res = $m->get_products_list('category', $firstId, false, $limit, 1);
            }
        }
        if (!is_array($res) || empty($res['items'])) {
            return array();
        }
        $out = array();
        foreach ($res['items'] as $row) {
            $out[] = is_array($row) ? $row : (array) $row;
        }
        return $out;
    }

    /**
     * @param array $cfg
     * @return array
     */
    private function fetch_categories_for_section_config(array $cfg)
    {
        if (!isset($this->CI->webshop_model)) {
            return array();
        }
        $limit = isset($cfg['limit']) ? (int) $cfg['limit'] : 0;
        $m = $this->CI->webshop_model;
        $tree = $m->get_categories();
        if (!is_array($tree) || empty($tree['main'])) {
            return array();
        }
        $items = array();
        foreach ($tree['main'] as $cid => $row) {
            $o = is_object($row) ? $row : (object) $row;
            $items[] = array(
                'id' => isset($o->id) ? (int) $o->id : (int) $cid,
                'name' => isset($o->name) ? (string) $o->name : '',
                'image' => isset($o->image) ? (string) $o->image : '',
                'photo' => isset($o->photo) ? (string) $o->photo : '',
            );
            if ($limit > 0 && count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    /**
     * Normalize multiple category payload shapes to flat list for section views.
     *
     * @param array $rawItems
     * @return array
     */
    private function normalize_category_seed_items(array $rawItems)
    {
        // Some payloads pass categories as array('main' => array(...)).
        if (isset($rawItems['main']) && is_array($rawItems['main'])) {
            $rawItems = $rawItems['main'];
        }
        $items = array();
        foreach ($rawItems as $cid => $row) {
            $o = is_object($row) ? $row : (object) $row;
            $id = isset($o->id) ? (int) $o->id : (int) $cid;
            if ($id <= 0) {
                continue;
            }
            $items[] = array(
                'id' => $id,
                'name' => isset($o->name) ? (string) $o->name : '',
                'image' => isset($o->image) ? (string) $o->image : '',
                'photo' => isset($o->photo) ? (string) $o->photo : '',
            );
        }
        return $items;
    }

    private function resolve_component_view($type)
    {
        $theme = 'default';
        if (isset($this->CI->webshop_theme_engine)) {
            $theme = $this->CI->webshop_theme_engine->resolve_active_theme(
                isset($this->CI->webshop_settings) ? $this->CI->webshop_settings : null
            );
        }

        // Section type -> filename basename(s) we accept (first match wins).
        // Header/Footer reuse the CMS strip components instead of the theme's top-level
        // header.php / footer.php, which already render the storefront chrome.
        $candidateBasenames = array($type);
        if ($type === 'header') {
            $candidateBasenames = array('cms_header_section');
        } elseif ($type === 'footer') {
            $candidateBasenames = array('cms_footer_section');
        }

        // 1. Active theme folder from elintom_api_switch (per host)
        if (function_exists('webshop_plane_vanila_view')) {
            foreach ($candidateBasenames as $basename) {
                $themePath = webshop_plane_vanila_view('components/' . $basename);
                if (is_file(VIEWPATH . $themePath . '.php')) {
                    return $themePath;
                }
            }
        } elseif ($theme !== 'default') {
            foreach ($candidateBasenames as $basename) {
                $themePath = 'plane_vanila_theme/' . $theme . '_theme/components/' . $basename;
                if (is_file(VIEWPATH . $themePath . '.php')) {
                    return $themePath;
                }
            }
        }

        // 3. Legacy fallback (webshop/components/ is currently missing in filesystem)
        $map = array(
            'html_block' => 'webshop/components/html_block',
            'product_grid' => 'webshop/components/product_grid',
            'product_carousel' => 'webshop/components/product_carousel',
            'category_grid' => 'webshop/components/category_grid',
            'category_carousel' => 'webshop/components/category_carousel',
            'banner' => 'webshop/components/banner',
            'hero_banner' => 'webshop/components/banner',
            'header' => 'webshop/components/cms_header_section',
            'footer' => 'webshop/components/cms_footer_section',
        );
        return isset($map[$type]) ? $map[$type] : '';
    }

    private function normalize_section_type(array $section)
    {
        $raw = '';
        if (isset($section['section_type']) && trim((string) $section['section_type']) !== '') {
            $raw = (string) $section['section_type'];
        } elseif (isset($section['section_name'])) {
            $raw = (string) $section['section_name'];
        }
        $type = strtolower(trim($raw));
        $type = preg_replace('/[\s\-]+/', '_', $type);
        $type = trim($type, '_');
        $aliases = array(
            'html_component' => 'html_block',
            'htmlcomponent' => 'html_block',
        );
        return isset($aliases[$type]) ? $aliases[$type] : $type;
    }
}
