<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Gulf Pharmacy checkout page.
 *
 * Address handling supports two modes:
 *
 *   1. Saved-address mode (logged-in customer with at least one saved address):
 *      - Buyer picks a billing address from a radio list.
 *      - "Billing address is the same as shipping" checkbox (default ON).
 *      - When OFF, a shipping picker is revealed using the same saved list.
 *      - Inline "+ Manage addresses" link → /webshop/your_account#addresses.
 *
 *   2. Manual-entry mode (guest, OR logged-in customer with zero saved addresses):
 *      - Full billing form (incl. postcode + country).
 *      - "Shipping same as billing" toggle; when OFF, the shipping form
 *        becomes required via JS (checkout.js).
 *
 * The submitted form is consumed by Webshop::submit_order(), which already
 * understands `billing_address_id`, `shipping_address_id` and
 * `billing_and_shipping_address_is_same`.
 */

$shopName = isset($Settings->site_name) && $Settings->site_name !== ''
    ? $Settings->site_name
    : (isset($webshop_settings->site_name) ? $webshop_settings->site_name : 'My Shop');

$session_customer = isset($this->session->webshop) ? $this->session->webshop : null;
$customer_name = $billing_email = $billing_phone = '';
if (is_object($session_customer)) {
    $customer_name = isset($session_customer->name) ? (string) $session_customer->name : '';
    $billing_email = isset($session_customer->email) ? (string) $session_customer->email : '';
    $billing_phone = isset($session_customer->phone) ? (string) $session_customer->phone : '';
} elseif (is_array($session_customer)) {
    $customer_name = isset($session_customer['name']) ? (string) $session_customer['name'] : '';
    $billing_email = isset($session_customer['email']) ? (string) $session_customer['email'] : '';
    $billing_phone = isset($session_customer['phone']) ? (string) $session_customer['phone'] : '';
}
$nameArr = preg_split('/\s+/', trim($customer_name), -1, PREG_SPLIT_NO_EMPTY);
$billing_first_name = isset($nameArr[0]) ? $nameArr[0] : '';
$billing_last_name  = isset($nameArr[1]) ? $nameArr[1] : '';
$billing_and_shipping_address_is_same = isset($billing_and_shipping_address_is_same) ? $billing_and_shipping_address_is_same : '1';

$checkout_customer_id = isset($customer_id) ? (int) $customer_id : 0;
$checkout_addresses_raw = isset($addresses) && is_array($addresses) ? $addresses : array();

// Normalise the address list to a stable [int id => array] map and find the default.
$checkout_addresses = array();
$default_checkout_addr_id = 0;
foreach ($checkout_addresses_raw as $addr_key => $addr) {
    if (!is_array($addr)) {
        continue;
    }
    $aid = (is_int($addr_key) || (is_string($addr_key) && ctype_digit((string) $addr_key)))
        ? (int) $addr_key
        : (int) (isset($addr['id']) ? $addr['id'] : 0);
    if ($aid < 1) {
        continue;
    }
    $checkout_addresses[$aid] = $addr;
    if ($default_checkout_addr_id < 1 && !empty($addr['is_default'])) {
        $default_checkout_addr_id = $aid;
    }
}
if ($default_checkout_addr_id < 1 && !empty($checkout_addresses)) {
    // No `is_default` flag → fall back to the first saved address.
    // Use reset() instead of array_key_first() for PHP < 7.3 compatibility.
    reset($checkout_addresses);
    $default_checkout_addr_id = (int) key($checkout_addresses);
}
$use_saved_checkout_addresses = ($checkout_customer_id > 0 && !empty($checkout_addresses));
$manage_addresses_url = base_url('webshop/your_account#addresses');

/*
 * Order totals breakdown — mirrors the exact math performed by
 * Webshop::submit_order() so the "Total" shown here always equals the
 * grand_total stored on the order (and therefore the amount charged on the
 * payment screen and at the gateway).
 *
 *   tax_method == 1 (EXCLUSIVE — catalogue price is pre-tax):
 *     line_total      = qty * price        (display: pre-tax line)
 *     line_tax        = qty * price * rate / 100
 *     subtotal       += line_total
 *     tax_extra      += line_tax           (added on top of subtotal)
 *
 *   tax_method == 0 (INCLUSIVE — catalogue price already contains tax):
 *     line_total      = qty * price        (display: customer-facing line)
 *     line_tax        = qty * price * rate / (100 + rate)
 *     subtotal       += line_total
 *     tax_included   += line_tax           (informational, already in subtotal)
 *
 *   grand_total = subtotal + tax_extra + shipping - discount
 */
