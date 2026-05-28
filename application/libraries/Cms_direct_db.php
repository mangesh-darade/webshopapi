<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read published CMS pages directly from ElintOm MySQL when getcmspage API is unavailable.
 * Returns the same stdClass shape as Webshop_api_model::get_cms_page_content().
 */
class Cms_direct_db {

    /** @var mysqli|null */
    protected $conn;

    /** @var string */
    protected $prefix = 'sma_';

    public function __construct() {
        $CI =& get_instance();
        $CI->config->load('elintom_api', true);
        if (!(bool) $CI->config->item('elintom_cms_direct_db', 'elintom_api')) {
            return;
        }
        $host = (string) $CI->config->item('elintom_cms_db_hostname', 'elintom_api');
        $user = (string) $CI->config->item('elintom_cms_db_username', 'elintom_api');
        $pass = (string) $CI->config->item('elintom_cms_db_password', 'elintom_api');
        $name = (string) $CI->config->item('elintom_cms_db_database', 'elintom_api');
        if ($host === '' || $name === '') {
            return;
        }
        $conn = @new mysqli($host, $user, $pass, $name);
        if ($conn->connect_error) {
            log_message('error', 'Cms_direct_db: connect failed ' . $conn->connect_error);
            return;
        }
        $conn->set_charset('utf8');
        $this->conn = $conn;
    }

    public function is_ready() {
        return $this->conn instanceof mysqli && $this->table_exists('pages');
    }

