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
            if ($type === 'category_grid' || $type === 'category_carousel') {
                $patch['home_has_category_grid'] = true;
                if (isset($cfg['title']) && trim((string) $cfg['title']) !== '') {
                    $patch['home_category_grid_title'] = (string) $cfg['title'];
                }
                continue;
            }
            if ($type === 'product_grid' || $type === 'product_carousel') {
                $patch['home_has_product_grid'] = true;
                if (isset($cfg['title']) && trim((string) $cfg['title']) !== '') {
                    $patch['home_product_grid_title'] = (string) $cfg['title'];
                }
                continue;
            }
            if ($type === 'html_block' && isset($cfg['content']) && trim((string) $cfg['content']) !== '') {
                $htmlBlocks[] = (string) $cfg['content'];
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
            if (!$this->should_render_catalog_section($type, $sectionData)) {
                continue;
            }
            $html[] = $this->CI->load->view($view, $sectionData, true);
        }
        return implode("\n", $html);
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
        return array(
            'title' => isset($cfg['title']) && trim((string) $cfg['title']) !== '' ? (string) $cfg['title'] : 'Featured Products',
            'products_per_page' => isset($cfg['products_per_page']) ? (int) $cfg['products_per_page'] : 8,
            'columns_desktop' => isset($cfg['columns_desktop']) ? (int) $cfg['columns_desktop'] : 4,
            'items' => $items,
        );
    }

    public function getCategoryGridData($config, $seed = array())
    {
        $cfg = $this->decode_config($config);
        $items = isset($seed['categories']) && is_array($seed['categories']) ? $seed['categories'] : array();
        if (empty($items)) {
            $items = $this->fetch_categories_for_section_config($cfg);
        }
        return array(
            'title' => isset($cfg['title']) && trim((string) $cfg['title']) !== '' ? (string) $cfg['title'] : 'Shop by Category',
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

    public function getHtmlBlockData($config)
    {
        $cfg = $this->decode_config($config);
        return array(
            'content' => isset($cfg['content']) ? (string) $cfg['content'] : '',
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
            return $this->getHtmlBlockData($config);
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
        if (!isset($cfg['title']) || trim((string) $cfg['title']) === '') {
            $base['title'] = 'Browse categories';
        }
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

    private function resolve_component_view($type)
    {
        $map = array(
            'html_block' => 'webshop/components/html_block',
            'product_grid' => 'webshop/components/product_grid',
            'product_carousel' => 'webshop/components/product_carousel',
            'category_grid' => 'webshop/components/category_grid',
            'category_carousel' => 'webshop/components/category_carousel',
            'banner' => 'webshop/components/banner',
            'hero_banner' => 'webshop/components/banner',
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