$subtotal       = 0.0;
$tax_included   = 0.0;
$tax_extra      = 0.0;
$tax_rates_used = array();

if (is_array($cart_items)) {
    foreach ($cart_items as $ci_row) {
        if (!is_array($ci_row)) { continue; }
        $ci_qty  = isset($ci_row['quantity']) ? (float) $ci_row['quantity'] : 0.0;
        $ci_unit = (isset($ci_row['product_price']) && (float) $ci_row['product_price'] > 0)
            ? (float) $ci_row['product_price']
            : (isset($ci_row['price']) ? (float) $ci_row['price'] : 0.0);
        $ci_rate = isset($ci_row['tax_rate']) ? (float) $ci_row['tax_rate'] : 0.0;
        $ci_meth = isset($ci_row['tax_method']) ? (int) $ci_row['tax_method'] : 0;
        $ci_line = $ci_unit * $ci_qty;
        $subtotal += $ci_line;
        if ($ci_rate > 0 && $ci_line > 0) {
            if ($ci_meth === 1) {
                $tax_extra += $ci_line * $ci_rate / 100.0;
            } else {
                $tax_included += $ci_line * $ci_rate / (100.0 + $ci_rate);
            }
            // Stable key so each distinct rate is shown once.
            $tax_rates_used[number_format($ci_rate, 4, '.', '')] = $ci_rate;
        }
    }
}

// Shipping resolution — same source Webshop_checkout exposes to the view.
$ship_amount = isset($shipping_charges) && is_numeric($shipping_charges) ? (float) $shipping_charges : 0.0;
$free_above  = isset($free_shipping_above) && is_numeric($free_shipping_above) ? (float) $free_shipping_above : 0.0;
$customer_facing_total_pre_ship = $subtotal + $tax_extra;
if ($ship_amount > 0 && $free_above > 0 && $customer_facing_total_pre_ship >= $free_above) {
    $ship_amount = 0.0;
}

$grand_total_display = $subtotal + $tax_extra + $ship_amount;

