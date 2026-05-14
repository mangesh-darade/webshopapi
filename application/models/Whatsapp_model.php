<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp_model extends CI_Model {

    // Cheerio API config
    private $cheerio_templete_url = 'https://pre-prod.cheerio.in/direct-apis/v1/whatsapp/template/send';
    private $cheerio_direct_url = 'https://pre-prod.cheerio.in/direct-apis/v1/whatsapp/direct/send';

    /**
     * Cheerio x-api-key from sma_settings.whatsapp_api_key (Settings or site model).
     */
    private function cheerio_api_key() {
        $ci = get_instance();
        if (isset($ci->Settings) && is_object($ci->Settings) && !empty($ci->Settings->whatsapp_api_key)) {
            return trim((string) $ci->Settings->whatsapp_api_key);
        }
        if (isset($ci->site)) {
            $s = $ci->site->get_setting();
            if ($s && !empty($s->whatsapp_api_key)) {
                return trim((string) $s->whatsapp_api_key);
            }
        }
        return '';
    }

    public function send_cheerio_templete($phone, $template_name, $params = [], $order_id = null, $type = null) {
        // Format template parameters
        $body_parameters = [];
        foreach ($params as $text) {
            $body_parameters[] = [
                "type" => "text",
                "text" => $text
            ];
        }

        // Final payload
        $payload = [
            "to" => $phone, // Dynamic phone number
            "data" => [
                "name" => $template_name, // Dynamic template name
                "language" => [
                    "code" => "en"
                ],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => $body_parameters // Dynamic parameters
                    ]
                ]
            ]
        ];
     
        return $this->_make_curl_request($type, $payload,$order_id);
    }

    public function get_order_and_items($order_id) {
        // Get order data first
        $this->db->select('*');
        $this->db->from('sma_orders');
        $this->db->where('id', $order_id);
        $order = $this->db->get()->row();

        if (!$order) {
            return null; // Or handle error as you want
        }

        // Get all items for this order
        $this->db->select('*');
        $this->db->from('sma_order_items');
        $this->db->where('sale_id', $order_id);
        $items = $this->db->get()->result();

        return [
            'order' => $order,
            'items' => $items
        ];
    }
    public function get_full_address($address_id) {
        $this->db->where('id', $address_id);
        $address = $this->db->get('sma_addresses')->row();

        if (!$address) {
            return null;
        }

        $parts = [
            $address->address_name,
            $address->line1,
            $address->line2,
            $address->city,
            $address->postal_code,
            $address->state,
            $address->country,
        ];

        // Use classic anonymous function for array_filter in PHP 5.6
        $parts = array_filter($parts, function($val) {
            return !empty($val);
        });

        $full_address = implode(', ', $parts);

        return $full_address;
    }
    public function send_order_whatsapp_message($phone,$order_id,$customer_order_msg_response_flag) {
        $phone = str_replace("+", "", $phone);
        $data = $this->get_order_and_items($order_id);
        if (!$data) {
            return ['status' => 'error', 'message' => 'Order not found'];
        }
       
        $order = $data['order'];
        $this->create_customer($order,$phone);
        $full_address = $this->get_full_address($order->shipping_address_id);

        $items = $data['items'];
        $items_list = [];
        foreach ($items as $item) {
            $items_list[] = $item->product_name . ($item->quantity > 1 ? "({$item->quantity})" : "");
        }
        $order_id = md5($order->id);
        $delivery_type = ucfirst(strtolower((string) (isset($order->delivery_type) ? $order->delivery_type : '')));
        $sale_status = ucfirst(strtolower((string) (isset($order->sale_status) ? $order->sale_status : '')));
         
        if ($customer_order_msg_response_flag == 'YES') {
             $params = [
                implode(', ', $items_list), // Items with quantity
                $this->sma->formatMoney($order->total), // Total
                ucfirst($order->payment_status), // Payment Status
                $full_address, // Full address (concatenated)
				base_url("reciept/invoice_reciept/" . $order_id), // Receipt URL
                base_url("webshop/track_order/" . $order_id), // Receipt URL
            ];
             $phone = $phone;
             $template_name = 'order_summary_yess';
             return $this->send_cheerio_direct_massage($phone, $template_name, $params,$order_id);

        }
        if ($customer_order_msg_response_flag == 'NO') {
            $params = [
                base_url("reciept/invoice_reciept/" . $order_id), // Receipt URL
                base_url("webshop/track_order/" . $order_id), // Receipt URL
            ];
             $phone = $phone;
             $template_name = 'order_summary_no';
             return $this->send_cheerio_direct_massage($phone, $template_name, $params,$order_id);

        }
        if ($delivery_type == 'Pickup' && $customer_order_msg_response_flag == 'Ready') {
            $params = [
               
            ];
             $phone = $phone;
             $template_name = 'pickup_ready_msg';
             return $this->send_cheerio_direct_massage($phone, $template_name, $params,$order_id);

        }
        if ($delivery_type == 'Door Delivery' && $customer_order_msg_response_flag == 'Ready') {
            $params = [
               
            ];
             $phone = $phone;
             $template_name = 'delivery_ready_msg';
             return $this->send_cheerio_direct_massage($phone, $template_name, $params,$order_id);

        }
        if ($customer_order_msg_response_flag == 'true') {   
            $params = [
                $order->customer, // Customer Name
                $order->invoice_no, // Order Number
                date("d-m-Y H:i", strtotime($order->date)), // Order Date
            ];
            $phone = $phone;
            $template_name = 'elintom_webshop_order_received_msg_option';
            return $this->send_cheerio_templete($phone, $template_name, $params,$order_id,'templete');
        }else {
            return true;
        }
       
    }
    // Method to get the template name based on order status
    private function get_template_for_status($status) {
        switch ($status) {
            case 'Received':
                return 'elintom_webshop_order_received_msg';
            case 'In Progress':
                return 'order_in_progress_template';
            case 'Ready':
                return 'order_ready_template';
            case 'Dispatch':
                return 'order_dispatched_template';
            default:
                return 'order_generic_template'; // Default template for unknown statuses
        }
    }
    /**
     * Generic cURL POST request to Cheerio API
     */
    private function _make_curl_request($type, $payload,$order_id) {
        $api_key = $this->cheerio_api_key();
        if ($api_key === '') {
            log_message('error', 'Whatsapp_model: whatsapp_api_key is empty; Cheerio request skipped.');
            return [
                'status' => 'error',
                'message' => 'WhatsApp API key not configured',
                'http_code' => 0,
                'order_id' => $order_id,
            ];
        }
        if ($type == 'templete') {
            $url = $this->cheerio_templete_url;
        } else {
            $url = $this->cheerio_direct_url;
        }
        $url =  $url;
		 
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: '. $api_key
            //'Authorization: Bearer ' . $this->cheerio_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($error) {
            return [
                'status' => 'error',
                'message' => $error,
                'order_id' => $order_id
            ];
        }

        return [
            'status' => ($http_code >= 200 && $http_code < 300) ? 'success' : 'error',
            'http_code' => $http_code,
            'response' => json_decode($response, true)
        ];
    }
    public function create_customer($order,$phone){
        if ($this->cheerio_api_key() === '') {
            return false;
        }
        $apiUrl = 'https://pre-prod.cheerio.in/direct-apis/v1/contacts/uploadSingleContact';
        $postData = array(
            'name' => $order->customer,
            'mobile' => $phone,
            'email' => '',
            'customData' => array(
                'order_id' => $order->id,
            ),
            'labels' => array('testaabhas')
        );

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'x-api-key: ' . $this->cheerio_api_key()
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            log_message('error', 'cURL error: ' . $error);
            return false;
        }
        if ($httpcode == 200 || $httpcode == 201) {
            return true;
        } else {
            log_message('error', "API Error (Status $httpcode): $response");
            return false;
        }
    }
    public function send_cheerio_direct_massage($phone, $template_name, $params = [], $order_id = null) {
        // Format template parameters
        $body_parameters = [];
        foreach ($params as $text) {
            $body_parameters[] = [
                "type" => "text",
                "text" => $text
            ];
        }

       $payload = [
			"to" => $phone,
			"type" => "template",
			"template" => [
				"name" => $template_name,
				"language" => [
					"code" => "en"
				],
				"components" => [
					[
						"type" => "body",
						"parameters" => $body_parameters
					]
				]
			]
		];
     
        // Call API
        return $this->_make_curl_request('send-whatsapp', $payload,$order_id);
    }
    public function send_otp_by_whatsappsss($phone, $otp) {
        $phone = str_replace("+", "", $phone);
        if ($phone && $otp) {
            $message = "Your OTP for login is: {{1}}. Please use this within 10 minutes. Do not share it with anyone.";
            return $this->send_cheerio_direct_whatapp_message($phone, $message);
        }
        return [
            'status' => 'error',
            'message' => 'Invalid phone or OTP.'
        ];
    }
    public function send_cheerio_direct_whatapp_message($phone, $message) {
        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $phone,
            "type" => "text",
            "text" => [
                "body" => $message
            ]
        ];
        return $this->_make_curl_request('direct', $payload, null);
    }
    /////////////////////////////// Create This method for templete massage ///////////////////////////////
     public function send_otp_by_whatsapp($phone, $otp) {
    $phone = str_replace("+", "", $phone);

   if ($phone && $otp) {
        $template_name = "order_status"; // Replace with your actual template name
        $otp = 'this otp: '.$otp; 
        $second_param = "5 minutes."; 
        $params = [$otp, $second_param]; // Send two parameters
        $type = "templete";

        return $this->send_cheerio_templete($phone, $template_name, $params, $order_id = null, $type);
    }

    return [
        'status' => 'error',
        'message' => 'Invalid phone or OTP.'
    ];
}



}
