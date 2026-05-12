<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';

$order          = isset($order) && is_array($order)              ? $order          : array();
$billing        = isset($billing_address) && is_array($billing_address) ? $billing_address : array();
$gateways       = isset($payments_gatway)                        ? $payments_gatway : null;
$gwConfig       = isset($payment_config) && is_array($payment_config)   ? $payment_config  : array();
$customerId     = isset($customer_id)                            ? $customer_id    : '';
$symbol         = isset($Settings->symbol)                      ? $Settings->symbol : '';
$shopName       = isset($Settings->site_name)                   ? $Settings->site_name : 'Shop';
$orderId        = isset($order['id'])                            ? $order['id']    : '';
$refNo          = isset($order['reference_no'])                  ? $order['reference_no'] : $orderId;
$grandTotal     = isset($order['grand_total'])                   ? (float) $order['grand_total'] : 0;
$formattedTotal = $symbol . ' ' . number_format($grandTotal, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment | <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/payments.css">
</head>
<body>
<div class="pay-shell">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
    }
    ?>

    <main class="pay-main">
        <div class="pay-card">
            <h1 class="pay-title">Complete Payment</h1>
            <p class="pay-sub">Order #<?= htmlspecialchars((string) $refNo, ENT_QUOTES, 'UTF-8') ?></p>

            <div class="pay-summary">
                <?php if (!empty($billing['address_name'])): ?>
                    <div class="pay-summary-row">
                        <span>Shipping to</span>
                        <span><?= htmlspecialchars($billing['address_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>
                <div class="pay-summary-row pay-summary-total">
                    <span>Amount Due</span>
                    <span><?= htmlspecialchars($formattedTotal, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <?php $flashErr = $this->session->flashdata('error_message'); ?>
            <?php if ($flashErr): ?>
                <div class="pay-error">⚠️ <?= htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form action="<?= base_url('webshop/payments') ?>?order=<?= urlencode((string) $orderId) ?>&customer=<?= urlencode((string) $customerId) ?>" method="post">
                <input type="hidden" name="submit" value="1">
                <input type="hidden" name="order_id" value="<?= htmlspecialchars((string) $orderId, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="reference_no" value="<?= htmlspecialchars((string) $refNo, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="customer_id" value="<?= htmlspecialchars((string) $customerId, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="amount" value="<?= htmlspecialchars(number_format($grandTotal, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">

                <div class="pay-gateways">
                    <?php
                    /**
                     * A gateway is ENABLED (shown) when pos_settings has it turned on.
                     * Credentials are checked separately — if missing, user sees a clear
                     * "not configured" error when they submit, instead of a hidden option.
                     */
                    $gwEnabled = function($key) use ($gwConfig, $gateways) {
                        // API-sourced config (from ElintOm getgatewaycredentials): check 'enabled' key.
                        if (isset($gwConfig[$key]) && array_key_exists('enabled', $gwConfig[$key])) {
                            return !empty($gwConfig[$key]['enabled']);
                        }
                        // Legacy local config fallback: use pos_settings object flag.
                        return is_object($gateways) && !empty($gateways->$key);
                    };

                    $gwHasCreds = function($key, $credKey) use ($gwConfig) {
                        return isset($gwConfig[$key][$credKey]) && $gwConfig[$key][$credKey] !== '';
                    };

                    // COD is always first — safe default pre-selected option.
                    $availableGateways = array();
                    $availableGateways['cod'] = array(
                        'label'     => 'Cash on Delivery',
                        'desc'      => 'Pay when your order arrives',
                        'configured'=> true,
                    );
                    // Online gateways — shown when enabled in pos_settings (credentials optional).
                    if ($gwEnabled('razorpay'))  $availableGateways['razorpay']  = array('label' => 'Razorpay',  'desc' => 'Pay via UPI, cards, net banking',          'configured' => $gwHasCreds('razorpay',  'KEY_ID'));
                    if ($gwEnabled('paytm'))     $availableGateways['paytm']     = array('label' => 'Paytm',     'desc' => 'Pay via Paytm wallet or UPI',                'configured' => $gwHasCreds('paytm',     'MERCHANT_KEY'));
                    if ($gwEnabled('instamojo')) $availableGateways['instamojo'] = array('label' => 'Instamojo', 'desc' => 'Pay via Instamojo',                          'configured' => $gwHasCreds('instamojo', 'API_KEY'));
                    if ($gwEnabled('ccavenue'))  $availableGateways['ccavenue']  = array('label' => 'CCAvenue',  'desc' => 'Secure card payment via CCAvenue',           'configured' => $gwHasCreds('ccavenue',  'API_KEY'));
                    if ($gwEnabled('stripe'))    $availableGateways['stripe']    = array('label' => 'Stripe',    'desc' => 'Pay via credit or debit card',               'configured' => $gwHasCreds('stripe',    'SECRET_KEY'));
                    $first = true;
                    foreach ($availableGateways as $gwKey => $gw):
                    ?>
                        <?php $configured = isset($gw['configured']) ? $gw['configured'] : true; ?>
                        <div class="pay-gateway-option<?= !$configured ? ' pay-gateway-unconfigured' : '' ?>">
                            <input type="radio" name="payment_gatway" id="gw_<?= $gwKey ?>" value="<?= $gwKey ?>" <?= $first ? 'checked' : '' ?>>
                            <label for="gw_<?= $gwKey ?>">
                                <div class="pay-gateway-text">
                                    <div class="pay-gateway-name"><?= htmlspecialchars($gw['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="pay-gateway-desc"><?= htmlspecialchars($gw['desc'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <?php if (!$configured): ?>
                                    <span class="pay-gw-badge">Setup required</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php $first = false; endforeach; ?>
                </div>

                <button type="submit" class="pay-btn">Pay <?= htmlspecialchars($formattedTotal, ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </div>
    </main>

    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php');
    }
    ?>
</div>
<script defer src="<?= $assets ?>gulfpharmacy_theme/js/payments.js"></script>
</body>
</html>
