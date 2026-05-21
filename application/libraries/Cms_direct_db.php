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
        $body_html = $this->render_sections_html($sections);

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

        return $o;
    }

    protected function table_exists($base) {
        $t = $this->prefix . $base;
        $esc = $this->conn->real_escape_string($t);
        $q = $this->conn->query("SHOW TABLES LIKE '{$esc}'");
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
