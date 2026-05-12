<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Checkout page (GET) — theme branches and shared cart/geo/customer data for {@see Webshop::checkout()}.
 */
class Webshop_checkout {

    /**
     * Build checkout view data and load the theme template.
     *
     * @param object $c Webshop controller ($this)
     */
    public function present($c) {
        if (!isset($_SESSION['cart'])) {
            redirect('webshop/index');
            return;
        }

        $c->data['postdata'] = (!empty($_SESSION['postdata'])) ? $_SESSION['postdata'] : null;
        $c->data['state_list'] = $c->webshop_model->get_state();
        $c->data['country'] = $c->webshop_model->getCountry();
        $c->data['customer_id'] = null;
        $c->data['addresses'] = array();

        // Resolve the logged-in customer id robustly. CI's session can store
        // the `webshop` key as either object or array; the old direct
        // ($c->session->webshop->user_id) access silently returned null when
        // it came back as an array, causing the checkout to render manual-entry
        // mode for users who actually had saved addresses.
        $customer_id = $this->_session_customer_id($c);
        if ($customer_id > 0) {
            $c->data['customer_id'] = $customer_id;
            $addresses = $c->webshop_model->get_customer_address($customer_id);
            $c->data['addresses'] = is_array($addresses) ? $addresses : array();
            if (empty($c->data['addresses'])) {
                // Surface in logs so we can tell "no addresses" from "lookup failed".
                log_message('info', 'Webshop_checkout: no saved addresses for customer ' . $customer_id);
            }
        }

        // Shipping fee + free-shipping threshold.
        // Resolution order: API webshop_settings → local config/webshop_shipping.php → 0.
        // The local file (application/config/webshop_shipping.php) is the easy knob
        // for stores that don't yet expose these values from the ElintOm API.
        $local_shipping = $this->_load_local_shipping_config($c);
        $c->data['shipping_charges'] = $this->_resolve_shipping_setting(
            $c,
            array(
                'shipping_charges', 'shipping_charge', 'shipping_cost', 'shipping_amount',
                'delivery_charges', 'delivery_charge', 'delivery_fee',
            ),
            isset($local_shipping['flat_fee']) ? (float) $local_shipping['flat_fee'] : 0.0
        );
        $c->data['free_shipping_above'] = $this->_resolve_shipping_setting(
            $c,
            array(
                'free_shipping_above', 'free_shipping_threshold', 'free_shipping_min',
                'free_delivery_above', 'free_delivery_threshold',
            ),
            isset($local_shipping['free_above']) ? (float) $local_shipping['free_above'] : 0.0
        );

        $theme = $c->webshop_settings->webshop_theme;

        if ($theme === 'restaurant') {
            $c->data['areacharges'] = $c->webshop_model->getAreaCharges();
            if ($c->input->get('guest') == '1') {
                unset($_SESSION['customer_register']);
            }
            $c->data['website_setting'] = $c->webshop_model->get_website_setting();
            $setting_map = array();
            $raw_settings = $c->data['website_setting'];
            if (is_array($raw_settings)) {
                foreach ($raw_settings as $row) {
                    if (is_object($row) && isset($row->fields)) {
                        $setting_map[$row->fields] = isset($row->value) ? $row->value : '';
                    } elseif (is_array($row) && isset($row['fields'])) {
                        $setting_map[$row['fields']] = isset($row['value']) ? $row['value'] : '';
                    }
                }
            }
            $c->data['setting_map'] = $setting_map;
        }

        // Resolved via auto-component fallback in resolve_webshop_view_path → components/checkout.
        $c->load_view('checkout', $c->data);
    }

    /**
     * Read the first non-empty numeric value across the given keys from
     * $c->webshop_settings, $c->Settings, then $c->pos_settings (if set).
     * Falls back to $local_default when no API value is available.
     */
    private function _resolve_shipping_setting($c, array $keys, $local_default = 0.0) {
        $blocks = array();
        if (isset($c->webshop_settings)) $blocks[] = $c->webshop_settings;
        if (isset($c->Settings))         $blocks[] = $c->Settings;
        if (isset($c->pos_settings))     $blocks[] = $c->pos_settings;

        foreach ($blocks as $block) {
            foreach ($keys as $k) {
                $val = null;
                if (is_object($block) && isset($block->$k))         $val = $block->$k;
                elseif (is_array($block) && isset($block[$k]))       $val = $block[$k];
                if ($val !== null && $val !== '' && is_numeric($val)) {
                    return (float) $val;
                }
            }
        }
        return (float) $local_default;
    }

    /**
     * Resolve the logged-in webshop customer id, accepting either object or
     * array session shapes. Mirrors Webshop::_get_webshop_session_user_id().
     */
    private function _session_customer_id($c) {
        $ws = $c->session->userdata('webshop');
        if (!$ws) {
            return 0;
        }
        if (is_object($ws)) {
            return isset($ws->user_id) ? (int) $ws->user_id : 0;
        }
        if (is_array($ws)) {
            return isset($ws['user_id']) ? (int) $ws['user_id'] : 0;
        }
        return 0;
    }

    /**
     * Load the local shipping fallback (application/config/webshop_shipping.php).
     * Returns an empty array silently if the file isn't present.
     *
     * CI3 signature: load($file, $use_sections=false, $fail_gracefully=false).
     * The config file declares `$config['webshop_shipping'] = array(...)`, so
     * we keep $use_sections = FALSE and read the array via config->item().
     */
    private function _load_local_shipping_config($c) {
        if (!file_exists(APPPATH . 'config/webshop_shipping.php')) {
            return array();
        }
        $c->config->load('webshop_shipping', FALSE, TRUE);
        $loaded = $c->config->item('webshop_shipping');
        return is_array($loaded) ? $loaded : array();
    }
}
