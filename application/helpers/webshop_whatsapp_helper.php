<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Webshop order WhatsApp message bodies (not HTTP).
 * Used by Whatsapp_model for local-DB mode; ElintOm has the same file under app/helpers/.
 * Builds order-placed plain text + template params; legacy flags YES/NO/Ready map to Cheerio template names.
 */

if (!function_exists('webshop_whatsapp_ensure_cheerio_helper')) {
    function webshop_whatsapp_ensure_cheerio_helper() {
        if (!function_exists('cheerio_whatsapp_storefront_base_url')) {
            get_instance()->load->helper('cheerio_whatsapp');
        }
    }
}

if (!function_exists('webshop_whatsapp_order_placed_template')) {
    function webshop_whatsapp_order_placed_template() {
        return 'elintom_webshop_order';
    }
}

if (!function_exists('webshop_whatsapp_order_hash')) {
    function webshop_whatsapp_order_hash($order_id) {
        return md5((int) $order_id);
    }
}

if (!function_exists('webshop_whatsapp_order_urls')) {
    /**
     * @return array{storefront: string, webshop: string, receipt: string, track: string}
     */
    function webshop_whatsapp_order_urls($order_id, $storefront_base = null) {
        webshop_whatsapp_ensure_cheerio_helper();
        $storefront = $storefront_base !== null
            ? rtrim((string) $storefront_base, '/')
            : cheerio_whatsapp_storefront_base_url();
        $webshop = rtrim($storefront, '/') . '/webshop';
        $hash = webshop_whatsapp_order_hash($order_id);
        return array(
            'storefront' => $storefront,
            'webshop' => $webshop,
            'receipt' => rtrim($storefront, '/') . '/reciept/invoice_reciept/' . $hash,
            'track' => rtrim($webshop, '/') . '/track_order/' . $hash,
        );
    }
}

if (!function_exists('webshop_whatsapp_format_address')) {
    function webshop_whatsapp_format_address($address) {
        if (!$address) {
            return null;
        }
        $parts = array_filter(array(
            isset($address->address_name) ? $address->address_name : null,
            isset($address->line1) ? $address->line1 : null,
            isset($address->line2) ? $address->line2 : null,
            isset($address->city) ? $address->city : null,
            isset($address->postal_code) ? $address->postal_code : null,
            isset($address->state) ? $address->state : null,
            isset($address->country) ? $address->country : null,
        ), function ($val) {
            return !empty($val);
        });
        return $parts ? implode(', ', $parts) : null;
    }
}

if (!function_exists('webshop_whatsapp_items_label_list')) {
    function webshop_whatsapp_items_label_list($items) {
        $list = array();
        foreach ($items as $item) {
            $row = is_object($item) ? $item : (object) $item;
            $name = isset($row->product_name) ? (string) $row->product_name : 'Item';
            $qty = isset($row->quantity) ? (float) $row->quantity : 1;
            $list[] = $name . ($qty > 1 ? '(' . (int) $qty . ')' : '');
        }
        return $list;
    }
}

if (!function_exists('webshop_whatsapp_order_display_total')) {
    function webshop_whatsapp_order_display_total($order, $items) {
        if (!function_exists('webshop_order_grand_total_amount')) {
            get_instance()->load->helper('webshop');
        }
        $order_arr = is_object($order) ? (array) $order : $order;
        $item_arrs = array();
        foreach ($items as $it) {
            $item_arrs[] = is_object($it) ? (array) $it : $it;
        }
        return webshop_order_grand_total_amount($order_arr, $item_arrs);
    }
}

if (!function_exists('webshop_whatsapp_build_order_placed_text')) {
    /**
     * @param object $order
     * @param array  $items
     * @param array  $items_list  From order_pricing_items_label_list
     * @param string $full_address
     * @param callable $format_money function($amount): string
     */
    function webshop_whatsapp_build_order_placed_text($order, $items, array $items_list, $full_address, $format_money) {
        webshop_whatsapp_ensure_cheerio_helper();
        $brand = cheerio_whatsapp_brand_name();
        $urls = webshop_whatsapp_order_urls((int) $order->id);
        $items_text = $items_list ? implode(', ', $items_list) : '-';
        $customer = !empty($order->customer) ? trim((string) $order->customer) : 'Customer';
        $total = webshop_whatsapp_order_display_total($order, $items);
        $ref = !empty($order->reference_no) ? (string) $order->reference_no : ('#' . (int) $order->id);

        return 'Hello ' . $customer . ",\n\n"
            . 'Your order at ' . $brand . " is confirmed.\n"
            . 'Order: ' . $ref . "\n\n"
            . 'Items: ' . $items_text . "\n"
            . 'Total: ' . call_user_func($format_money, $total) . "\n"
            . 'Payment: ' . ucfirst((string) $order->payment_status) . "\n"
            . 'Address: ' . $full_address . "\n\n"
            . 'Receipt: ' . $urls['receipt'] . "\n"
            . 'Track: ' . $urls['track'] . "\n\n"
            . 'Thank you.';
    }
}

if (!function_exists('webshop_whatsapp_build_order_placed_template_params')) {
    function webshop_whatsapp_build_order_placed_template_params($order, $items, array $items_list, $full_address, $format_money) {
        webshop_whatsapp_ensure_cheerio_helper();
        $brand = cheerio_whatsapp_brand_name();
        $urls = webshop_whatsapp_order_urls((int) $order->id);
        $items_text = $items_list ? implode(', ', $items_list) : '-';
        $customer = !empty($order->customer) ? trim((string) $order->customer) : 'Customer';
        $total = webshop_whatsapp_order_display_total($order, $items);

        return array(
            $items_text,
            call_user_func($format_money, $total),
            ucfirst((string) $order->payment_status),
            $full_address,
            $urls['receipt'],
            $urls['track'],
            $customer,
            $brand,
            cheerio_whatsapp_support_phone(),
            $urls['storefront'],
        );
    }
}

if (!function_exists('webshop_whatsapp_legacy_template_for_flag')) {
    /**
     * Legacy YES/NO/Ready flows (ElintOm webshop + urbanpiper).
     *
     * @return array|null  ['template' => string, 'params' => array] or null if not handled
     */
    function webshop_whatsapp_legacy_template_for_flag($flag, $order, array $items_list, $full_address, $format_money) {
        $numeric_order_id = (int) $order->id;
        $order_hash = webshop_whatsapp_order_hash($numeric_order_id);
        $delivery_type = ucfirst(strtolower((string) $order->delivery_type));

        if ($flag === 'YES') {
            return array(
                'template' => 'order_summary_yess',
                'params' => array(
                    implode(', ', $items_list),
                    call_user_func($format_money, $order->total),
                    ucfirst($order->payment_status),
                    $full_address,
                    base_url('reciept/invoice_reciept/' . $order_hash),
                    base_url('webshop/track_order/' . $order_hash),
                ),
            );
        }
        if ($flag === 'NO') {
            return array(
                'template' => 'order_summary_no',
                'params' => array(
                    base_url('reciept/invoice_reciept/' . $order_hash),
                    base_url('webshop/track_order/' . $order_hash),
                ),
            );
        }
        if ($delivery_type === 'Pickup' && $flag === 'Ready') {
            return array('template' => 'pickup_ready_msg', 'params' => array());
        }
        if ($delivery_type === 'Door Delivery' && $flag === 'Ready') {
            return array('template' => 'delivery_ready_msg', 'params' => array());
        }
        return null;
    }
}
