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
        if ($product_id <= 0) {
            $product_id = $this->post_int($postData, 'item_id');
        }

        $variant_id = $this->post_int($postData, 'variant_id');
        if ($variant_id <= 0) {
            $variant_id = $this->post_int($postData, 'option_id');
        }
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

        // Authoritative pricing: never trust client-posted price values. Some
        // entry points (category listing, wishlist) only post the product id,
        // which would otherwise store product_price=0 and break the cart total.
        // Always resolve the current price from the API so the cart, checkout
        // and payment screens stay consistent.
        $api_product = $this->resolve_product_pricing($product_id);
        if (is_array($api_product) && !empty($api_product)) {
            $api_price = isset($api_product['price']) ? (float) $api_product['price'] : 0.0;
            $api_tax_rate = isset($api_product['tax_rate']) ? (float) $api_product['tax_rate'] : 0.0;
            $api_tax_method = isset($api_product['tax_method']) ? (int) $api_product['tax_method'] : 0;
            $api_promo = isset($api_product['promo_price']) ? (float) $api_product['promo_price'] : 0.0;

            if ($api_price > 0) {
                $price = $api_price;
                $product_unit_price = $api_price;
            }
            if ($api_tax_rate > 0 && $tax_rate <= 0) {
                $tax_rate = $api_tax_rate;
            }
            if ($api_tax_method > 0 && $tax_method <= 0) {
                $tax_method = $api_tax_method;
            }
            if ($api_promo > 0 && $promotion_price <= 0) {
                $promotion_price = $api_promo;
            }
        }

        $item_key = ((int) $variant_id > 0) ? ($product_id . '_' . $variant_id) : (string) $product_id;
        $this->ensure_cart_session();

        if (isset($_SESSION['cart'][$item_key])) {
            $_SESSION['cart'][$item_key]['quantity'] += $quantity;
            // Refresh price on existing row so a stale 0-price entry from an
            // older add_to_cart call is corrected the next time the buyer
            // adds the same product.
            if ($price > 0) {
                $_SESSION['cart'][$item_key]['product_price'] = $product_unit_price;
                $_SESSION['cart'][$item_key]['price'] = $price;
            }
            if ($tax_rate > 0) {
                $_SESSION['cart'][$item_key]['tax_rate'] = $tax_rate;
            }
            if ($tax_method > 0) {
                $_SESSION['cart'][$item_key]['tax_method'] = $tax_method;
            }
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

    /**
     * Resolve authoritative product pricing from the API (or local DB fallback).
     * Returned shape mirrors {@see Webshop_api_model::_flatten_product_for_order()}.
     *
     * @param  int $product_id
     * @return array Empty array on failure.
     */
    protected function resolve_product_pricing($product_id)
    {
        $pid = (int) $product_id;
        if ($pid < 1) {
            return array();
        }
        if (!isset($this->CI->webshop_model) || !is_object($this->CI->webshop_model)) {
            return array();
        }
        try {
            $row = $this->CI->webshop_model->get_product_by_id(
                $pid,
                'id,price,eshop_price,tax_rate,tax_method,promo_price,promotion,start_date,end_date'
            );
        } catch (\Exception $e) {
            log_message('error', 'Webshop_action_engine::resolve_product_pricing — ' . $e->getMessage());
            return array();
        } catch (\Throwable $e) {
            log_message('error', 'Webshop_action_engine::resolve_product_pricing — ' . $e->getMessage());
            return array();
        }
        if (!is_array($row) || !isset($row[$pid]) || !is_array($row[$pid]) || empty($row[$pid])) {
            return array();
        }
        return $row[$pid];
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
        return $this->wishlist_mutate($postData, $user_id, 'add');
    }

    public function remove_from_wishlist($postData, $user_id)
    {
        return $this->wishlist_mutate($postData, $user_id, 'remove');
    }

    /**
     * Shared body for add/remove wishlist actions.
     *
     * Calls the API model with the scalar signature
     * (user_id, product_id, option_id) — NOT the single-array legacy DB-model
     * signature. The previous version passed array('product_id'=>..,'user_id'=>..)
     * which PHP bound to $user_id, leaving $product_id/$option_id as NULL on
     * the API side: the call always failed silently and the engine still
     * returned SUCCESS, so the heart toggle "worked" visually but nothing
     * was ever persisted in sma_eshop_wishlist.
     *
     * @param array  $postData
     * @param int    $user_id
     * @param string $op  'add' | 'remove'
     * @return array
     */
    protected function wishlist_mutate($postData, $user_id, $op)
    {
        $product_id = $this->post_int($postData, 'product_id');
        if ($product_id <= 0) {
            $product_id = $this->post_int($postData, 'item_id');
        }

        $option_id = $this->post_int($postData, 'variant_id');
        if ($option_id <= 0) {
            $option_id = $this->post_int($postData, 'option_id');
        }

        if ($product_id <= 0) {
            return array(
                'status' => 'FAIL',
                'error'  => 'Invalid product',
            );
        }
        $uid = (int) $user_id;
        if ($uid <= 0) {
            // Distinct error code lets the storefront JS redirect to login
            // instead of surfacing a generic "unable to update" alert.
            return array(
                'status' => 'FAIL',
                'error'  => 'User session invalid',
                'code'   => 'NOT_LOGGED_IN',
            );
        }

        $model = $this->CI->webshop_model;
        $ok = ($op === 'remove')
            ? $model->remove_from_wishlist($uid, $product_id, $option_id ?: null)
            : $model->add_to_wishlist($uid, $product_id, $option_id ?: null);

        if (!$ok) {
            return array(
                'status' => 'FAIL',
                'error'  => ($op === 'remove')
                    ? 'Unable to remove from favourites.'
                    : 'Unable to add to favourites.',
            );
        }

        $count = 0;
        if (method_exists($model, 'get_wishlist_count')) {
            $count = (int) $model->get_wishlist_count($uid);
        }

        return array(
            'status' => 'SUCCESS',
            'count'  => $count,
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
