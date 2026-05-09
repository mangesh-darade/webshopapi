<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reusable backend actions for webshop CTA buttons.
 *
 * Centralizes cart + wishlist behavior used by:
 * - Add to Cart
 * - Buy Now
 * - Favourite/Wishlist
 */
class Webshop_action_engine
{
    /** @var CI_Controller */
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    public function add_to_cart($postData)
    {
        $product_id = $this->post_int($postData, 'product_id');
        $variant_id = $this->post_int($postData, 'variant_id');
        $variant_price = $this->post_float($postData, 'variant_price');
        $unit_quantity = max(1, $this->post_int($postData, 'variant_unit_quantity', 1));
        $product_unit_price = $this->post_float($postData, 'product_price');
        $quantity = max(1, $this->post_int($postData, 'quantity', 1));
        $tax_rate = $this->post_float($postData, 'tax_rate');
        $tax_method = $this->post_int($postData, 'tax_method');
        $price = $this->post_float($postData, 'price');
        $promotion_price = $this->post_float($postData, 'promotion_price');

        if ($product_id <= 0) {
            return array(
                'status' => 'FAIL',
                'error' => 'Invalid product',
            );
        }

        $item_key = ((int) $variant_id > 0) ? ($product_id . '_' . $variant_id) : (string) $product_id;
        $this->ensure_cart_session();

        if (isset($_SESSION['cart'][$item_key])) {
            $_SESSION['cart'][$item_key]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$item_key] = array(
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'variant_price' => $variant_price,
                'unit_quantity' => $unit_quantity,
                'product_price' => $product_unit_price,
                'quantity' => $quantity,
                'tax_rate' => $tax_rate,
                'tax_method' => $tax_method,
                'price' => $price,
                'promotion_price' => $promotion_price,
            );
        }

        $totals = $this->cart_totals_from_session();
        return array(
            'status' => 'SUCCESS',
            // Legacy keys retained for existing JS compatibility.
            'cart_count' => $totals['count'],
            'cart_items' => $totals['count'],
            'cart_total' => $totals['total'],
        );
    }

    public function update_cart($postData)
    {
        $item_key = $this->post_string($postData, 'itemKey');
        $itemQty = max(1, $this->post_int($postData, 'itemQty', 1));

        $this->ensure_cart_session();
        if ($item_key === '' || !isset($_SESSION['cart'][$item_key])) {
            return array('status' => 'FAIL');
        }

        $_SESSION['cart'][$item_key]['quantity'] = $itemQty;
        return array('status' => 'SUCCESS');
    }

    public function add_to_wishlist($postData, $user_id)
    {
        $product_id = $this->post_int($postData, 'product_id');
        $option_id = $this->post_int($postData, 'variant_id');
        if ($product_id <= 0) {
            return array(
                'status' => 'FAIL',
                'error' => 'Invalid product',
            );
        }
        if ((int) $user_id <= 0) {
            return array(
                'status' => 'FAIL',
                'error' => 'User session invalid',
            );
        }

        $wishlist = $this->CI->webshop_model->add_to_wishlist(array(
            'product_id' => $product_id,
            'option_id' => $option_id,
            'user_id' => (int) $user_id,
        ));

        return array(
            'status' => 'SUCCESS',
            'count' => is_array($wishlist) ? count($wishlist) : 0,
            'items' => $wishlist,
        );
    }

    public function remove_from_wishlist($postData, $user_id)
    {
        $product_id = $this->post_int($postData, 'product_id');
        $option_id = $this->post_int($postData, 'variant_id');
        if ($product_id <= 0) {
            return array(
                'status' => 'FAIL',
                'error' => 'Invalid product',
            );
        }
        if ((int) $user_id <= 0) {
            return array(
                'status' => 'FAIL',
                'error' => 'User session invalid',
            );
        }

        $wishlist = $this->CI->webshop_model->remove_from_wishlist(array(
            'product_id' => $product_id,
            'option_id' => $option_id,
            'user_id' => (int) $user_id,
        ));

        return array(
            'status' => 'SUCCESS',
            'count' => is_array($wishlist) ? count($wishlist) : 0,
            'items' => $wishlist,
        );
    }

    protected function post_int($data, $key, $default = 0)
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return (int) $default;
        }
        return (int) $data[$key];
    }

    protected function post_float($data, $key, $default = 0.0)
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return (float) $default;
        }
        return (float) $data[$key];
    }

    protected function post_string($data, $key, $default = '')
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return (string) $default;
        }
        return trim((string) $data[$key]);
    }

    protected function ensure_cart_session()
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = array();
        }
    }

    protected function cart_totals_from_session()
    {
        $this->ensure_cart_session();
        $subtotal = 0.0;
        foreach ($_SESSION['cart'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $subtotal += (float) (isset($item['product_price']) ? $item['product_price'] : 0) * (float) (isset($item['quantity']) ? $item['quantity'] : 0);
        }
        return array(
            'count' => count($_SESSION['cart']),
            'total' => number_format($subtotal, 2),
        );
    }
}
