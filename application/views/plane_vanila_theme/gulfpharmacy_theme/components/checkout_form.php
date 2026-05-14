<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Gulf Pharmacy — checkout form component.
 *
 * Address handling supports two modes:
 *
 *   1. Saved-address mode — logged-in customer with ≥ 1 saved address.
 *      - Billing picker (radio list) → posts `billing_address_id`.
 *      - "Same as billing" checkbox (default ON). When OFF, the shipping
 *        picker is revealed and posts `shipping_address_id`.
 *      - + Manage addresses link → /webshop/your_account#addresses.
 *
 *   2. Manual-entry mode — guest, OR logged-in customer with 0 saved addresses.
 *      - Full billing form (with postcode, state, country).
 *      - "Same as billing" toggle revealing a parallel shipping form.
 *
 * Server-side, Webshop::submit_order() already understands
 * `billing_address_id`, `shipping_address_id` and
 * `billing_and_shipping_address_is_same` — no controller change required.
 */

$customer_id = isset($customer_id) ? (int) $customer_id : 0;
$addresses_raw = isset($addresses) && is_array($addresses) ? $addresses : array();
$state_list  = isset($state_list)  && is_array($state_list)  ? $state_list  : array();
$country     = isset($country)     && is_array($country)     ? $country     : array();
$cart_items  = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : array();
$symbol      = isset($Settings->symbol) ? $Settings->symbol : '';

// Product rows keyed by id — same source as cart / header mini-cart (`get_cart_data()`).
$__checkout_products = array();
if (isset($cart_data) && is_array($cart_data) && isset($cart_data['products']) && is_array($cart_data['products'])) {
    $__checkout_products = $cart_data['products'];
}

// Shipping configuration resolved by Webshop_checkout::present().
// $shipping_charges      = flat fee charged when no free-shipping rule applies.
// $free_shipping_above   = subtotal threshold (>=) at which shipping becomes free; 0 disables.
$shipping_charges_default = isset($shipping_charges)    ? (float) $shipping_charges    : 0.0;
$free_shipping_above      = isset($free_shipping_above) ? (float) $free_shipping_above : 0.0;

// Normalise the saved-address list to a [int id => array] map and find default.
// API can return rows as either associative arrays or stdClass — accept both.
$saved_addresses = array();
$default_addr_id = 0;
foreach ($addresses_raw as $key => $addr) {
    if (is_object($addr)) {
        $addr = (array) $addr;
    }
    if (!is_array($addr)) {
        continue;
    }
    $aid = (is_int($key) || (is_string($key) && ctype_digit((string) $key)))
        ? (int) $key
        : (int) (isset($addr['id']) ? $addr['id'] : 0);
    if ($aid < 1) {
        continue;
    }
    $saved_addresses[$aid] = $addr;
    if ($default_addr_id < 1 && !empty($addr['is_default'])) {
        $default_addr_id = $aid;
    }
}
if ($default_addr_id < 1 && !empty($saved_addresses)) {
    reset($saved_addresses);
    $default_addr_id = (int) key($saved_addresses);
}
$use_saved = ($customer_id > 0 && !empty($saved_addresses));
$manage_addresses_url = base_url('webshop/your_account#addresses');

/* -----------------------------------------------------------------------
 * Default-address pre-fill for the manual form.
 *
 * If a saved default address exists we use its values as defaults for the
 * Billing form inputs. This helps two cases:
 *   1. A logged-in user with 0 saved addresses → $default_addr stays empty,
 *      fields render blank.
 *   2. A logged-in user whose saved-address picker can't render (e.g. an
 *      upstream issue clears $saved_addresses for this request) → we still
 *      have something useful in the form.
 *
 * Helpers below split address_name into first/last and pick `selected`
 * options for the State / Country selects.
 * ----------------------------------------------------------------------- */
$default_addr = ($default_addr_id > 0 && isset($saved_addresses[$default_addr_id]))
    ? $saved_addresses[$default_addr_id]
    : array();