// Single-line VAT label: "VAT 5%" when one rate; "VAT" when multiple rates.
$tax_rate_label = '';
if (count($tax_rates_used) === 1) {
    $only_rate = (float) reset($tax_rates_used);
    $rate_fmt = rtrim(rtrim(number_format($only_rate, 2, '.', ''), '0'), '.');
    $tax_rate_label = $rate_fmt !== '' ? (' ' . $rate_fmt . '%') : '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Checkout | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/checkout-page.css">
</head>
<body class="pv-checkout-page">
<?php require_once(VIEWPATH . 'plane_vanila_theme/gulfpharmacy_theme/header.php'); ?>
<main class="pv-main">
    <div class="container">
        <nav class="pv-breadcrumb">
            <a href="<?= base_url('webshop') ?>">Home</a> &rsaquo;
            <a href="<?= base_url('webshop/cart') ?>">Cart</a> &rsaquo; Checkout
        </nav>
        <div class="pv-steps">
            <span class="pv-step-item pv-step-active">1. Billing &amp; Shipping</span>
            <span>&rsaquo;</span>
            <span class="pv-step-item pv-step-muted">2. Payment</span>
            <span>&rsaquo;</span>
            <span class="pv-step-item pv-step-muted">3. Confirm</span>
        </div>
        <h1 class="pv-page-title">Billing &amp; Shipping Info</h1>

        <?php if ($checkout_customer_id <= 0): ?>
        <div class="pv-guest-banner" role="status" aria-live="polite">
            <div class="pv-guest-banner-body">
                <strong>Checking out as a guest</strong>
                <span>Already have an account?
                    <a href="<?= base_url('webshop/login?return_page=webshop/checkout') ?>">Sign in</a>
                    to use saved addresses and track your order.
                </span>
            </div>
        </div>
        <?php endif; ?>

        <div class="pv-checkout-layout">
            <div>
                <form name="checkout" id="custinfoform" method="post" action="<?= base_url('webshop/submit_order') ?>" novalidate>

                    <?php if (is_array($cart_items) && count($cart_items)):
                        foreach ($cart_items as $itemKey => $item): ?>
                            <input type="hidden" name="item_id[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['product_id'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="option_id[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['variant_id'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="option_price[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['variant_price'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="item_unit_quantity[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['unit_quantity'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="item_quantity[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['quantity'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="item_unit_price[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['product_price'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="item_tax_rate[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['tax_rate'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="item_tax_method[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['tax_method'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="item_promotion_price[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['promotion_price'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="item_product_price[<?= $itemKey ?>]" value="<?= htmlspecialchars((string) $item['price'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach;
                    endif; ?>
                    <input type="hidden" id="cart_subtotal_amt" name="cart_subtotal_amt" value="<?= $subtotal ?>">
                    <input type="hidden" id="cart_total" name="cart_total" value="<?= $subtotal ?>">
                    <input type="hidden" name="submit_order" value="<?= md5(date('Y-m-d H')) ?>">
                    <input type="hidden" id="coupon_code_id" name="coupon_code_id" value="">
                    <input type="hidden" id="coupon_code_value" name="coupon_code_value" value="">
                    <input type="hidden" id="coupon_code" name="coupon_code" value="">
                    <input type="hidden" id="coupon_discount_rate" name="coupon_discount_rate" value="">
                    <input type="hidden" id="coupon_discount_amount" name="coupon_discount_amount" value="">
                    <input type="hidden" id="shipping_charges" name="shipping_charges" value="0">

                    <?php if ($checkout_customer_id > 0): ?>
                    <input type="hidden" name="customer_id" value="<?= (int) $checkout_customer_id ?>">
                    <?php endif; ?>

                    <?php if ($use_saved_checkout_addresses): ?>
                    <!-- =====================================================
                         Saved-address mode (logged-in with ≥ 1 saved address)
                         ===================================================== -->
                    <div id="pv-checkout-saved-wrap" data-checkout-address-mode="saved">
                        <div class="pv-card">
                            <div class="pv-section-head">
                                <h2 class="pv-section-title">Billing address</h2>
                                <a class="pv-link" href="<?= htmlspecialchars($manage_addresses_url, ENT_QUOTES, 'UTF-8') ?>">+ Manage addresses</a>
                            </div>
                            <p class="pv-co-hint">Invoice and receipt will be sent to this address.</p>
                            <div class="pv-co-addr-grid" role="radiogroup" aria-label="Billing address">
                                <?php foreach ($checkout_addresses as $aid => $addr):
                                    $nm = isset($addr['address_name']) ? $addr['address_name'] : '';
                                    $l1 = isset($addr['line1']) ? $addr['line1'] : '';
                                    $l2 = isset($addr['line2']) ? $addr['line2'] : '';
                                    $ct = isset($addr['city']) ? $addr['city'] : '';
                                    $st = isset($addr['state']) ? $addr['state'] : '';
                                    $pc = isset($addr['postal_code']) ? $addr['postal_code'] : '';
                                    $ph = isset($addr['phone']) ? $addr['phone'] : '';
                                    $is_def = !empty($addr['is_default']);
                                    $checked = ($aid === $default_checkout_addr_id) ? ' checked' : '';
                                ?>
                                <label class="pv-co-addr-card">
                                    <input type="radio" name="billing_address_id" value="<?= (int) $aid ?>"<?= $checked ?> required>
                                    <span class="pv-co-addr-meta">
                                        <strong><?= html_escape($nm !== '' ? $nm : 'Saved address') ?></strong>
                                        <?php if ($is_def): ?><span class="pv-co-addr-default">Default</span><?php endif; ?>
                                        <br>
                                        <?= html_escape($l1) ?><?php if ($l2 !== ''): ?>, <?= html_escape($l2) ?><?php endif; ?><br>
                                        <?= html_escape(trim($ct . ($st !== '' ? ', ' . $st : ''))) ?>
                                        <?php if ($pc !== ''): ?> &mdash; <?= html_escape($pc) ?><?php endif; ?>
                                        <?php if ($ph !== ''): ?><br><span class="pv-co-hint" style="margin:4px 0 0;display:inline-block"><?= html_escape($ph) ?></span><?php endif; ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <label class="pv-same-shipping">
                            <input type="checkbox" name="billing_and_shipping" id="billtocopy" value="1" checked>
                            <span>Deliver to the same address as billing</span>
                        </label>
                        <input type="hidden" name="billing_and_shipping_address_is_same" id="billing_and_shipping_address_is_same" value="1">

                        <div id="usershipping" style="display:none">
                            <div class="pv-card">
                                <div class="pv-section-head">
                                    <h2 class="pv-section-title">Shipping address</h2>
                                    <a class="pv-link" href="<?= htmlspecialchars($manage_addresses_url, ENT_QUOTES, 'UTF-8') ?>">+ Manage addresses</a>
                                </div>
                                <p class="pv-co-hint">Where should we deliver this order?</p>
                                <div class="pv-co-addr-grid" role="radiogroup" aria-label="Shipping address">
                                    <?php foreach ($checkout_addresses as $aid => $addr):
                                        $nm = isset($addr['address_name']) ? $addr['address_name'] : '';
                                        $l1 = isset($addr['line1']) ? $addr['line1'] : '';
                                        $l2 = isset($addr['line2']) ? $addr['line2'] : '';
                                        $ct = isset($addr['city']) ? $addr['city'] : '';
                                        $st = isset($addr['state']) ? $addr['state'] : '';
                                        $pc = isset($addr['postal_code']) ? $addr['postal_code'] : '';
                                        $ph = isset($addr['phone']) ? $addr['phone'] : '';
                                        $is_def = !empty($addr['is_default']);
                                        $checked = ($aid === $default_checkout_addr_id) ? ' checked' : '';
                                    ?>
                                    <label class="pv-co-addr-card">
                                        <input type="radio" name="shipping_address_id" value="<?= (int) $aid ?>"<?= $checked ?> disabled>
                                        <span class="pv-co-addr-meta">
                                            <strong><?= html_escape($nm !== '' ? $nm : 'Saved address') ?></strong>
                                            <?php if ($is_def): ?><span class="pv-co-addr-default">Default</span><?php endif; ?>
                                            <br>
                                            <?= html_escape($l1) ?><?php if ($l2 !== ''): ?>, <?= html_escape($l2) ?><?php endif; ?><br>
                                            <?= html_escape(trim($ct . ($st !== '' ? ', ' . $st : ''))) ?>
                                            <?php if ($pc !== ''): ?> &mdash; <?= html_escape($pc) ?><?php endif; ?>
                                            <?php if ($ph !== ''): ?><br><span class="pv-co-hint" style="margin:4px 0 0;display:inline-block"><?= html_escape($ph) ?></span><?php endif; ?>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- =====================================================
                         Manual-entry mode (guest, or no saved addresses)
                         ===================================================== -->
                    <div class="pv-card">
                        <div class="pv-section-head">
                            <h2 class="pv-section-title">Billing address</h2>
                            <?php if ($checkout_customer_id > 0): ?>
                                <a class="pv-link" href="<?= htmlspecialchars($manage_addresses_url, ENT_QUOTES, 'UTF-8') ?>">+ Manage addresses</a>
                            <?php endif; ?>
                        </div>
                        <div class="pv-form-row">
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtfirstname">First name *</label>
                                <input name="billing_first_name" type="text" class="pv-input" id="txtfirstname" value="<?= htmlspecialchars($billing_first_name, ENT_QUOTES, 'UTF-8') ?>" required>
                                <small id="fNameError" class="error-text">Please enter only alphabets.</small>
                            </div>
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtlastname">Last name *</label>
                                <input name="billing_last_name" type="text" class="pv-input" id="txtlastname" value="<?= htmlspecialchars($billing_last_name, ENT_QUOTES, 'UTF-8') ?>" required>
                                <small id="lNameError" class="error-text">Please enter only alphabets.</small>
                            </div>
                        </div>
                        <div class="pv-form-group">
                            <label class="pv-label" for="txtaddress1">Address line 1 *</label>
                            <input name="billing_address_1" type="text" class="pv-input" id="txtaddress1" value="<?= isset($postdata['billing_address_1']) ? htmlspecialchars($postdata['billing_address_1'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
                            <small id="add1Error" class="error-text">Please enter a valid address.</small>
                        </div>
                        <div class="pv-form-group">
                            <label class="pv-label" for="txtaddress2">Apt / Suite</label>
                            <input name="billing_address_2" type="text" class="pv-input" id="txtaddress2" value="<?= isset($postdata['billing_address_2']) ? htmlspecialchars($postdata['billing_address_2'], ENT_QUOTES, 'UTF-8') : '' ?>">
                        </div>
                        <div class="pv-form-row">
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtcity">City *</label>
                                <input name="billing_city" type="text" class="pv-input" id="txtcity" value="<?= isset($postdata['billing_city']) ? htmlspecialchars($postdata['billing_city'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
                                <small id="cityError" class="error-text">Please enter city.</small>
                            </div>
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtpostcode">Postcode / ZIP *</label>
                                <input name="billing_postcode" type="text" class="pv-input" id="txtpostcode" value="<?= isset($postdata['billing_postcode']) ? htmlspecialchars($postdata['billing_postcode'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
                                <small id="postcodeError" class="error-text">Please enter a postcode.</small>
                            </div>
                        </div>
                        <div class="pv-form-row">
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtstate">State / Province *</label>
                                <select name="billing_state" class="pv-select" id="txtstate" required>
                                    <option value="">Select state</option>
                                    <?php if (is_array($state_list)) foreach ($state_list as $state):
                                        $sval = $state['name'] . '~' . $state['code'];
                                        $sel  = (isset($postdata['billing_state']) && $postdata['billing_state'] === $sval) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($sval, ENT_QUOTES, 'UTF-8') . '" ' . $sel . ' data-country-id="' . htmlspecialchars((string) $state['country_id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($state['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    endforeach; ?>
                                </select>
                                <small id="stateError" class="error-text">Please select a value.</small>
                            </div>
                            <div class="pv-form-group">
                                <label class="pv-label" for="billtocountry">Country *</label>
                                <select name="billing_country" class="pv-select" id="billtocountry" required>
                                    <option value="">Select country</option>
                                    <?php if (is_array($country)) foreach ($country as $cc):
                                        // Controller does explode('~') and treats [1] as ISO code, so order is name~code.
                                        $cval = $cc->name . '~' . $cc->code;
                                        $sel  = (isset($postdata['billing_country']) && $postdata['billing_country'] === $cval) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($cval, ENT_QUOTES, 'UTF-8') . '" ' . $sel . ' data-country-id="' . (int) $cc->id . '" phonedigits="' . htmlspecialchars((string) $cc->phone_digits, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($cc->name, ENT_QUOTES, 'UTF-8') . '</option>';
                                    endforeach; ?>
                                </select>
                                <small id="countryError" class="error-text">Please select a value.</small>
                            </div>
                        </div>
                        <div class="pv-form-row">
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtphone">Phone *</label>
                                <input name="billing_phone" type="text" class="pv-input" id="txtphone" value="<?= htmlspecialchars($billing_phone, ENT_QUOTES, 'UTF-8') ?>" required>
                                <small id="phoneError" class="error-text">Please enter a valid phone number.</small>
                            </div>
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtemail">Email *</label>
                                <input name="billing_email" type="email" class="pv-input" id="txtemail" value="<?= htmlspecialchars($billing_email, ENT_QUOTES, 'UTF-8') ?>" required>
                                <small id="emailError" class="error-text">Please enter a valid email.</small>
                            </div>
                        </div>
                    </div>

                    <label class="pv-same-shipping">
                        <input type="checkbox" name="billing_and_shipping" id="billtocopy" value="1" checked>
                        <span>Shipping address same as billing address</span>
                    </label>
                    <input type="hidden" name="billing_and_shipping_address_is_same" id="billing_and_shipping_address_is_same" value="<?= htmlspecialchars((string) $billing_and_shipping_address_is_same, ENT_QUOTES, 'UTF-8') ?>">

                    <div id="usershipping" style="display:none">
                        <div class="pv-card">
                            <div class="pv-section-head">
                                <h2 class="pv-section-title">Shipping address</h2>
                            </div>
                            <div class="pv-form-row">
                                <div class="pv-form-group">
                                    <label class="pv-label" for="txtsfirstname">First name *</label>
                                    <input name="shipping_first_name" type="text" class="pv-input" id="txtsfirstname" value="<?= isset($postdata['shipping_first_name']) ? htmlspecialchars($postdata['shipping_first_name'], ENT_QUOTES, 'UTF-8') : '' ?>" maxlength="30">
                                    <small id="fNameErrorS" class="error-text">Please enter only alphabets.</small>
                                </div>
                                <div class="pv-form-group">
                                    <label class="pv-label" for="txtslastname">Last name *</label>
                                    <input name="shipping_last_name" type="text" class="pv-input" id="txtslastname" value="<?= isset($postdata['shipping_last_name']) ? htmlspecialchars($postdata['shipping_last_name'], ENT_QUOTES, 'UTF-8') : '' ?>" maxlength="30">
                                    <small id="lNameErrorS" class="error-text">Please enter only alphabets.</small>
                                </div>
                            </div>
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtsaddress1">Address line 1 *</label>
                                <input name="shipping_address_1" type="text" class="pv-input" id="txtsaddress1" value="<?= isset($postdata['shipping_address_1']) ? htmlspecialchars($postdata['shipping_address_1'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                <small id="add1ErrorS" class="error-text">Please enter a valid address.</small>
                            </div>
                            <div class="pv-form-group">
                                <label class="pv-label" for="txtsaddress2">Apt / Suite</label>
                                <input name="shipping_address_2" type="text" class="pv-input" id="txtsaddress2" value="<?= isset($postdata['shipping_address_2']) ? htmlspecialchars($postdata['shipping_address_2'], ENT_QUOTES, 'UTF-8') : '' ?>">
                            </div>
                            <div class="pv-form-row">
                                <div class="pv-form-group">
                                    <label class="pv-label" for="txtscity">City *</label>
                                    <input name="shipping_city" type="text" class="pv-input" id="txtscity" value="<?= isset($postdata['shipping_city']) ? htmlspecialchars($postdata['shipping_city'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                    <small id="cityErrorS" class="error-text">Please enter a valid city.</small>
                                </div>
                                <div class="pv-form-group">
                                    <label class="pv-label" for="txtspostcode">Postcode / ZIP *</label>
                                    <input name="shipping_postcode" type="text" class="pv-input" id="txtspostcode" value="<?= isset($postdata['shipping_postcode']) ? htmlspecialchars($postdata['shipping_postcode'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                    <small id="postcodeErrorS" class="error-text">Please enter a postcode.</small>
                                </div>
                            </div>
                            <div class="pv-form-row">
                                <div class="pv-form-group">
                                    <label class="pv-label" for="txtsstate">State *</label>
                                    <select name="shipping_state" class="pv-select" id="txtsstate">
                                        <option value="">Select state</option>
                                        <?php if (is_array($state_list)) foreach ($state_list as $state):
                                            $sval = $state['name'] . '~' . $state['code'];
                                            $sel  = (isset($postdata['shipping_state']) && $postdata['shipping_state'] === $sval) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($sval, ENT_QUOTES, 'UTF-8') . '" ' . $sel . ' data-country-id="' . htmlspecialchars((string) $state['country_id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($state['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                                        endforeach; ?>
                                    </select>
                                    <small id="stateErrorS" class="error-text">Please select a value.</small>
                                </div>
                                <div class="pv-form-group">
                                    <label class="pv-label" for="shiptocountry">Country *</label>
                                    <select name="shipping_country" class="pv-select" id="shiptocountry">
                                        <option value="">Select country</option>
                                        <?php if (is_array($country)) foreach ($country as $cc):
                                            // Match billing select: name~code so the controller's [1] reads as the ISO code.
                                            $cval = $cc->name . '~' . $cc->code;
                                            $sel_ship = (isset($postdata['shipping_country']) && $postdata['shipping_country'] === $cval);
                                            if (!$sel_ship && isset($postdata['billing_country']) && $postdata['billing_country'] === $cval) {
                                                $sel_ship = true;
                                            }
                                            $sel = $sel_ship ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($cval, ENT_QUOTES, 'UTF-8') . '" ' . $sel . ' data-country-id="' . (int) $cc->id . '" phonedigits="' . htmlspecialchars((string) $cc->phone_digits, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($cc->name, ENT_QUOTES, 'UTF-8') . '</option>';
                                        endforeach; ?>
                                    </select>
                                    <small id="countryErrorS" class="error-text">Please select a value.</small>
                                </div>
                            </div>
                            <div class="pv-form-row">
                                <div class="pv-form-group">
                                    <label class="pv-label" for="txtsphone">Phone *</label>
                                    <input name="shipping_phone" type="text" class="pv-input" id="txtsphone" value="<?= isset($postdata['shipping_phone']) ? htmlspecialchars($postdata['shipping_phone'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                    <small id="phoneErrorS" class="error-text">Please enter a valid phone number.</small>
                                </div>
                                <div class="pv-form-group">
                                    <label class="pv-label" for="txtsemail">Email *</label>
                                    <input name="shipping_email" type="email" class="pv-input" id="txtsemail" value="<?= isset($postdata['shipping_email']) ? htmlspecialchars($postdata['shipping_email'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                    <small id="emailErrorS" class="error-text">Please enter a valid email.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Payment Method -->
                    <div class="pv-card">
                        <div class="pv-section-head">
                            <h2 class="pv-section-title">Payment method</h2>
                        </div>
                        <label class="pv-payment-option">
                            <input type="radio" name="payment_method" value="ccavenue" id="ccavenue" checked>
                            <span class="pv-payment-label">Online payment</span>
                        </label>
                    </div>

                    <button type="submit" class="pv-btn-checkout" id="btn-continue">Place order &rarr;</button>
                </form>
            </div>

            <!-- Order Summary -->
            <aside>
                <div class="pv-card">
                    <div class="pv-section-head"><h2 class="pv-section-title">Order summary</h2></div>
                    <?php
                    $cart_data2 = isset($cart_data) ? $cart_data : array();
                    $cart_products2 = isset($cart_data2['products']) ? $cart_data2['products'] : array();
                    if (is_array($cart_items)):
                        foreach ($cart_items as $item):
                            $pinfo = isset($cart_products2[$item['product_id']]) ? $cart_products2[$item['product_id']] : null;
                            $iname = $pinfo && isset($pinfo['name']) ? $pinfo['name'] : 'Product';
                            $iimg  = ($pinfo && !empty($pinfo['image'])) ? $pinfo['image'] : 'no_image.png';
                    ?>
                    <div class="pv-summary-item">
                        <img src="<?= webshop_media_src($uploads, $iimg) ?>" alt="<?= htmlspecialchars($iname, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="pv-summary-item-body">
                            <p class="pv-summary-item-name"><?= htmlspecialchars($iname, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="pv-summary-item-meta">Qty: <?= (int) $item['quantity'] ?></p>
                        </div>
                        <?php if (!empty($item['show_price'])): ?><span class="pv-summary-item-price"><?= $this->sma->formatMoney((float) $item['quantity'] * (float) $item['product_price']) ?></span><?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                    <hr style="border:none;border-top:1px solid var(--gp-border);margin:12px 0">
                    <div class="pv-summary-row">
                        <span>Subtotal</span>
                        <span><?= $this->sma->formatMoney($subtotal) ?></span>
                    </div>
                    <?php if ($tax_included > 0): ?>
                    <div class="pv-summary-row pv-summary-row-sub">
                        <span>VAT<?= htmlspecialchars($tax_rate_label, ENT_QUOTES, 'UTF-8') ?> <small>(included)</small></span>
                        <span><?= $this->sma->formatMoney($tax_included) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($tax_extra > 0): ?>
                    <div class="pv-summary-row">
                        <span>VAT<?= htmlspecialchars($tax_rate_label, ENT_QUOTES, 'UTF-8') ?></span>
                        <span><?= $this->sma->formatMoney($tax_extra) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="pv-summary-row">
                        <span>Shipping</span>
                        <span><?= $ship_amount > 0 ? $this->sma->formatMoney($ship_amount) : 'Free' ?></span>
                    </div>
                    <div class="pv-summary-total">
                        <span>Total</span>
                        <span><?= $this->sma->formatMoney($grand_total_display) ?></span>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</main>
<?php require_once(VIEWPATH . 'plane_vanila_theme/gulfpharmacy_theme/footer.php'); ?>
<script src="<?= $assets ?>gulfpharmacy_theme/js/main.js"></script>
<script src="<?= $assets ?>gulfpharmacy_theme/js/jquery.validate.min.js"></script>
<script src="<?= $assets ?>custom_js/common.js"></script>
<script src="<?= $assets ?>gulfpharmacy_theme/js/checkout.js"></script>
<script type="module" src="<?= $assets ?>gulfpharmacy_theme/js/common.js"></script>
</body>
</html>