    /**
     * Published pages for header/footer nav (mirrors ElintOm Cms_model::getPublishedPages).
     *
     * @param array<int,string>   $page_types
     * @param string|null         $placement header|footer|null (all)
     * @return array<int,array<string,mixed>>
     */
    public function list_published_pages(array $page_types = array('static'), $placement = null) {
        if (!$this->is_ready()) {
            return array();
        }
        $pages = $this->prefix . 'pages';
        $select = array('id', 'page_name', 'page_type', 'url', 'status', 'updated_at');
        $order = 'ORDER BY `page_name` ASC, `id` ASC';
        if ($this->column_exists('pages', 'nav_order')) {
            $select[] = 'nav_order';
            $order = 'ORDER BY `nav_order` ASC, `page_name` ASC, `id` ASC';
        }
        if ($this->column_exists('pages', 'show_in_header')) {
            $select[] = 'show_in_header';
        }
        if ($this->column_exists('pages', 'show_in_footer')) {
            $select[] = 'show_in_footer';
        }
        $sql = 'SELECT `' . implode('`, `', $select) . "` FROM `{$pages}` WHERE `status` = 'published'";
        $placement = strtolower(trim((string) $placement));
        if ($placement === 'header' && $this->column_exists('pages', 'show_in_header')) {
            $sql .= ' AND `show_in_header` = 1';
        } elseif ($placement === 'footer' && $this->column_exists('pages', 'show_in_footer')) {
            $sql .= ' AND `show_in_footer` = 1';
        }
        if (!empty($page_types)) {
            $types = array();
            foreach ($page_types as $type) {
                $type = trim((string) $type);
                if ($type !== '') {
                    $types[] = "'" . $this->conn->real_escape_string($type) . "'";
                }
            }
            if (!empty($types)) {
                $sql .= ' AND `page_type` IN (' . implode(',', $types) . ')';
            }
        }
        $sql .= ' ' . $order;
        $q = $this->conn->query($sql);
        if (!$q) {
            return array();
        }
        $out = array();
        while ($row = $q->fetch_assoc()) {
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param string $url_path e.g. /about-us
     * @return stdClass|null
     */
    public function get_page_by_url($url_path) {
        if (!$this->is_ready()) {
            return null;
        }
        $url = '/' . ltrim((string) $url_path, '/');
        if ($url === '//') {
            $url = '/';
        }

        $pages = $this->prefix . 'pages';
        $stmt = $this->conn->prepare(
            "SELECT * FROM `{$pages}` WHERE `url` = ? AND `status` = 'published' LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $url);
        $stmt->execute();
        $res = $stmt->get_result();
        $page = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$page) {
            return null;
        }

        $page_id = (int) $page['id'];
        $sections = $this->load_sections($page_id);
        // page_text is not pre-rendered when sections exist — Webshop_section_engine renders them once.
        $body_html = !empty($sections) ? '' : $this->render_sections_html($sections);

        $o = new stdClass();
        $o->page_key = ltrim($url, '/');
        $o->url = $url;
        $o->page_type = isset($page['page_type']) ? strtolower((string) $page['page_type']) : 'static';
        $o->status = 'published';
        $o->page_title = isset($page['page_name']) ? (string) $page['page_name'] : '';
        $o->page_text = $body_html;
        $o->meta_tags = '';
        $o->sections = $sections;
        $o->meta_tags_raw = array();
        $o->header_html = '';
        $o->footer_html = '';
        $o->banner_html = '';
        $o->logo_html = '';
        $o->show_header = true;
        $o->show_footer = true;
        $o->page_banner_image_url = $this->build_media_url(isset($page['banner_image']) ? $page['banner_image'] : '');
        $o->page_logo_image_url = $this->build_media_url(isset($page['logo_image']) ? $page['logo_image'] : '');
        $o->page_summary = '';
        $o->page_description = '';
        $o->cms_loaded_from_api = true;
        $o->cms_loaded_via = 'direct_db';
        $o->cms_page_found = true;
        $o->id = $page_id;

        return $o;
    }

    protected function table_exists($base) {
        $t = $this->prefix . $base;
        $esc = $this->conn->real_escape_string($t);
        $q = $this->conn->query("SHOW TABLES LIKE '{$esc}'");
        return $q && $q->num_rows > 0;
    }

    protected function column_exists($table_base, $column) {
        $t = $this->prefix . $table_base;
        $esc_t = $this->conn->real_escape_string($t);
        $esc_c = $this->conn->real_escape_string((string) $column);
        $q = $this->conn->query("SHOW COLUMNS FROM `{$esc_t}` LIKE '{$esc_c}'");
        return $q && $q->num_rows > 0;
    }

    protected function load_sections($page_id) {
        $map = $this->prefix . 'page_section_mapping';
        $sm = $this->prefix . 'sections_master';
        if (!$this->table_exists('page_section_mapping') || !$this->table_exists('sections_master')) {
            return array();
        }
        $page_id = (int) $page_id;
        $sql = "SELECT psm.*, sm.section_name, sm.section_type, sm.is_dynamic, sm.util_function
                FROM `{$map}` psm
                INNER JOIN `{$sm}` sm ON sm.id = psm.section_id
                WHERE psm.page_id = {$page_id} AND psm.is_enabled = 1
                ORDER BY psm.sort_order ASC";
        $q = $this->conn->query($sql);
        if (!$q) {
            return array();
        }
        $out = array();
        while ($row = $q->fetch_assoc()) {
            $out[] = $row;
        }
        return $out;
    }

    protected function render_sections_html(array $sections) {
        $chunks = array();
        foreach ($sections as $section) {
            $type = isset($section['section_type']) ? strtolower((string) $section['section_type']) : '';
            $raw = isset($section['section_contain']) ? $section['section_contain'] : '';
            if (trim((string) $raw) === '' && isset($section['config_json'])) {
                $raw = $section['config_json'];
            }
            $cfg = is_string($raw) && $raw !== '' ? json_decode($raw, true) : array();
            if (!is_array($cfg)) {
                $cfg = array();
            }
            if ($type === 'html_block' || $type === 'html' || $type === 'html_component') {
                foreach (array('content', 'html', 'body') as $k) {
                    if (!empty($cfg[$k])) {
                        $chunks[] = (string) $cfg[$k];
                        continue 2;
                    }
                }
            }
            if (is_string($raw) && strpos(trim($raw), '<') !== false) {
                $chunks[] = $raw;
            }
        }
        return implode("\n", $chunks);
    }

    protected function build_media_url($file) {
        $file = trim((string) $file);
        if ($file === '') {
            return '';
        }
        if (strpos($file, 'http') === 0) {
            return $file;
        }
        $CI =& get_instance();
        $base = (string) $CI->config->item('elintom_media_uploads_base_url', 'elintom_api');
        $base = rtrim($base, '/') . '/';
        return $base . 'webshop/cms_pages/' . ltrim($file, '/');
    }
}
