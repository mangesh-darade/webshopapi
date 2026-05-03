<?php defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {

    /** @var string|null Absolute uploads base (…/assets/mdata/{host}/uploads/ or …/mdata/{tenant}/uploads/) from getsettings or NULL; public for Webshop_api_model::get_media_uploads_base() */
    public $api_media_uploads_base = null;

    function __construct()
    {
        parent::__construct();
        $this->Customer_assets = elintom_customer_assets_from_http_host();
        $this->shopowner  = $this->checkusers();

        // 100% DB-less approach: Fetch settings from API
        $this->load->model('webshop_api_model');
        $api_data = $this->webshop_api_model->get_settings();

        if ($api_data && isset($api_data->status) && $api_data->status === 'SUCCESS') {
            $this->Settings = (object) $api_data->pos_settings;
            $this->webshop_settings = (object) $api_data->webshop_settings;
            $this->pos_settings = (object) $api_data->pos_config;
            $this->api_website_setting = isset($api_data->website_setting)
                ? $api_data->website_setting
                : array();
            $this->_normalize_settings_from_api();
            if (!isset($this->Settings->active_webshop)) {
                $this->Settings->active_webshop = 1;
            }
            $this->api_media_uploads_base = $this->_resolve_media_uploads_base_from_api($api_data);
            $this->_sync_customer_assets_from_api_settings();
        } else {
            // Transport failure, empty body, or JSON { status: ERROR }
            echo "<h2>API Connection Error</h2>";
            if ($api_data && isset($api_data->status) && strtoupper((string) $api_data->status) === 'ERROR') {
                $em = isset($api_data->msg) ? (string) $api_data->msg : 'Unknown API error.';
                echo "<p><strong>ElintOm API returned an error (HTTP OK, JSON error):</strong><br>"
                    . htmlspecialchars($em) . "</p>";
                echo "<p>Fix this in ElintOm (settings, API key, POS version ≥ 3, etc.), then reload.</p><br>";
            } else {
                echo "Failed to reach ElintOm or retrieve settings (network / empty response / invalid JSON).<br><br>";
            }

            // Get the exact API client instance that executed the request
            $client = $this->webshop_api_model->get_api_client();
            
            if ($client) {
                $err = $client->get_last_error();
                echo "<strong>Internal Client Error:</strong> " . ($err !== null && $err !== '' ? htmlspecialchars((string) $err) : '(none — see Parsed API Payload)') . "<br>";
                $raw = $client->get_last_raw_response();
                echo "<strong>Raw Response String:</strong> <pre>" . htmlentities($raw === null || $raw === '' ? '(empty)' : (string) $raw) . "</pre><br>";
            }

            echo "<strong>Parsed API Payload:</strong><br><pre>";
            echo htmlspecialchars($api_data === null ? '(null)' : print_r($api_data, true));
            echo "</pre>";
            die();
        }

        // $this->site->resetExpirePromos(); // Disabled without DB
        // $this->load->model('pos_model');  // Disabled without DB
        if($sma_language = $this->input->cookie('sma_language', TRUE)) {
            $this->config->set_item('language', $sma_language);
            if (file_exists(APPPATH . 'language/' . $sma_language . '/sma_lang.php')) {
                $this->lang->load('sma', $sma_language);
            }
            $this->Settings->user_language = $sma_language;
        } else {
            $this->config->set_item('language', $this->Settings->language);
            if (file_exists(APPPATH . 'language/' . $this->Settings->language . '/sma_lang.php')) {
                $this->lang->load('sma', $this->Settings->language);
            }
            $this->Settings->user_language = $this->Settings->language;
        }
        if($rtl_support = $this->input->cookie('sma_rtl_support', TRUE)) {
            $this->Settings->user_rtl = $rtl_support;
        } else {
            $this->Settings->user_rtl = $this->Settings->rtl;
        }
        $this->theme = $this->Settings->theme.'/views/';
        if(is_dir(VIEWPATH.$this->Settings->theme.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR)) {
            $this->data['assets'] = base_url() . 'themes/' . $this->Settings->theme . '/assets/';
        } else {
            $this->data['assets'] = base_url() . 'themes/default/assets/';
        }
        $this->load->helper('genfun_helper');
        $this->data['Settings'] = $this->Settings;
        $this->data['Shopowner'] = $this->shopowner;

        $this->Customer_url = $this->Customer_assets;
        $this->data['Customer_assets'] = $this->Customer_assets;

        if(isset($this->Settings->pos_type) && $this->Settings->pos_type == 'restaurant' ){
            if (isset($this->db)) {
                $this->data['UPSettings'] = $this->db->select('*')->where(['id' => '1'])->get('sma_up_settings')->row();
            } else {
                $this->data['UPSettings'] = new stdClass();
            }
        }

        // $this->pos_settings = $this->pos_model->getSetting(); // Resolved via API above
       
        $this->data['pos_settings'] = $this->pos_settings;

        $this->load->library('sma');
        $this->loggedIn = $this->sma->logged_in();
    
        if($this->loggedIn) {
            if (isset($this->site)) {
                $this->default_currency = $this->site->getCurrencyByCode($this->Settings->default_currency);
                $this->data['default_currency'] = $this->default_currency;
            } else {
                $this->default_currency = new stdClass();
                $this->data['default_currency'] = NULL;
            }
            $this->Owner = $this->sma->in_group('owner') ? TRUE : NULL;
            $this->data['Owner'] = $this->Owner;
            $this->Customer = $this->sma->in_group('customer') ? TRUE : NULL;
            $this->data['Customer'] = $this->Customer;
            $this->Supplier = $this->sma->in_group('supplier') ? TRUE : NULL;
            $this->data['Supplier'] = $this->Supplier;
            $this->Admin = $this->sma->in_group('admin') ? TRUE : NULL;
            $this->data['Admin'] = $this->Admin;
    	   
            if($sd = $this->site->getDateFormat($this->Settings->dateformat)) {
                $dateFormats = array(
                    'js_sdate' => $sd->js,
                    'php_sdate' => $sd->php,
                    'mysq_sdate' => $sd->sql,
                    'js_ldate' => $sd->js . ' hh:ii',
                    'php_ldate' => $sd->php . ' H:i',
                    'mysql_ldate' => $sd->sql . ' %H:%i'
                    );
            } else {
                $dateFormats = array(
                    'js_sdate' => 'mm-dd-yyyy',
                    'php_sdate' => 'm-d-Y',
                    'mysq_sdate' => '%m-%d-%Y',
                    'js_ldate' => 'mm-dd-yyyy hh:ii:ss',
                    'php_ldate' => 'm-d-Y H:i:s',
                    'mysql_ldate' => '%m-%d-%Y %T'
                    );
            }
            if(file_exists(APPPATH.'controllers'.DIRECTORY_SEPARATOR.'Pos.php')) {
                define("POS", 1);
            } else {
                define("POS", 0);
            }
            if(!$this->Owner && !$this->Admin) {
                if (isset($this->site)) {
                    $gp = $this->site->checkPermissions();
                    $this->GP = $gp[0];
                    $this->data['GP'] = $gp[0];
                } else {
                    $this->GP = NULL;
                    $this->data['GP'] = NULL;
                }
            } else {
                $this->data['GP'] = NULL;
            }
            $this->dateFormats = $dateFormats;
            $this->data['dateFormats'] = $dateFormats;
            $this->load->language('calendar');
            //$this->default_currency = $this->Settings->currency_code;
            //$this->data['default_currency'] = $this->default_currency;
            $this->m = strtolower($this->router->fetch_class());
            $this->v = strtolower($this->router->fetch_method());
            $this->data['m']= $this->m;
            $this->data['v'] = $this->v;
            $this->data['dt_lang'] = json_encode(lang('datatables_lang'));
            $this->data['dp_lang'] = json_encode(array('days' => array(lang('cal_sunday'), lang('cal_monday'), lang('cal_tuesday'), lang('cal_wednesday'), lang('cal_thursday'), lang('cal_friday'), lang('cal_saturday'), lang('cal_sunday')), 'daysShort' => array(lang('cal_sun'), lang('cal_mon'), lang('cal_tue'), lang('cal_wed'), lang('cal_thu'), lang('cal_fri'), lang('cal_sat'), lang('cal_sun')), 'daysMin' => array(lang('cal_su'), lang('cal_mo'), lang('cal_tu'), lang('cal_we'), lang('cal_th'), lang('cal_fr'), lang('cal_sa'), lang('cal_su')), 'months' => array(lang('cal_january'), lang('cal_february'), lang('cal_march'), lang('cal_april'), lang('cal_may'), lang('cal_june'), lang('cal_july'), lang('cal_august'), lang('cal_september'), lang('cal_october'), lang('cal_november'), lang('cal_december')), 'monthsShort' => array(lang('cal_jan'), lang('cal_feb'), lang('cal_mar'), lang('cal_apr'), lang('cal_may'), lang('cal_jun'), lang('cal_jul'), lang('cal_aug'), lang('cal_sep'), lang('cal_oct'), lang('cal_nov'), lang('cal_dec')), 'today' => lang('today'), 'suffix' => array(), 'meridiem' => array()));

        }
    }

    /**
     * Legacy Api3/merged API payloads may omit columns the POS webshop always had (sma_settings row).
     * Fill safe defaults so views and Sma do not throw notices/fatals.
     */
    protected function _normalize_settings_from_api() {
        $S = $this->Settings;
        if (!is_object($S)) {
            $this->Settings = new stdClass();
            $S = $this->Settings;
        }
        $lang = $this->config->item('language');
        if (!isset($S->language) || $S->language === '' || $S->language === null) {
            $S->language = is_string($lang) && $lang !== '' ? $lang : 'english';
        }
        if (!isset($S->rtl) || $S->rtl === '' || $S->rtl === null) {
            $S->rtl = '0';
        }
        if (!isset($S->theme) || $S->theme === '' || $S->theme === null) {
            if (isset($S->webshop_theme) && (string) $S->webshop_theme !== '') {
                $S->theme = (string) $S->webshop_theme;
            } elseif (isset($S->default_eshop_theame) && (string) $S->default_eshop_theame !== '') {
                $S->theme = (string) $S->default_eshop_theame;
            } else {
                $S->theme = 'default';
            }
        }
        if (!isset($S->dateformat) || $S->dateformat === '' || $S->dateformat === null) {
            $S->dateformat = '1';
        }
        if (!isset($S->default_currency) || $S->default_currency === '' || $S->default_currency === null) {
            $S->default_currency = 'USD';
        }
        // Used by Sma::formatMoney when present
        if (!isset($S->decimals)) {
            $S->decimals = 2;
        }
        if (!isset($S->decimals_sep)) {
            $S->decimals_sep = '.';
        }
        if (!isset($S->thousands_sep)) {
            $S->thousands_sep = ',';
        }
        if (!isset($S->display_symbol)) {
            $S->display_symbol = 1;
        }
        // Sma::formatMoney / views expect currency symbol; api pos_settings may omit it
        if (!isset($S->symbol) || $S->symbol === '' || $S->symbol === null) {
            $S->symbol = '$';
        }
        if (!isset($S->sac)) {
            $S->sac = 0;
        }
        // active_webshop: from API sma_settings, else pos_config.eshop_active (sma_pos_settings)
        $aw = null;
        if (isset($S->active_webshop) && $S->active_webshop !== '' && $S->active_webshop !== null) {
            $aw = (int) $S->active_webshop;
        } elseif (isset($S->eshop_active) && $S->eshop_active !== '' && $S->eshop_active !== null) {
            $aw = (int) $S->eshop_active;
        } else {
            $ps = $this->pos_settings;
            if (is_object($ps) && isset($ps->eshop_active) && $ps->eshop_active !== '' && $ps->eshop_active !== null) {
                $aw = (int) $ps->eshop_active;
            } elseif (is_array($ps) && isset($ps['eshop_active']) && $ps['eshop_active'] !== '' && $ps['eshop_active'] !== null) {
                $aw = (int) $ps['eshop_active'];
            } else {
                $ws = $this->webshop_settings;
                if (is_object($ws) && isset($ws->eshop_active) && $ws->eshop_active !== '' && $ws->eshop_active !== null) {
                    $aw = (int) $ws->eshop_active;
                } elseif (is_array($ws) && isset($ws['eshop_active']) && $ws['eshop_active'] !== '' && $ws['eshop_active'] !== null) {
                    $aw = (int) $ws['eshop_active'];
                }
            }
        }
        $S->active_webshop = ($aw !== null) ? ($aw ? 1 : 0) : 1;
    }

    /**
     * Optional absolute URL from ElintOm getsettings for assets/mdata/.../uploads/
     * Root or pos_settings / webshop_settings may expose: media_uploads_base_url, mdata_url, uploads_base_url.
     */
    protected function _resolve_media_uploads_base_from_api($api_data) {
        if (!is_object($api_data)) {
            return null;
        }
        $keys = array('media_uploads_base_url', 'mdata_url', 'uploads_base_url', 'mdata_base_url');
        foreach ($keys as $k) {
            if (isset($api_data->$k)) {
                $u = $this->_sanitize_absolute_uploads_base($api_data->$k);
                if ($u !== null) {
                    return $u;
                }
            }
        }
        foreach (array('pos_settings', 'webshop_settings') as $block) {
            if (!isset($api_data->$block) || !is_object($api_data->$block)) {
                continue;
            }
            $obj = $api_data->$block;
            foreach ($keys as $k) {
                if (isset($obj->$k)) {
                    $u = $this->_sanitize_absolute_uploads_base($obj->$k);
                    if ($u !== null) {
                        return $u;
                    }
                }
            }
        }
        return null;
    }

    protected function _sanitize_absolute_uploads_base($url) {
        if (!is_string($url) || trim($url) === '') {
            return null;
        }
        $u = rtrim(str_replace('\\', '/', trim($url)), '/');
        if (!preg_match('#^https?://#i', $u)) {
            return null;
        }
        return $u . '/';
    }

    protected function _sync_customer_assets_from_api_settings() {
        $blocks = array();
        if (is_object($this->Settings)) {
            $blocks[] = $this->Settings;
        }
        if (isset($this->webshop_settings) && is_object($this->webshop_settings)) {
            $blocks[] = $this->webshop_settings;
        }
        foreach ($blocks as $S) {
            foreach (array('eshop_customer_assets', 'customer_assets_folder', 'mdata_folder', 'tenant_key', 'customer_assets',
                'subdomain', 'shop_subdomain', 'eshop_subdomain', 'mdata_customer_key') as $k) {
                if (!isset($S->$k)) {
                    continue;
                }
                $raw = (string) $S->$k;
                if ($raw === '') {
                    continue;
                }
                $folder = preg_replace('/[^a-zA-Z0-9_.-]/', '', $raw);
                if ($folder !== '') {
                    $this->Customer_assets = $folder;
                    return;
                }
            }
        }
    }

    public function checkusers(){
        if (!isset($this->db)) {
            return null;
        }
        $user_id            = $this->session->userdata('user_id');
        $this->db->select('group_id');
        $this->db->where('id', $user_id);
        $q = $this->db->get("sma_users");

        // $q = $this->db->get('sma_users');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data = $row;
            }
            return $data;
        }
    }
    function page_construct($page, $meta = array(), $data = array()) {
        $meta['message'] = isset($data['message']) ? $data['message'] : $this->session->flashdata('message');
        $meta['error'] = isset($data['error']) ? $data['error'] : $this->session->flashdata('error');
        $meta['warning'] = isset($data['warning']) ? $data['warning'] : $this->session->flashdata('warning');
        
        if (isset($this->site)) {
            $meta['info'] = $this->site->getNotifications();
            $meta['events'] = $this->site->getUpcomingEvents();
            $meta['eshop_due_payment'] =  $this->site->getEshopPaymentDueOrder();
            $meta['qty_alert_num'] = $this->site->get_total_qty_alerts();
            $meta['exp_alert_num'] = $this->site->get_expiring_qty_alerts();
        } else {
            $meta['info'] = 0;
            $meta['events'] = 0;
            $meta['eshop_due_payment'] = 0;
            $meta['qty_alert_num'] = 0;
            $meta['exp_alert_num'] = 0;
        }

        $meta['ip_address'] = $this->input->ip_address();
        $meta['Owner'] = $data['Owner'];
        $meta['Admin'] = $data['Admin'];
        $meta['Supplier'] = $data['Supplier'];
        $meta['Customer'] = $data['Customer'];
        $meta['Settings'] = $data['Settings'];
        $meta['Shopowner'] = isset($data['Shopowner']) ? $data['Shopowner']->group_id : null;
        $meta['pos_settings'] = $data['pos_settings'];
        $meta['dateFormats'] = $data['dateFormats'];
        $meta['assets'] = $data['assets'];
        $meta['GP'] = $data['GP'];
        $this->load->view($this->theme . 'header', $meta);
        $this->load->view($this->theme . $page, $data);
        $this->load->view($this->theme . 'footer');
    }

    function page_view($page, $data = array()) {
        $meta['message'] = isset($data['message']) ? $data['message'] : $this->session->flashdata('message');
        $meta['error'] = isset($data['error']) ? $data['error'] : $this->session->flashdata('error');
        $meta['warning'] = isset($data['warning']) ? $data['warning'] : $this->session->flashdata('warning');
       
        $this->load->view($this->theme . $page, $data);
        
    }
    
	function pos_error_log(array $logger){
		$pos_url   = base_url();
		$error_message = $logger[0];
		$errorUrl = $logger[1];
		$error_time    = time();
	 
	}
     // Db Switch implement
     function Customer_url($data) {
      
        $data = $data;
       return $data;
    }
}
