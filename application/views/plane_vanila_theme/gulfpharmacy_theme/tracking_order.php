<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Gulf Pharmacy order tracking page.
 *
 * Renders a status timeline + order summary for one order so logged-in customers can
 * see where their order is. The lookup itself happens server-side in
 * Webshop::track_order() (via Webshop_api_model::get_order_for_tracking) and is
 * constrained to the session user — viewers cannot read someone else's order by
 * guessing reference_no.
 *
 * Data expected from the controller:
 *   $identifier      : raw URL segment (numeric id, reference_no, or MD5(id))
 *   $is_logged_in    : bool — true when a webshop session is present
 *   $tracking_order  : associative array of one orders row (or [] when not found)
 *   $tracking_items  : array of order items (or [])
 *   $tracking_error  : non-empty string when we have nothing to show (auth / not found)
 *   $Settings        : provides ->symbol for currency formatting
 */

$identifier      = isset($identifier) ? (string) $identifier : (isset($order_id) ? (string) $order_id : '');
$is_logged_in    = !empty($is_logged_in);
$tracking_order  = isset($tracking_order) && is_array($tracking_order) ? $tracking_order : array();
$tracking_items  = isset($tracking_items) && is_array($tracking_items) ? $tracking_items : array();
$tracking_error  = isset($tracking_error) ? (string) $tracking_error : '';

$currency = (isset($Settings) && is_object($Settings) && !empty($Settings->symbol)) ? (string) $Settings->symbol : '$';

// Normalise sale_status into one of 5 timeline steps. DB values vary
// (Received / accepted / In Progress / order_ready / Food Ready / Dispatched / Delivered);
// strip both whitespace AND underscores so `order_ready` and `Food Ready` collapse to
// the same key as `orderready` / `foodready`.
$status_raw    = isset($tracking_order['sale_status']) ? (string) $tracking_order['sale_status'] : '';
$status_key    = strtolower(preg_replace('/[\s_-]+/', '', $status_raw));
$status_steps  = array('received', 'in_progress', 'ready', 'dispatched', 'delivered');
$status_labels = array(
    'received'    => 'Received',
    'in_progress' => 'In progress',
    'ready'       => 'Ready',
    'dispatched'  => 'Dispatched',
    'delivered'   => 'Delivered',
);
$current_step = 'received';
if (in_array($status_key, array('inprogress', 'accepted', 'preparing', 'processing'), true)) {
    $current_step = 'in_progress';
} elseif (in_array($status_key, array('ready', 'orderready', 'foodready'), true)) {
    $current_step = 'ready';
} elseif (in_array($status_key, array('dispatched', 'shipped', 'enroute', 'outfordelivery'), true)) {
    $current_step = 'dispatched';
} elseif (in_array($status_key, array('delivered', 'completed', 'complete'), true)) {
    $current_step = 'delivered';
}

$current_index = array_search($current_step, $status_steps, true);
if ($current_index === false) {
    $current_index = 0;
}

$order_ref     = isset($tracking_order['reference_no']) ? (string) $tracking_order['reference_no'] : '';
$order_id_num  = isset($tracking_order['id']) ? (int) $tracking_order['id'] : 0;
$order_date    = isset($tracking_order['date']) ? (string) $tracking_order['date'] : '';
$order_total   = isset($tracking_order['grand_total']) ? (float) $tracking_order['grand_total'] : 0.0;
$order_sub     = isset($tracking_order['total']) ? (float) $tracking_order['total'] : 0.0;
$order_ship    = isset($tracking_order['shipping']) ? (float) $tracking_order['shipping'] : 0.0;
$order_tax     = isset($tracking_order['total_tax']) ? (float) $tracking_order['total_tax'] : 0.0;
$payment_meth  = isset($tracking_order['payment_method']) ? (string) $tracking_order['payment_method'] : '';
$payment_stat  = isset($tracking_order['payment_status']) ? (string) $tracking_order['payment_status'] : '';

