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
        $api_product = $this->resolve_product_pricing($product_id, $variant_id);
        if (is_array($api_product) && !empty($api_product)) {
            $resolved_line = function_exists('webshop_resolve_variant_line_price')
                ? webshop_resolve_variant_line_price(
                    $api_product,
                    $variant_id,
                    $variant_id > 0 ? $variant_price : null,
                    $product_unit_price,
                    $price
                )
                : array('unit_price' => 0.0, 'variant_price' => 0.0, 'promo_price' => 0.0);
            $api_price = isset($resolved_line['unit_price']) ? (float) $resolved_line['unit_price'] : 0.0;
            if ($api_price <= 0) {
                $api_price = function_exists('webshop_checkout_resolve_product_price')
                    ? (float) webshop_checkout_resolve_product_price($api_product, $product_unit_price, $price)
                    : (isset($api_product['price']) ? (float) $api_product['price'] : 0.0);
            }
            if ($variant_id > 0 && isset($resolved_line['variant_price'])) {
                $variant_price = (float) $resolved_line['variant_price'];
            }
            $api_tax_rate = isset($api_product['tax_rate']) ? (float) $api_product['tax_rate'] : 0.0;
            $api_tax_method = isset($api_product['tax_method']) ? (int) $api_product['tax_method'] : 0;
            $api_promo = isset($resolved_line['promo_price']) && (float) $resolved_line['promo_price'] > 0
                ? (float) $resolved_line['promo_price']
                : (isset($api_product['promo_price']) ? (float) $api_product['promo_price'] : 0.0);

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

        $catalog_max_qty = null;
        if (is_array($api_product) && array_key_exists('quantity', $api_product)) {
            $catalog_max_qty = max(0.0, (float) $api_product['quantity']);
        }

        $item_key = ((int) $variant_id > 0) ? ($product_id . '_' . $variant_id) : (string) $product_id;
        $this->ensure_cart_session();

        if ($catalog_max_qty !== null) {
            if ($catalog_max_qty <= 0) {
                return array(
                    'status'  => 'FAIL',
                    'error'   => 'out_of_stock',
                    'message' => 'This product is out of stock.',
                );
            }
            $already = isset($_SESSION['cart'][$item_key]) ? (float) $_SESSION['cart'][$item_key]['quantity'] : 0.0;
            if ($already + (float) $quantity > $catalog_max_qty) {
                return array(
                    'status'  => 'FAIL',
                    'error'   => 'insufficient_stock',
                    'message' => 'The requested quantity is not available.',
                );
            }
        }

        $product_name = '';
        if (is_array($api_product) && !empty($api_product)) {
            foreach (array('name', 'product_name', 'title') as $nk) {
                if (!empty($api_product[$nk])) {
                    $product_name = trim((string) $api_product[$nk]);
                    break;
                }
            }
        }

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
            if ($product_name !== '') {
                $_SESSION['cart'][$item_key]['product_name'] = $product_name;
            }
            if ($variant_id > 0 && function_exists('webshop_cart_line_variant_label')) {
                $vlabel = webshop_cart_line_variant_label(
                    $_SESSION['cart'][$item_key],
                    is_array($api_product) ? $api_product : array()
                );
                if ($vlabel !== '') {
                    $_SESSION['cart'][$item_key]['variant_name'] = $vlabel;
                }
            }
        } else {
            $line = array(
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
                'product_name' => $product_name,
            );
            if ($variant_id > 0 && function_exists('webshop_cart_line_variant_label')) {
                $vlabel = webshop_cart_line_variant_label($line, is_array($api_product) ? $api_product : array());
                if ($vlabel !== '') {
                    $line['variant_name'] = $vlabel;
                }
            }
            $_SESSION['cart'][$item_key] = $line;
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
     * Buy now: replace cart with a single line, then checkout.
     *
     * @param array $postData
     * @return array
     */
    public function buy_now($postData)
    {
        $this->ensure_cart_session();
        $_SESSION['cart'] = array();
        return $this->add_to_cart($postData);
    }

    /**
     * Resolve authoritative product pricing (and sellable quantity) from the API.
     *
     * @param  int $product_id
     * @param  int $variant_id   product option id when line is a variant SKU
     * @return array Empty array on failure.
     */
    protected function resolve_product_pricing($product_id, $variant_id = 0)
    {
        $pid = (int) $product_id;
        if ($pid < 1) {
            return array();
        }
        if (!isset($this->CI->webshop_model) || !is_object($this->CI->webshop_model)) {
            return array();
        }
        $out = array();
        try {
            if (method_exists($this->CI->webshop_model, 'resolve_product_row_by_id')) {
                $resolved = $this->CI->webshop_model->resolve_product_row_by_id($pid);
                if (is_array($resolved) && !empty($resolved)) {
                    $out = $resolved;
                }
            }
            if ($out === array()) {
                $row = $this->CI->webshop_model->get_product_by_id(
                    $pid,
                    'id,price,eshop_price,tax_rate,tax_method,promo_price,promotion,start_date,end_date,quantity'
                );
                if (is_array($row) && isset($row[$pid]) && is_array($row[$pid]) && !empty($row[$pid])) {
                    $out = $row[$pid];
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Webshop_action_engine::resolve_product_pricing — ' . $e->getMessage());
            return array();
        } catch (\Throwable $e) {
            log_message('error', 'Webshop_action_engine::resolve_product_pricing — ' . $e->getMessage());
            return array();
        }
        if ($out === array()) {
            return array();
        }
        $vid = (int) $variant_id;
        if ($vid > 0) {
            $has_variants = false;
            foreach (array('variants', 'product_variants', 'options', 'product_options') as $vk) {
                if (!empty($out[$vk]) && is_array($out[$vk])) {
                    $has_variants = true;
                    break;
                }
            }
            if (!$has_variants && method_exists($this->CI->webshop_model, 'resolve_product_row_by_id')) {
                $full = $this->CI->webshop_model->resolve_product_row_by_id($pid);
                if (is_array($full) && !empty($full)) {
                    $out = array_merge($out, $full);
                }
            }
        }
        if ($vid > 0 && isset($out['variant_stock']) && is_array($out['variant_stock']) && !empty($out['variant_stock'])) {
            if (array_key_exists($vid, $out['variant_stock'])) {
                $out['quantity'] = (float) $out['variant_stock'][$vid];
            } else {
                $out['quantity'] = 0.0;
            }
        }
        if (isset($out['variant_stock'])) {
            unset($out['variant_stock']);
        }
        if ($vid > 0 && function_exists('webshop_resolve_variant_line_price')) {
            $line = webshop_resolve_variant_line_price($out, $vid, null, 0, 0);
            if (!empty($line['unit_price']) && (float) $line['unit_price'] > 0) {
                $out['price'] = (float) $line['unit_price'];
            }
            if (isset($line['variant_price'])) {
                $out['resolved_variant_price'] = (float) $line['variant_price'];
            }
        }
        return $out;
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

        $pid = (int) (isset($_SESSION['cart'][$item_key]['product_id']) ? $_SESSION['cart'][$item_key]['product_id'] : 0);
        $vid = (int) (isset($_SESSION['cart'][$item_key]['variant_id']) ? $_SESSION['cart'][$item_key]['variant_id'] : 0);
        if ($pid > 0) {
            $resolved = $this->resolve_product_pricing($pid, $vid);
            if (is_array($resolved) && array_key_exists('quantity', $resolved)) {
                $max = max(0.0, (float) $resolved['quantity']);
                if ($max <= 0 || (float) $itemQty > $max) {
                    return array('status' => 'FAIL');
                }
            }
        }

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
        $existing_rows = method_exists($model, 'get_wishlist') ? $model->get_wishlist($uid) : array();
        $norm = function_exists('webshop_wishlist_normalize_rows')
            ? webshop_wishlist_normalize_rows($existing_rows)
            : array('lines' => array(), 'lookup' => array(), 'count' => 0, 'duplicates' => array());

        if ($op === 'add' && function_exists('webshop_wishlist_product_is_saved')) {
            if (webshop_wishlist_product_is_saved($norm['lookup'], $product_id, $option_id)) {
                return array(
                    'status'             => 'SUCCESS',
                    'count'              => (int) $norm['count'],
                    'already_in_wishlist' => true,
                );
            }
        }

        if ($op === 'remove') {
            $ok = $model->remove_from_wishlist($uid, $product_id, $option_id ?: null);
            if (!$ok && function_exists('webshop_wishlist_product_is_saved')
                && webshop_wishlist_product_is_saved($norm['lookup'], $product_id, 0)) {
                $ok = $model->remove_from_wishlist($uid, $product_id, null);
            }
        } else {
            $ok = $model->add_to_wishlist($uid, $product_id, $option_id ?: null);
        }

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

    /**
     * Re-check session cart lines against live catalogue quantities before checkout.
     *
     * @return array{ok:bool,message:string}
     */
    public function validate_session_cart_stock()
    {
        $this->ensure_cart_session();
        foreach ($_SESSION['cart'] as $item_key => $line) {
            if (!is_array($line)) {
                continue;
            }
            $pid = (int) (isset($line['product_id']) ? $line['product_id'] : 0);
            if ($pid < 1) {
                continue;
            }
            $vid = (int) (isset($line['variant_id']) ? $line['variant_id'] : 0);
            $want = isset($line['quantity']) ? (float) $line['quantity'] : 1.0;
            $resolved = $this->resolve_product_pricing($pid, $vid);
            if (!is_array($resolved) || !array_key_exists('quantity', $resolved)) {
                continue;
            }
            $max = max(0.0, (float) $resolved['quantity']);
            if ($max <= 0 || $want > $max) {
                return array(
                    'ok'      => false,
                    'message' => 'Your cart contains an item that is out of stock or no longer available in the requested quantity. Please return to the cart and update it before checkout.',
                );
            }
        }
        return array('ok' => true, 'message' => '');
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
