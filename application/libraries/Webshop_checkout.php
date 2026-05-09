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
        if (isset($c->session->webshop) && $c->session->webshop->user_id) {
            $customer_id = (int) $c->session->webshop->user_id;
            $c->data['customer_id'] = $customer_id;
            $c->data['addresses'] = $c->webshop_model->get_customer_address($customer_id);
        }

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
}
