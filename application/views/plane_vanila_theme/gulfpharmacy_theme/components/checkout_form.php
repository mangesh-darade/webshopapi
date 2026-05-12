<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$customer_id = isset($customer_id) ? $customer_id : null;
$addresses   = isset($addresses)   && is_array($addresses)   ? $addresses   : array();
$state_list  = isset($state_list)  && is_array($state_list)  ? $state_list  : array();
$country     = isset($country)     && is_array($country)     ? $country     : array();
$cart_items  = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array();
$symbol      = isset($Settings->symbol) ? $Settings->symbol : '';

// Cart stores 'product_price' as the unit price; 'price' is the raw eshop_price.
$subtotal = 0;
foreach ($cart_items as $ci) {
    $ci_up     = isset($ci['product_price']) ? (float) $ci['product_price']
               : (isset($ci['price']) ? (float) $ci['price'] : 0.0);
    $subtotal += $ci_up * (float) (isset($ci['quantity']) ? $ci['quantity'] : 1);
}
?>
<div class="checkout-container">
    <form id="checkoutForm" action="<?= base_url('webshop/submit_order') ?>" method="post">

        <!-- Required controller fields -->
        <input type="hidden" name="submit_order"                       value="<?= md5(date('Y-m-d H')) ?>">
        <input type="hidden" name="cart_subtotal_amt"                  value="<?= number_format($subtotal, 4, '.', '') ?>">
        <input type="hidden" name="billing_and_shipping_address_is_same" value="1">

        <!-- Cart item arrays — required by submit_order() -->
        <?php foreach ($cart_items as $ci):
            $ci_id    = isset($ci['product_id'])      ? (int)   $ci['product_id']                  : 0;
            $ci_qty   = isset($ci['quantity'])        ? (float) $ci['quantity']                    : 1;
            // Action engine stores price as 'product_price'; fall back to 'price' then 0.
            $ci_price = isset($ci['product_price'])   ? (float) $ci['product_price']
                      : (isset($ci['price'])          ? (float) $ci['price']                       : 0.0);
            $ci_tax   = isset($ci['tax_rate'])        ? (float) $ci['tax_rate']                    : 0;
            $ci_taxm  = isset($ci['tax_method'])      ? (int)   $ci['tax_method']                  : 0;
            $ci_promo = isset($ci['promotion_price']) ? (float) $ci['promotion_price']             : 0;
        ?>
        <input type="hidden" name="item_id[]"              value="<?= $ci_id ?>">
        <input type="hidden" name="option_id[]"            value="0">
        <input type="hidden" name="option_price[]"         value="0">
        <input type="hidden" name="item_quantity[]"        value="<?= $ci_qty ?>">
        <input type="hidden" name="item_unit_quantity[]"   value="1">
        <input type="hidden" name="item_unit_price[]"      value="<?= $ci_price ?>">
        <input type="hidden" name="item_tax_rate[]"        value="<?= $ci_tax ?>">
        <input type="hidden" name="item_tax_method[]"      value="<?= $ci_taxm ?>">
        <input type="hidden" name="item_promotion_price[]" value="<?= $ci_promo ?>">
        <input type="hidden" name="item_product_price[]"   value="<?= $ci_price ?>">
        <?php endforeach; ?>

        <div class="checkout-grid">
            <!-- ── Left: Billing / Shipping ── -->
            <div class="checkout-form-section">

                <?php if ($customer_id && !empty($addresses)): ?>
                    <h3 class="section-title">Select Shipping Address</h3>
                    <div class="address-selector">
                        <?php foreach ($addresses as $addr_id => $addr):
                            $is_default = !empty($addr['is_default']);
                        ?>
                            <label class="address-card">
                                <input type="radio" name="default_shipping_address" value="<?= $addr_id ?>" <?= $is_default ? 'checked' : '' ?>>
                                <span class="address-details">
                                    <strong><?= html_escape(isset($addr['address_name']) ? $addr['address_name'] : '') ?></strong><br>
                                    <?= html_escape(isset($addr['line1']) ? $addr['line1'] : '') ?>
                                    <?php if (!empty($addr['line2'])): ?>, <?= html_escape($addr['line2']) ?><?php endif; ?><br>
                                    <?= html_escape(isset($addr['city']) ? $addr['city'] : '') ?>,
                                    <?= html_escape(isset($addr['state']) ? $addr['state'] : '') ?> -
                                    <?= html_escape(isset($addr['postal_code']) ? $addr['postal_code'] : '') ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="customer_id" value="<?= (int) $customer_id ?>">

                <?php else: ?>
                    <h3 class="section-title">Billing Details</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="billing_first_name" required placeholder="John">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="billing_last_name" required placeholder="Doe">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="billing_email" required placeholder="john@example.com">
                    </div>

                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="billing_phone" required placeholder="+91 9876543210">
                    </div>

                    <div class="form-group">
                        <label>Address Line 1 *</label>
                        <input type="text" name="billing_address_1" required placeholder="House No, Street name">
                    </div>

                    <div class="form-group">
                        <label>Address Line 2</label>
                        <input type="text" name="billing_address_2" placeholder="Apartment, suite, etc.">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Town / City *</label>
                            <input type="text" name="billing_city" required>
                        </div>
                        <div class="form-group">
                            <label>Postcode / ZIP *</label>
                            <input type="text" name="billing_postcode" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>State *</label>
                            <select name="billing_state" id="billing_state" required>
                                <option value="">Select State</option>
                                <?php foreach ($state_list as $st):
                                    $st_arr  = is_object($st) ? (array) $st : $st;
                                    $st_name = isset($st_arr['name']) ? $st_arr['name'] : '';
                                    $st_code = isset($st_arr['code']) ? $st_arr['code'] : '';
                                ?>
                                    <option value="<?= html_escape($st_name . '~' . $st_code) ?>"><?= html_escape($st_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Country *</label>
                            <select name="billing_country" id="billing_country" required>
                                <option value="">Select Country</option>
                                <?php foreach ($country as $c):
                                    $c_name = is_object($c) ? $c->name : (isset($c['name']) ? $c['name'] : '');
                                    $c_code = is_object($c) ? $c->code : (isset($c['code']) ? $c['code'] : '');
                                ?>
                                    <option value="<?= html_escape($c_name . '~' . $c_code) ?>" <?= ($c_code === 'IN') ? 'selected' : '' ?>><?= html_escape($c_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="shipping_country" id="shipping_country" value="">
                <?php endif; ?>

                <div class="form-group terms-group">
                    <label class="checkbox-container" for="checkoutTerms">
                        <input type="checkbox" name="terms" id="checkoutTerms" required>
                        <span class="checkbox-text">I agree to the <a href="#">terms and conditions</a> *</span>
                    </label>
                </div>

            </div><!-- /.checkout-form-section -->

            <!-- ── Right: Order Summary + Payment ── -->
            <div class="order-summary-section">
                <div class="summary-card">
                    <h3 class="section-title">Order Summary</h3>

                    <div class="cart-items-summary">
                        <?php foreach ($cart_items as $ci):
                            $ci_name  = isset($ci['name'])         ? $ci['name']
                                      : (isset($ci['product_name']) ? $ci['product_name']
                                      : ('Product #' . (isset($ci['product_id']) ? $ci['product_id'] : '?')));
                            $ci_qty   = isset($ci['quantity'])     ? (float) $ci['quantity']     : 1;
                            $ci_price = isset($ci['product_price']) ? (float) $ci['product_price']
                                      : (isset($ci['price'])        ? (float) $ci['price']       : 0.0);
                        ?>
                            <div class="summary-item">
                                <span class="item-name"><?= html_escape($ci_name) ?> &times; <?= (int) $ci_qty ?></span>
                                <span class="item-price"><?= $symbol ?> <?= number_format($ci_price * $ci_qty, 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-totals">
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span><?= $symbol ?> <?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total</span>
                            <span><?= $symbol ?> <?= number_format($subtotal, 2) ?></span>
                        </div>
                    </div>

                    <!-- Payment method — inside the form so it's submitted -->
                    <div class="payment-methods">
                        <h4 class="mini-title">Payment Method</h4>
                        <div class="payment-option">
                            <input type="radio" name="payment_method" value="cod" id="payment_cod" checked>
                            <label for="payment_cod">Cash on Delivery</label>
                        </div>
                        <div class="payment-option">
                            <input type="radio" name="payment_method" value="online" id="payment_online">
                            <label for="payment_online">Online Payment</label>
                        </div>
                    </div>

                    <button type="submit" class="place-order-btn" id="placeOrder">Place Order</button>
                </div>
            </div><!-- /.order-summary-section -->
        </div><!-- /.checkout-grid -->

    </form>
</div><!-- /.checkout-container -->

<style>
    :root {
        --co-primary: #0F4C81;
        --co-accent: #00A884;
        --co-bg: #ffffff;
        --co-text: #1f2937;
        --co-muted: #6b7280;
        --co-border: #e2e8f0;
    }
    .checkout-container { font-family: 'Inter', system-ui, sans-serif; color: var(--co-text); max-width: 1200px; margin: 40px auto; padding: 0 20px; }
    .checkout-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px; }
    @media (max-width: 992px) { .checkout-grid { grid-template-columns: 1fr; } }
    .section-title { font-size: 1.3rem; font-weight: 700; margin: 0 0 20px; padding-bottom: 10px; border-bottom: 2px solid var(--co-border); color: var(--co-primary); }
    .form-row { display: flex; gap: 16px; flex-wrap: wrap; }
    .form-group { margin-bottom: 18px; flex: 1; min-width: 180px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.875rem; color: var(--co-text); }
    /* Terms row: label is not a field caption — reset block label rules and use flex row. */
    .terms-group > label.checkbox-container {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 0;
        font-weight: 500;
        font-size: 0.9375rem;
        line-height: 1.5;
        cursor: pointer;
        width: 100%;
        max-width: 100%;
        padding: 14px 16px;
        border: 1px solid var(--co-border);
        border-radius: 10px;
        background: #f8fafc;
        box-sizing: border-box;
    }
    .terms-group > label.checkbox-container:focus-within {
        border-color: var(--co-primary);
        box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.08);
    }
    /* Scope text-field styling so checkbox/radio inputs keep their native widget. */
    .form-group input:not([type="checkbox"]):not([type="radio"]),
    .form-group select { width: 100%; padding: 10px 14px; border: 1.5px solid var(--co-border); border-radius: 8px; font-size: 0.95rem; transition: border-color .2s; box-sizing: border-box; }
    .form-group input:not([type="checkbox"]):not([type="radio"]):focus,
    .form-group select:focus { outline: none; border-color: var(--co-primary); box-shadow: 0 0 0 3px rgba(15,76,129,.08); }
    .form-group input[type="checkbox"],
    .form-group input[type="radio"] { width: 18px; height: 18px; margin: 0; padding: 0; flex-shrink: 0; accent-color: var(--co-primary); cursor: pointer; }
    .address-selector { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-bottom: 16px; }
    .address-card { border: 2px solid var(--co-border); border-radius: 12px; padding: 14px; cursor: pointer; display: flex; gap: 12px; align-items: flex-start; transition: border-color .2s; }
    .address-card:has(input:checked) { border-color: var(--co-primary); background: rgba(15,76,129,.04); }
    .address-card input { margin-top: 3px; accent-color: var(--co-primary); }
    .address-details { font-size: 14px; line-height: 1.6; }
    .summary-card { background: #fff; border: 1px solid var(--co-border); border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(0,0,0,.06); position: sticky; top: 20px; }
    .summary-item { display: flex; justify-content: space-between; font-size: 14px; padding: 7px 0; border-bottom: 1px dashed #f1f5f9; }
    .summary-item:last-child { border-bottom: 0; }
    .summary-totals { margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--co-border); }
    .total-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 6px; }
    .grand-total { font-size: 17px; font-weight: 800; color: var(--co-primary); margin-top: 10px; }
    .payment-methods { margin-top: 24px; border-top: 1px solid var(--co-border); padding-top: 18px; }
    .mini-title { font-weight: 700; font-size: 15px; margin: 0 0 12px; }
    .payment-option { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 14px; }
    .payment-option input { accent-color: var(--co-primary); }
    .place-order-btn { width: 100%; margin-top: 20px; background: var(--co-primary); color: #fff; border: none; padding: 14px; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .2s; }
    .place-order-btn:hover { background: #0c3d69; }
    .terms-group {
        margin-top: 28px;
        margin-bottom: 0;
        padding-top: 20px;
        border-top: 1px solid var(--co-border);
        flex: 1 1 100%;
        min-width: 100%;
    }
    .terms-group .checkbox-container { margin-bottom: 0; }
    .terms-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin: 2px 0 0 0;
        flex-shrink: 0;
        align-self: flex-start;
    }
    .terms-group > label.checkbox-container .checkbox-text { flex: 1; min-width: 0; padding-top: 1px; }
    .terms-group > label.checkbox-container a { color: var(--co-primary); text-decoration: underline; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var billingCountry = document.getElementById('billing_country');
    var shippingCountry = document.getElementById('shipping_country');
    if (billingCountry && shippingCountry) {
        shippingCountry.value = billingCountry.value;
        billingCountry.addEventListener('change', function () {
            shippingCountry.value = billingCountry.value;
        });
    }
});
</script>