$prefill_full_name = isset($default_addr['address_name']) ? trim((string) $default_addr['address_name']) : '';
$prefill_first = '';
$prefill_last  = '';
if ($prefill_full_name !== '') {
    $name_parts = preg_split('/\s+/', $prefill_full_name, 2);
    $prefill_first = isset($name_parts[0]) ? $name_parts[0] : '';
    $prefill_last  = isset($name_parts[1]) ? $name_parts[1] : '';
}
$prefill_email    = isset($default_addr['email_id'])    ? (string) $default_addr['email_id']    : '';
$prefill_phone    = isset($default_addr['phone'])       ? (string) $default_addr['phone']       : '';
$prefill_line1    = isset($default_addr['line1'])       ? (string) $default_addr['line1']       : '';
$prefill_line2    = isset($default_addr['line2'])       ? (string) $default_addr['line2']       : '';
$prefill_city     = isset($default_addr['city'])        ? (string) $default_addr['city']        : '';
$prefill_postcode = isset($default_addr['postal_code']) ? (string) $default_addr['postal_code'] : '';
$prefill_state    = isset($default_addr['state'])       ? (string) $default_addr['state']       : '';
$prefill_state_cd = isset($default_addr['state_code'])  ? (string) $default_addr['state_code']  : '';
$prefill_country  = isset($default_addr['country'])     ? (string) $default_addr['country']     : '';

// Cart stores 'product_price' as the unit price; 'price' is the raw eshop_price.
$subtotal = 0;
foreach ($cart_items as $ci) {
    $ci_up    = isset($ci['product_price']) ? (float) $ci['product_price']
              : (isset($ci['price']) ? (float) $ci['price'] : 0.0);
    $subtotal += $ci_up * (float) (isset($ci['quantity']) ? $ci['quantity'] : 1);
}

// Compute effective shipping at render time.
// JS recomputes the same way when the cart or coupon changes (see checkout-form.js).
$shipping_effective = $shipping_charges_default;
if ($free_shipping_above > 0 && $subtotal >= $free_shipping_above) {
    $shipping_effective = 0.0;
}

