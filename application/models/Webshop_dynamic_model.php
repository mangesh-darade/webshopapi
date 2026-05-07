<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dynamic rendering metadata read model (Phase 1).
 *
 * This model is intentionally read-focused and non-breaking:
 * - it only reads config tables if they exist
 * - returns empty results when schema is not available
 * - does not change legacy storefront behavior
 */
class Webshop_dynamic_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    private function has_db()
    {
        return isset($this->db);
    }

    private function table_name($base)
    {
        if (!$this->has_db()) {
            return null;
        }
        $prefixed = 'sma_' . $base;
        if ($this->db->table_exists($prefixed)) {
            return $prefixed;
        }
        if ($this->db->table_exists($base)) {
            return $base;
        }
        return null;
    }

    private function table_exists($base)
    {
        return $this->table_name($base) !== null;
    }

    public function is_dynamic_schema_available()
    {
        return $this->table_exists('pages')
            && $this->table_exists('sections_master')
            && $this->table_exists('page_section_mapping');
    }

    private function normalize_slug($slug)
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            $slug = '/';
        }
        if ($slug[0] !== '/') {
            $slug = '/' . $slug;
        }
        return $slug;
    }

    public function get_page_runtime_config($slug, $controller, $method)
    {
        if (!$this->is_dynamic_schema_available()) {
            return array();
        }

        $slug = $this->normalize_slug($slug);

        $pages = $this->table_name('pages');
        $this->db->select('id, page_name, page_type, url, status, updated_at');
        $this->db->from($pages);
        $this->db->where('url', $slug);
        $this->db->limit(1);
        $q = $this->db->get();
        if (!$q || $q->num_rows() === 0) {
            return array();
        }

        $row = (array) $q->row();
        $isActive = isset($row['status']) && strtolower((string) $row['status']) === 'published';
        return array(
            'page_id' => isset($row['id']) ? (int) $row['id'] : 0,
            'page_name' => isset($row['page_name']) ? (string) $row['page_name'] : '',
            'page_type' => isset($row['page_type']) ? (string) $row['page_type'] : '',
            'slug' => isset($row['url']) ? (string) $row['url'] : $slug,
            'controller_name' => (string) $controller,
            'method_name' => (string) $method,
            'theme_name' => null,
            'layout_name' => null,
            'meta_data' => array(),
            'active_status' => $isActive ? 1 : 0,
        );
    }

    public function get_page_sections($page_id)
    {
        $page_id = (int) $page_id;
        if ($page_id <= 0 || !$this->is_dynamic_schema_available()) {
            return array();
        }

        $psm = $this->table_name('page_section_mapping');
        $sm = $this->table_name('sections_master');

        $this->db->select('psm.id, psm.page_id, psm.section_id, psm.sort_order, psm.config_json, psm.is_enabled,
            sm.section_name, sm.section_type, sm.ui_component, sm.util_function, sm.config_schema, sm.is_dynamic');
        $this->db->from($psm . ' psm');
        $this->db->join($sm . ' sm', 'sm.id = psm.section_id', 'inner');
        $this->db->where('psm.page_id', $page_id);
        $this->db->where('psm.is_enabled', 1);
        $this->db->order_by('psm.sort_order', 'ASC');
        $q = $this->db->get();

        return ($q && $q->num_rows() > 0) ? $q->result_array() : array();
    }

    public function get_page_tags_with_fallback($page_id, $page_type = '')
    {
        $page_id = (int) $page_id;
        $page_type = trim((string) $page_type);
        if ($page_id <= 0 || !$this->has_db()) {
            return array();
        }

        $ptm = $this->table_name('page_tag_mapping');
        $pttd = $this->table_name('page_type_tag_defaults');
        $gtd = $this->table_name('global_tag_defaults');
        $tm = $this->table_name('tags_master');

        if ($ptm === null || $tm === null) {
            return array();
        }

        $tags = array();

        // 1) Page-level tags (highest priority)
        $this->db->select('ptm.property_name, ptm.value, tm.tag_name, tm.tag_type');
        $this->db->from($ptm . ' ptm');
        $this->db->join($tm . ' tm', 'tm.id = ptm.tag_id', 'left');
        $this->db->where('ptm.page_id', $page_id);
        $q = $this->db->get();
        if ($q && $q->num_rows() > 0) {
            foreach ($q->result_array() as $row) {
                $key = isset($row['property_name']) ? trim((string) $row['property_name']) : '';
                if ($key === '') {
                    continue;
                }
                $tags[$key] = array(
                    'property_name' => $key,
                    'value' => isset($row['value']) ? (string) $row['value'] : '',
                    'tag_name' => isset($row['tag_name']) ? (string) $row['tag_name'] : $key,
                    'tag_type' => isset($row['tag_type']) ? (string) $row['tag_type'] : 'meta',
                    'source' => 'page',
                );
            }
        }

        // 2) Page-type defaults (only if property missing from page-level)
        if ($pttd !== null && $page_type !== '') {
            $this->db->select('pttd.property_name, pttd.value, tm.tag_name, tm.tag_type');
            $this->db->from($pttd . ' pttd');
            $this->db->join($tm . ' tm', 'tm.id = pttd.tag_id', 'left');
            $this->db->where('pttd.page_type', $page_type);
            $q = $this->db->get();
            if ($q && $q->num_rows() > 0) {
                foreach ($q->result_array() as $row) {
                    $key = isset($row['property_name']) ? trim((string) $row['property_name']) : '';
                    if ($key === '' || isset($tags[$key])) {
                        continue;
                    }
                    $tags[$key] = array(
                        'property_name' => $key,
                        'value' => isset($row['value']) ? (string) $row['value'] : '',
                        'tag_name' => isset($row['tag_name']) ? (string) $row['tag_name'] : $key,
                        'tag_type' => isset($row['tag_type']) ? (string) $row['tag_type'] : 'meta',
                        'source' => 'page_type',
                    );
                }
            }
        }

        // 3) Global defaults (only if still missing)
        if ($gtd !== null) {
            $this->db->select('gtd.property_name, gtd.value, tm.tag_name, tm.tag_type');
            $this->db->from($gtd . ' gtd');
            $this->db->join($tm . ' tm', 'tm.id = gtd.tag_id', 'left');
            $q = $this->db->get();
            if ($q && $q->num_rows() > 0) {
                foreach ($q->result_array() as $row) {
                    $key = isset($row['property_name']) ? trim((string) $row['property_name']) : '';
                    if ($key === '' || isset($tags[$key])) {
                        continue;
                    }
                    $tags[$key] = array(
                        'property_name' => $key,
                        'value' => isset($row['value']) ? (string) $row['value'] : '',
                        'tag_name' => isset($row['tag_name']) ? (string) $row['tag_name'] : $key,
                        'tag_type' => isset($row['tag_type']) ? (string) $row['tag_type'] : 'meta',
                        'source' => 'global',
                    );
                }
            }
        }

        return array_values($tags);
    }

    public function getPageData($url)
    {
        $slug = $this->normalize_slug($url);
        $result = array(
            'page' => array(),
            'sections' => array(),
            'tags' => array(),
            'meta_html' => '',
            'available' => false,
        );
        if (!$this->is_dynamic_schema_available()) {
            return $result;
        }

        $page = $this->get_page_runtime_config($slug, 'webshop', 'index');
        if (empty($page) || empty($page['page_id'])) {
            return $result;
        }

        $result['page'] = $page;
        $result['sections'] = $this->get_page_sections((int) $page['page_id']);
        $result['tags'] = $this->get_page_tags_with_fallback((int) $page['page_id'], isset($page['page_type']) ? $page['page_type'] : '');
        $result['available'] = !empty($page['active_status']);
        return $result;
    }
}