$order_date_display = '';
if ($order_date !== '' && $order_date !== '0000-00-00 00:00:00') {
    $ts = strtotime($order_date);
    if ($ts) {
        $order_date_display = date('M j, Y · g:i A', $ts);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Track order<?= $order_ref !== '' ? ' · ' . htmlspecialchars($order_ref, ENT_QUOTES, 'UTF-8') : '' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/tracking-order.css">
    <link rel="icon" type="image/x-icon" href="<?= $uploads ?>webshop/herbinn_favicon.ico">
</head>
<body>

<?php include_once('header.php'); ?>

<main class="to-shell">
    <div class="to-card">
        <header class="to-card-head">
            <div>
                <h1 class="to-title">Track your order</h1>
                <?php if ($identifier !== ''): ?>
                    <p class="to-sub">Reference <span class="to-ref"><?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?></span></p>
                <?php endif; ?>
            </div>
            <a class="to-back" href="<?= htmlspecialchars(base_url('webshop/your_orders'), ENT_QUOTES, 'UTF-8') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                Back to orders
            </a>
        </header>

        <?php if ($tracking_error !== '' || empty($tracking_order)): ?>
            <div class="to-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 8v4"/>
                    <path d="M12 16h.01"/>
                </svg>
                <p class="to-empty-msg">
                    <?= htmlspecialchars($tracking_error !== '' ? $tracking_error : 'No tracking data found for this reference.', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php if (!$is_logged_in): ?>
                    <a class="to-btn" href="<?= htmlspecialchars(base_url('webshop/login'), ENT_QUOTES, 'UTF-8') ?>">Sign in</a>
                <?php else: ?>
                    <form class="to-search" method="get" action="<?= htmlspecialchars(base_url('webshop/your_tracking'), ENT_QUOTES, 'UTF-8') ?>">
                        <a class="to-btn to-btn-ghost" href="<?= htmlspecialchars(base_url('webshop/your_tracking'), ENT_QUOTES, 'UTF-8') ?>">Try another reference</a>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="to-summary">
                <div>
                    <p class="to-summary-label">Order</p>
                    <p class="to-summary-val">#<?= $order_id_num > 0 ? (int) $order_id_num : htmlspecialchars($order_ref, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="to-summary-label">Placed</p>
                    <p class="to-summary-val"><?= $order_date_display !== '' ? htmlspecialchars($order_date_display, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                </div>
                <div>
                    <p class="to-summary-label">Status</p>
                    <p class="to-summary-val to-summary-status"><?= htmlspecialchars($status_labels[$current_step], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div>
                    <p class="to-summary-label">Total</p>
                    <p class="to-summary-val"><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_total, 2) ?></p>
                </div>
            </div>

            <ol class="to-steps" aria-label="Order progress">
                <?php foreach ($status_steps as $i => $key): ?>
                    <?php
                        $state = 'to-step-pending';
                        if ($i < $current_index) {
                            $state = 'to-step-done';
                        } elseif ($i === $current_index) {
                            $state = 'to-step-active';
                        }
                    ?>
                    <li class="to-step <?= $state ?>">
                        <span class="to-step-dot" aria-hidden="true">
                            <?php if ($i < $current_index): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-11"/></svg>
                            <?php else: ?>
                                <span class="to-step-num"><?= $i + 1 ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="to-step-label"><?= htmlspecialchars($status_labels[$key], ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if (!empty($tracking_items)): ?>
                <section class="to-items" aria-label="Order items">
                    <h2 class="to-h2">Items</h2>
                    <ul class="to-items-list">
                        <?php foreach ($tracking_items as $item):
                            $row = is_array($item) ? $item : (array) $item;
                            $name = isset($row['product_name']) ? (string) $row['product_name'] : (isset($row['name']) ? (string) $row['name'] : 'Item');
                            $qty  = isset($row['quantity']) ? (float) $row['quantity'] : 0;
                            $unit = isset($row['unit_price']) ? (float) $row['unit_price'] : (isset($row['price']) ? (float) $row['price'] : 0);
                            $line = isset($row['subtotal']) ? (float) $row['subtotal'] : $qty * $unit;
                        ?>
                            <li class="to-item">
                                <span class="to-item-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="to-item-qty">× <?= $qty == (int) $qty ? (int) $qty : number_format($qty, 2) ?></span>
                                <span class="to-item-price"><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($line, 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="to-totals" aria-label="Order totals">
                <dl>
                    <div><dt>Subtotal</dt><dd><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_sub, 2) ?></dd></div>
                    <?php if ($order_tax > 0): ?><div><dt>Tax</dt><dd><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_tax, 2) ?></dd></div><?php endif; ?>
                    <div><dt>Shipping</dt><dd><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_ship, 2) ?></dd></div>
                    <div class="to-totals-grand"><dt>Total</dt><dd><?= htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') ?> <?= number_format($order_total, 2) ?></dd></div>
                </dl>
                <?php if ($payment_meth !== '' || $payment_stat !== ''): ?>
                    <p class="to-payment">
                        <?php if ($payment_meth !== ''): ?>Paid via <strong><?= htmlspecialchars(strtoupper($payment_meth), ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
                        <?php if ($payment_stat !== ''): ?> · <span class="to-pay-status to-pay-<?= htmlspecialchars(strtolower($payment_stat), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($payment_stat), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include_once('footer.php'); ?>

<?php if (!empty($tracking_order) && $order_id_num > 0): ?>
<script>
    window.GP_TRACK_CTX = {
        order_id: <?= json_encode($order_id_num, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        identifier: <?= json_encode($identifier, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        current_step: <?= json_encode($current_step, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        endpoint: <?= json_encode(base_url('webshop/track_order_status'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        csrf_name: <?= json_encode($this->security->get_csrf_token_name(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        csrf_hash: <?= json_encode($this->security->get_csrf_hash(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
</script>
<script defer src="<?= $assets ?>gulfpharmacy_theme/js/tracking-order.js"></script>
<?php endif; ?>

</body>
</html>