$co_assets = isset($assets) ? $assets : base_url('assets/webshop/');
?>
<link rel="stylesheet" href="<?= $co_assets ?>gulfpharmacy_theme/css/checkout-form.css">
<div class="checkout-container">
    <form id="checkoutForm"
          action="<?= base_url('webshop/submit_order') ?>"
          method="post"
          novalidate
          data-action-url="<?= base_url('webshop/webshop_request') ?>">

        <!-- Required controller fields -->
        <input type="hidden" name="submit_order" value="<?= md5(date('Y-m-d H')) ?>">
        <input type="hidden" name="cart_subtotal_amt" id="cart_subtotal_amt" value="<?= number_format($subtotal, 4, '.', '') ?>">
        <input type="hidden" name="cart_total" id="cart_total" value="<?= number_format($subtotal + $shipping_effective, 4, '.', '') ?>">
        <input type="hidden" name="billing_and_shipping_address_is_same" id="billing_and_shipping_address_is_same" value="1">
        <?php if ($customer_id > 0): ?>
            <input type="hidden" name="customer_id" value="<?= (int) $customer_id ?>">
        <?php endif; ?>

        <!-- Shipping charges (read on submit_order, displayed in summary).
             Default + free-threshold come from webshop_settings via Webshop_checkout. -->
        <input type="hidden" name="shipping_charges"     id="shipping_charges"     value="<?= number_format($shipping_effective, 4, '.', '') ?>">
        <input type="hidden" name="shipping_charges_base" id="shipping_charges_base" value="<?= number_format($shipping_charges_default, 4, '.', '') ?>">
        <input type="hidden" name="free_shipping_above"  id="free_shipping_above"  value="<?= number_format($free_shipping_above, 4, '.', '') ?>">

        <!-- Coupon fields (controller reads coupon_code + coupon_discount_amount) -->
        <input type="hidden" name="coupon_code"             id="coupon_code"             value="">
        <input type="hidden" name="coupon_code_id"          id="coupon_code_id"          value="">
        <input type="hidden" name="coupon_code_value"       id="coupon_code_value"       value="">
        <input type="hidden" name="coupon_discount_rate"    id="coupon_discount_rate"    value="">
        <input type="hidden" name="coupon_discount_amount"  id="coupon_discount_amount"  value="">

        <!-- Cart item arrays — required by submit_order() -->
        <?php foreach ($cart_items as $ci):
            $ci_id    = isset($ci['product_id'])      ? (int)   $ci['product_id']        : 0;
            $ci_qty   = isset($ci['quantity'])        ? (float) $ci['quantity']          : 1;
            $ci_price = isset($ci['product_price'])   ? (float) $ci['product_price']
                      : (isset($ci['price'])          ? (float) $ci['price']             : 0.0);
            $ci_tax   = isset($ci['tax_rate'])        ? (float) $ci['tax_rate']          : 0;
            $ci_taxm  = isset($ci['tax_method'])      ? (int)   $ci['tax_method']        : 0;
            $ci_promo = isset($ci['promotion_price']) ? (float) $ci['promotion_price']   : 0;
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

                <?php if ($use_saved): ?>
                <!-- ===== Saved-address mode ===== -->
                <div class="address-block" data-mode="saved">
                    <div class="section-head">
                        <h3 class="section-title">Billing address</h3>
                        <a class="section-link" href="<?= htmlspecialchars($manage_addresses_url, ENT_QUOTES, 'UTF-8') ?>">+ Manage addresses</a>
                    </div>
                    <p class="section-hint">Invoice and receipt will be sent to this address.</p>
                    <div class="address-selector" role="radiogroup" aria-label="Billing address">
                        <?php foreach ($saved_addresses as $aid => $addr):
                            $nm = isset($addr['address_name']) ? $addr['address_name'] : '';
                            $l1 = isset($addr['line1']) ? $addr['line1'] : '';
                            $l2 = isset($addr['line2']) ? $addr['line2'] : '';
                            $ct = isset($addr['city']) ? $addr['city'] : '';
                            $st = isset($addr['state']) ? $addr['state'] : '';
                            $pc = isset($addr['postal_code']) ? $addr['postal_code'] : '';
                            $ph = isset($addr['phone']) ? $addr['phone'] : '';
                            $is_def = !empty($addr['is_default']);
                            $checked = ($aid === $default_addr_id) ? ' checked' : '';
                        ?>
                        <label class="address-card">
                            <input type="radio" name="billing_address_id" value="<?= (int) $aid ?>"<?= $checked ?> required>
                            <span class="address-details">
                                <strong><?= html_escape($nm !== '' ? $nm : 'Saved address') ?></strong>
                                <?php if ($is_def): ?><span class="address-default">Default</span><?php endif; ?>
                                <br>
                                <?= html_escape($l1) ?><?php if ($l2 !== ''): ?>, <?= html_escape($l2) ?><?php endif; ?><br>
                                <?= html_escape(trim($ct . ($st !== '' ? ', ' . $st : ''))) ?>
                                <?php if ($pc !== ''): ?> &mdash; <?= html_escape($pc) ?><?php endif; ?>
                                <?php if ($ph !== ''): ?><br><span class="address-meta"><?= html_escape($ph) ?></span><?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <label class="bill-ship-toggle">
                    <input type="checkbox" name="billing_and_shipping" id="billtocopy" value="1" checked>
                    <span>Deliver to the same address as billing</span>
                </label>

                <div id="usershipping" class="shipping-section" hidden>
                    <div class="address-block" data-mode="saved">
                        <div class="section-head">
                            <h3 class="section-title">Shipping address</h3>
                            <a class="section-link" href="<?= htmlspecialchars($manage_addresses_url, ENT_QUOTES, 'UTF-8') ?>">+ Manage addresses</a>
                        </div>
                        <p class="section-hint">Where should we deliver this order?</p>
                        <div class="address-selector" role="radiogroup" aria-label="Shipping address">
                            <?php foreach ($saved_addresses as $aid => $addr):
                                $nm = isset($addr['address_name']) ? $addr['address_name'] : '';
                                $l1 = isset($addr['line1']) ? $addr['line1'] : '';
                                $l2 = isset($addr['line2']) ? $addr['line2'] : '';
                                $ct = isset($addr['city']) ? $addr['city'] : '';
                                $st = isset($addr['state']) ? $addr['state'] : '';
                                $pc = isset($addr['postal_code']) ? $addr['postal_code'] : '';
                                $ph = isset($addr['phone']) ? $addr['phone'] : '';
                                $is_def = !empty($addr['is_default']);
                                $checked = ($aid === $default_addr_id) ? ' checked' : '';
                            ?>
                            <label class="address-card">
                                <input type="radio" name="shipping_address_id" value="<?= (int) $aid ?>"<?= $checked ?> disabled>
                                <span class="address-details">
                                    <strong><?= html_escape($nm !== '' ? $nm : 'Saved address') ?></strong>
                                    <?php if ($is_def): ?><span class="address-default">Default</span><?php endif; ?>
                                    <br>
                                    <?= html_escape($l1) ?><?php if ($l2 !== ''): ?>, <?= html_escape($l2) ?><?php endif; ?><br>
                                    <?= html_escape(trim($ct . ($st !== '' ? ', ' . $st : ''))) ?>
                                    <?php if ($pc !== ''): ?> &mdash; <?= html_escape($pc) ?><?php endif; ?>
                                    <?php if ($ph !== ''): ?><br><span class="address-meta"><?= html_escape($ph) ?></span><?php endif; ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- ===== Manual-entry mode ===== -->
                <div class="address-block" data-mode="manual">
                    <div class="section-head">
                        <h3 class="section-title">Billing details</h3>
                        <?php if ($customer_id > 0): ?>
                            <a class="section-link" href="<?= htmlspecialchars($manage_addresses_url, ENT_QUOTES, 'UTF-8') ?>">+ Manage addresses</a>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="billing_first_name">First name *</label>
                            <input type="text" id="billing_first_name" name="billing_first_name" required placeholder="John" value="<?= html_escape($prefill_first) ?>">
                        </div>
                        <div class="form-group">
                            <label for="billing_last_name">Last name *</label>
                            <input type="text" id="billing_last_name" name="billing_last_name" required placeholder="Doe" value="<?= html_escape($prefill_last) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="billing_email">Email *</label>
                            <input type="email" id="billing_email" name="billing_email" required placeholder="john@example.com" value="<?= html_escape($prefill_email) ?>">
                        </div>
                        <div class="form-group">
                            <label for="billing_phone">Phone *</label>
                            <input type="tel" id="billing_phone" name="billing_phone" required placeholder="+91 9876543210" value="<?= html_escape($prefill_phone) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="billing_address_1">Address line 1 *</label>
                        <input type="text" id="billing_address_1" name="billing_address_1" required placeholder="House no, street name" value="<?= html_escape($prefill_line1) ?>">
                    </div>
                    <div class="form-group">
                        <label for="billing_address_2">Apt / Suite</label>
                        <input type="text" id="billing_address_2" name="billing_address_2" placeholder="Apartment, suite, etc." value="<?= html_escape($prefill_line2) ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="billing_city">City *</label>
                            <input type="text" id="billing_city" name="billing_city" required value="<?= html_escape($prefill_city) ?>">
                        </div>
                        <div class="form-group">
                            <label for="billing_postcode">Postcode / ZIP *</label>
                            <input type="text" id="billing_postcode" name="billing_postcode" required value="<?= html_escape($prefill_postcode) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="billing_state">State *</label>
                            <select name="billing_state" id="billing_state" required>
                                <option value="">Select state</option>
                                <?php foreach ($state_list as $st):
                                    $st_arr  = is_object($st) ? (array) $st : $st;
                                    $st_name = isset($st_arr['name']) ? $st_arr['name'] : '';
                                    $st_code = isset($st_arr['code']) ? $st_arr['code'] : '';
                                    // Mark selected when either the saved state name OR its code matches.
                                    $is_sel_state = ($prefill_state !== '' && strcasecmp($st_name, $prefill_state) === 0)
                                                  || ($prefill_state_cd !== '' && strcasecmp($st_code, $prefill_state_cd) === 0);
                                ?>
                                <option value="<?= html_escape($st_name . '~' . $st_code) ?>" <?= $is_sel_state ? 'selected' : '' ?>><?= html_escape($st_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="billing_country">Country *</label>
                            <select name="billing_country" id="billing_country" required>
                                <option value="">Select country</option>
                                <?php
                                $has_prefill_country = ($prefill_country !== '');
                                foreach ($country as $c):
                                    $c_name = is_object($c) ? $c->name : (isset($c['name']) ? $c['name'] : '');
                                    $c_code = is_object($c) ? $c->code : (isset($c['code']) ? $c['code'] : '');
                                    if ($has_prefill_country) {
                                        $is_sel_country = (strcasecmp($c_code, $prefill_country) === 0)
                                                       || (strcasecmp($c_name, $prefill_country) === 0);
                                    } else {
                                        $is_sel_country = ($c_code === 'IN');
                                    }
                                ?>
                                <option value="<?= html_escape($c_name . '~' . $c_code) ?>" <?= $is_sel_country ? 'selected' : '' ?>><?= html_escape($c_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <label class="bill-ship-toggle">
                    <input type="checkbox" name="billing_and_shipping" id="billtocopy" value="1" checked>
                    <span>Shipping address same as billing address</span>
                </label>

                <div id="usershipping" class="shipping-section" hidden>
                    <div class="address-block" data-mode="manual">
                        <div class="section-head">
                            <h3 class="section-title">Shipping details</h3>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_first_name">First name *</label>
                                <input type="text" id="shipping_first_name" name="shipping_first_name" maxlength="30">
                            </div>
                            <div class="form-group">
                                <label for="shipping_last_name">Last name *</label>
                                <input type="text" id="shipping_last_name" name="shipping_last_name" maxlength="30">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_email">Email *</label>
                                <input type="email" id="shipping_email" name="shipping_email">
                            </div>
                            <div class="form-group">
                                <label for="shipping_phone">Phone *</label>
                                <input type="tel" id="shipping_phone" name="shipping_phone">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="shipping_address_1">Address line 1 *</label>
                            <input type="text" id="shipping_address_1" name="shipping_address_1">
                        </div>
                        <div class="form-group">
                            <label for="shipping_address_2">Apt / Suite</label>
                            <input type="text" id="shipping_address_2" name="shipping_address_2">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_city">City *</label>
                                <input type="text" id="shipping_city" name="shipping_city">
                            </div>
                            <div class="form-group">
                                <label for="shipping_postcode">Postcode / ZIP *</label>
                                <input type="text" id="shipping_postcode" name="shipping_postcode">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_state">State *</label>
                                <select name="shipping_state" id="shipping_state">
                                    <option value="">Select state</option>
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
                                <label for="shipping_country">Country *</label>
                                <select name="shipping_country" id="shipping_country">
                                    <option value="">Select country</option>
                                    <?php foreach ($country as $c):
                                        $c_name = is_object($c) ? $c->name : (isset($c['name']) ? $c['name'] : '');
                                        $c_code = is_object($c) ? $c->code : (isset($c['code']) ? $c['code'] : '');
                                    ?>
                                    <option value="<?= html_escape($c_name . '~' . $c_code) ?>"><?= html_escape($c_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── Apply coupon ── -->
                <div class="coupon-card" id="couponCard">
                    <div class="section-head">
                        <h3 class="section-title">Have a coupon?</h3>
                    </div>
                    <div class="coupon-input-row">
                        <input type="text"
                               id="couponInput"
                               class="coupon-input"
                               placeholder="Enter coupon code"
                               autocomplete="off"
                               maxlength="40">
                        <button type="button" id="couponApply" class="coupon-btn">Apply</button>
                    </div>
                    <p class="coupon-msg" id="couponMsg" role="status" aria-live="polite"></p>

                    <div class="coupon-applied" id="couponApplied" hidden>
                        <div class="coupon-applied-meta">
                            <span class="coupon-tag" id="couponTag">CODE</span>
                            <span class="coupon-applied-text">Coupon applied &mdash; you saved <span id="couponSavedAmt"></span></span>
                        </div>
                        <button type="button" id="couponRemove" class="coupon-remove">Remove</button>
                    </div>
                </div>

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
                    <h3 class="section-title">Order summary</h3>

                    <div class="cart-items-summary">
                        <?php foreach ($cart_items as $ci):
                            $ci_pid = isset($ci['product_id']) ? (int) $ci['product_id'] : 0;
                            $ci_name = '';
                            if (!empty($ci['name'])) {
                                $ci_name = trim((string) $ci['name']);
                            }
                            if ($ci_name === '' && !empty($ci['product_name'])) {
                                $ci_name = trim((string) $ci['product_name']);
                            }
                            if ($ci_name === '' && $ci_pid > 0) {
                                $prow = null;
                                if (isset($__checkout_products[$ci_pid])) {
                                    $prow = $__checkout_products[$ci_pid];
                                } elseif (isset($__checkout_products[(string) $ci_pid])) {
                                    $prow = $__checkout_products[(string) $ci_pid];
                                }
                                if (is_array($prow)) {
                                    if (!empty($prow['name'])) {
                                        $ci_name = trim((string) $prow['name']);
                                    } elseif (!empty($prow['product_name'])) {
                                        $ci_name = trim((string) $prow['product_name']);
                                    } elseif (!empty($prow['code'])) {
                                        $ci_name = trim((string) $prow['code']);
                                    }
                                } elseif (is_object($prow)) {
                                    if (isset($prow->name) && (string) $prow->name !== '') {
                                        $ci_name = trim((string) $prow->name);
                                    } elseif (isset($prow->product_name) && (string) $prow->product_name !== '') {
                                        $ci_name = trim((string) $prow->product_name);
                                    } elseif (isset($prow->code) && (string) $prow->code !== '') {
                                        $ci_name = trim((string) $prow->code);
                                    }
                                }
                            }
                            if ($ci_name === '') {
                                $ci_name = $ci_pid > 0 ? ('Product #' . $ci_pid) : 'Product';
                            }
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

                    <div class="summary-totals"
                         id="summaryTotals"
                         data-symbol="<?= html_escape($symbol) ?>"
                         data-subtotal="<?= number_format($subtotal, 4, '.', '') ?>">
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span id="sumSubtotal"><?= $symbol ?> <?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="total-row" id="sumShippingRow">
                            <span>Shipping</span>
                            <?php if ($shipping_effective > 0): ?>
                                <span id="sumShipping"><?= $symbol ?> <?= number_format($shipping_effective, 2) ?></span>
                            <?php else: ?>
                                <span id="sumShipping" class="shipping-free">Free</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($free_shipping_above > 0 && $shipping_effective > 0): ?>
                            <p class="shipping-hint" id="shippingHint">Free shipping on orders over <?= $symbol ?> <?= number_format($free_shipping_above, 2) ?>.</p>
                        <?php endif; ?>
                        <div class="total-row total-discount" id="sumDiscountRow" hidden>
                            <span>Discount</span>
                            <span id="sumDiscount">&minus; <?= $symbol ?> 0.00</span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total</span>
                            <span id="sumTotal"><?= $symbol ?> <?= number_format($subtotal + $shipping_effective, 2) ?></span>
                        </div>
                    </div>

                    <!-- Payment method — inside the form so it's submitted -->
                    <div class="payment-methods">
                        <h4 class="mini-title">Payment method</h4>
                        <div class="payment-option">
                            <input type="radio" name="payment_method" value="cod" id="payment_cod" checked>
                            <label for="payment_cod">Cash on delivery</label>
                        </div>
                        <div class="payment-option">
                            <input type="radio" name="payment_method" value="online" id="payment_online">
                            <label for="payment_online">Online payment</label>
                        </div>
                    </div>

                    <button type="submit" class="place-order-btn" id="placeOrder">Place order</button>
                </div>
            </div><!-- /.order-summary-section -->
        </div><!-- /.checkout-grid -->

    </form>
</div><!-- /.checkout-container -->
<script defer src="<?= $co_assets ?>gulfpharmacy_theme/js/checkout-form.js"></script>
