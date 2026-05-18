<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WhatsApp via Cheerio — LOCAL DB FALLBACK ONLY (sma_orders / sma_addresses).
 *
 * Do not use when uses_elintom_api_for_orders() is true; callers should use
 * Webshop_api_model::notify_order_placed_whatsapp_remote() instead (phone + key live in ElintOm).
 *
 * flag 'true'  → send_webshop_order_placed_whatsapp() (direct text, then elintom_webshop_order template)
 * flag YES/NO/Ready → legacy Cheerio templates (order_summary_*, pickup_ready_msg, etc.)
 */
class Whatsapp_model extends CI_Model {

    private $runtime_api_key = null;

    public function __construct() {
        parent::__construct();
        $this->load->helper(array('cheerio_whatsapp', 'webshop_whatsapp'));
    }

    public function set_api_key($key) {
        $key = trim((string) $key);
        $this->runtime_api_key = ($key !== '') ? $key : null;
    }

    public function cheerio_delivery_ok($res) {
        return cheerio_whatsapp_delivery_ok($res);
    }

    public function send_cheerio_templete($phone, $template_name, $params = array(), $order_id = null, $type = null) {
        if ($type === 'templete') {
            return cheerio_whatsapp_send_template($phone, $template_name, $params, $order_id, $this->runtime_api_key);
        }
        return cheerio_whatsapp_send_direct_text($phone, is_array($params) ? implode(' ', $params) : '', $order_id, $this->runtime_api_key);
    }

    public function send_cheerio_direct_text($phone_digits, $message, $order_id = null) {
        return cheerio_whatsapp_send_direct_text($phone_digits, $message, $order_id, $this->runtime_api_key);
    }

    public function send_cheerio_direct_whatapp_message($phone, $message) {
        return cheerio_whatsapp_send_direct_text($phone, $message, null, $this->runtime_api_key);
    }

    public function send_cheerio_direct_massage($phone, $template_name, $params = array(), $order_id = null) {
        return cheerio_whatsapp_send_template($phone, $template_name, $params, $order_id, $this->runtime_api_key);
    }

    public function get_order_and_items($order_id) {
        $order_id = (int) $order_id;
        $order = $this->db->where('id', $order_id)->get(cheerio_whatsapp_local_table('orders'))->row();
        if (!$order) {
            return null;
        }
        $items = $this->db->where('sale_id', $order_id)->get(cheerio_whatsapp_local_table('order_items'))->result();
        return array('order' => $order, 'items' => $items);
    }

    public function get_full_address($address_id) {
        $address = $this->db->where('id', (int) $address_id)->get(cheerio_whatsapp_local_table('addresses'))->row();
        return webshop_whatsapp_format_address($address);
    }

    public function send_webshop_order_placed_whatsapp($phone, $order_id, $phone_is_resolved = true) {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone === '') {
            return array('status' => 'error', 'message' => 'Invalid phone');
        }
        if (!$phone_is_resolved) {
            $phone = cheerio_whatsapp_phone_digits($phone);
        }

        $order_id = (int) $order_id;
        $data = $this->get_order_and_items($order_id);
        if (!$data) {
            return array('status' => 'error', 'message' => 'Order not found', 'order_id' => $order_id);
        }

        $order = $data['order'];
        $this->create_customer($order, $phone);

        $addr_id = !empty($order->shipping_address_id) ? $order->shipping_address_id : $order->billing_address_id;
        $full_address = $this->get_full_address($addr_id);
        if ($full_address === null || $full_address === '') {
            $full_address = '-';
        }

        $items_list = webshop_whatsapp_items_label_list($data['items']);
        $this->load->library('sma');
        $fmt = array($this->sma, 'formatMoney');

        $message = webshop_whatsapp_build_order_placed_text($order, $data['items'], $items_list, $full_address, $fmt);
        $direct = $this->send_cheerio_direct_text($phone, $message, $order_id);
        if ($this->cheerio_delivery_ok($direct)) {
            $direct['delivery_method'] = 'direct_text';
            return $direct;
        }

        $params = webshop_whatsapp_build_order_placed_template_params($order, $data['items'], $items_list, $full_address, $fmt);
        $tpl = cheerio_whatsapp_send_template($phone, webshop_whatsapp_order_placed_template(), $params, $order_id, $this->runtime_api_key);
        if ($this->cheerio_delivery_ok($tpl)) {
            $tpl['delivery_method'] = 'template';
            return $tpl;
        }

        if (is_array($direct)) {
            $direct['delivery_method'] = 'failed';
            $direct['template_attempted'] = true;
        }
        return $direct;
    }

    public function send_order_whatsapp_message($phone, $order_id, $customer_order_msg_response_flag) {
        if ($customer_order_msg_response_flag == 'true' || $customer_order_msg_response_flag === true) {
            return $this->send_webshop_order_placed_whatsapp($phone, $order_id, true);
        }

        $phone = cheerio_whatsapp_phone_digits($phone);
        $data = $this->get_order_and_items($order_id);
        if (!$data) {
            return array('status' => 'error', 'message' => 'Order not found');
        }

        $order = $data['order'];
        $this->create_customer($order, $phone);

        $addr_id = !empty($order->shipping_address_id) ? $order->shipping_address_id : $order->billing_address_id;
        $full_address = $this->get_full_address($addr_id);
        if ($full_address === null || $full_address === '') {
            $full_address = '-';
        }

        $items_list = webshop_whatsapp_items_label_list($data['items']);
        $this->load->library('sma');
        $fmt = array($this->sma, 'formatMoney');

        $legacy = webshop_whatsapp_legacy_template_for_flag(
            (string) $customer_order_msg_response_flag,
            $order,
            $items_list,
            $full_address,
            $fmt
        );
        if ($legacy !== null) {
            return cheerio_whatsapp_send_template($phone, $legacy['template'], $legacy['params'], (int) $order->id, $this->runtime_api_key);
        }

        return true;
    }

    public function create_customer($order, $phone) {
        return cheerio_whatsapp_upload_contact($order->customer, $phone, (int) $order->id, $this->runtime_api_key);
    }

    public function send_otp_by_whatsapp($phone, $otp) {
        $phone = cheerio_whatsapp_phone_digits($phone);
        $otp = trim((string) $otp);
        if ($phone === '' || $otp === '') {
            return array('status' => 'error', 'message' => 'Invalid phone or OTP.');
        }
        return $this->send_cheerio_direct_text($phone, cheerio_whatsapp_build_otp_message($otp), null);
    }
}
