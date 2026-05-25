<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Unified Gulf Pharmacy My Account view.
 *
 * Hosts five tabs (profile / orders / tracking / addresses / change_password) in one shell so
 * /webshop/your_account, /webshop/your_orders, /webshop/your_address and
 * /webshop/change_password all render the same page with a different active tab.
 *
 * Data expected from Webshop::_render_my_account_view():
 *   $customer        : associative array (id, name, email, phone, dob, image, ...)
 *   $addresses       : array keyed by id of address rows (or FALSE)
 *   $orders          : ['orders' => [obj, ...], 'payments' => [...]] or FALSE
 *   $state_list      : array of ['name','code','country_id'] entries
 *   $country         : list of country objects (->name, ->code, ->id, ->phone_digits, ->postal_code)
 *   $active_tab      : one of profile|orders|tracking|addresses|change_password
 *   $password_status : 'success'|'error' (set when arriving from /change_password/$status)
 *   $ma_orders_lazy / $ma_addresses_lazy / $ma_geo_lazy : when true, that slice is filled client-side
 *       after POST webshop_request (action=account_panel_data).
 *   $Settings        : provides $Settings->symbol for currency formatting
 */

$active_tab = isset($active_tab) && in_array($active_tab, array('profile','orders','tracking','addresses','change_password'), true) ? $active_tab : 'profile';

$ma_orders_lazy = !empty($ma_orders_lazy);
$ma_addresses_lazy = !empty($ma_addresses_lazy);
$ma_geo_lazy = !empty($ma_geo_lazy);

$customer   = isset($customer) && is_array($customer) ? $customer : array();
$addresses  = isset($addresses) && is_array($addresses) ? $addresses : array();
$orders_set = isset($orders) && is_array($orders) && isset($orders['orders']) && is_array($orders['orders']) ? $orders['orders'] : array();

$first_name = '';
if (!empty($customer['name'])) {
    $tokens = preg_split('/\s+/', trim((string) $customer['name']));
    $first_name = $tokens[0];
}
$cust_email = isset($customer['email']) ? (string) $customer['email'] : '';
$cust_phone = isset($customer['phone']) ? (string) $customer['phone'] : '';
$cust_dob   = isset($customer['dob']) && $customer['dob'] !== '0000-00-00' ? (string) $customer['dob'] : '';
$_ma_local_images = isset($images) ? (string) $images : base_url('assets/images/customers/');
$_ma_uploads_base  = isset($uploads) ? (string) $uploads : '';
$cust_image = function_exists('webshop_customer_avatar_src')
    ? webshop_customer_avatar_src($customer, $_ma_local_images, $_ma_uploads_base)
    : '';
$avatar_initials = function_exists('webshop_avatar_initials_from_name')
    ? webshop_avatar_initials_from_name(isset($customer['name']) ? (string) $customer['name'] : '')
    : '?';

$webshop_url = base_url('webshop');
$currency = (isset($Settings) && is_object($Settings) && !empty($Settings->symbol)) ? (string) $Settings->symbol : '$';

$password_msg = $this->session->flashdata('message');
$password_err = $this->session->flashdata('error');
$pw_status    = isset($password_status) ? (string) $password_status : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>My Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/common.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/header.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/header-drawers.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/components.css') ?>">
    <link rel="stylesheet" href="<?= webshop_theme_assets_url('css/my-account.css') ?>">
    <?php if (function_exists('webshop_csrf_pair')): ?>
    <script>window.GP_CSRF=<?= json_encode(webshop_csrf_pair(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <?php endif; ?>
</head>
<body>
<div class="gp-site-wrapper">

<?php include_once('header.php'); ?>

<main class="ma-shell">
    <!-- Sidebar / tabs -->
    <aside class="ma-side" aria-label="Account navigation">
        <div class="ma-side-greet">
            <p class="ma-side-greet-hi">Hello,</p>
            <p class="ma-side-greet-name"><?= htmlspecialchars($first_name !== '' ? $first_name : 'there', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <ul class="ma-side-list" role="tablist">
            <li><button type="button" class="ma-side-link<?= $active_tab === 'profile' ? ' is-active' : '' ?>" data-tab="profile" role="tab" <?= $active_tab === 'profile' ? 'aria-current="page"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                Profile
            </button></li>
            <li><button type="button" class="ma-side-link<?= $active_tab === 'orders' ? ' is-active' : '' ?>" data-tab="orders" role="tab" <?= $active_tab === 'orders' ? 'aria-current="page"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                Orders
            </button></li>
            <li><button type="button" class="ma-side-link<?= $active_tab === 'tracking' ? ' is-active' : '' ?>" data-tab="tracking" role="tab" <?= $active_tab === 'tracking' ? 'aria-current="page"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                Track order
            </button></li>
            <li><button type="button" class="ma-side-link<?= $active_tab === 'addresses' ? ' is-active' : '' ?>" data-tab="addresses" role="tab" <?= $active_tab === 'addresses' ? 'aria-current="page"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                Addresses
            </button></li>
            <li><button type="button" class="ma-side-link<?= $active_tab === 'change_password' ? ' is-active' : '' ?>" data-tab="change_password" role="tab" <?= $active_tab === 'change_password' ? 'aria-current="page"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Change password
            </button></li>
            <li class="ma-side-divider" role="presentation"></li>
            <li><a class="ma-side-link" href="<?= $webshop_url ?>/wishlist">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                Wishlist
            </a></li>
            <li><a class="ma-side-link" href="<?= $webshop_url ?>/logout" data-danger="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                Sign out
            </a></li>
        </ul>
    </aside>

    <!-- Tab panes -->
    <section class="ma-main">

        <!-- ─────────── Profile ─────────── -->
        <div class="ma-tab<?= $active_tab === 'profile' ? ' is-active' : '' ?>" data-tab="profile" role="tabpanel">
            <div class="ma-card">
                <div class="ma-card-head">
                    <div>
                        <h2 class="ma-card-title">Personal details</h2>
                        <p class="ma-card-sub">Keep your name, email and date of birth up to date.</p>
                    </div>
                </div>

                <form class="ma-form" id="ma-profile-form" autocomplete="on" novalidate>
                    <div class="ma-banner ma-banner-inline" hidden></div>

                    <div class="ma-avatar">
                        <div class="ma-avatar-visual" aria-hidden="true">
                            <span class="ma-avatar-fallback" id="ma-avatar-fallback"<?= $cust_image !== '' ? ' hidden' : '' ?>><?= htmlspecialchars($avatar_initials, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($cust_image !== ''): ?>
                            <img class="ma-avatar-img" id="ma-avatar-img" src="<?= htmlspecialchars($cust_image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars('Profile photo of ' . (isset($customer['name']) ? (string) $customer['name'] : 'account holder'), ENT_QUOTES, 'UTF-8') ?>" decoding="async" onerror="this.hidden=true;var f=document.getElementById('ma-avatar-fallback');if(f){f.hidden=false;}">
                            <?php endif; ?>
                        </div>
                        <div class="ma-avatar-meta">
                            <p class="ma-avatar-name"><?= htmlspecialchars(isset($customer['name']) ? (string) $customer['name'] : 'Account holder', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="ma-avatar-sub"><?= htmlspecialchars($cust_email !== '' ? $cust_email : 'Add your email below', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>

                    <div class="ma-form-row">
                        <label class="ma-label" for="ma-fName">Full name *</label>
                        <input type="text" id="ma-fName" name="fName" class="ma-input" value="<?= htmlspecialchars(isset($customer['name']) ? (string) $customer['name'] : '', ENT_QUOTES, 'UTF-8') ?>" required>
                        <p class="ma-error">Please enter your name using letters only.</p>
                    </div>

                    <div class="ma-form-row-2">
                        <div>
                            <label class="ma-label" for="ma-email">Email *</label>
                            <input type="email" id="ma-email" name="email" class="ma-input" value="<?= htmlspecialchars($cust_email, ENT_QUOTES, 'UTF-8') ?>" required>
                            <p class="ma-error">Please enter a valid email address.</p>
                        </div>
                        <div>
                            <label class="ma-label" for="ma-dob">Date of birth</label>
                            <input type="date" id="ma-dob" name="dob" class="ma-input" value="<?= htmlspecialchars($cust_dob, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="ma-form-row">
                        <label class="ma-label" for="ma-phone">Contact number</label>
                        <input type="tel" id="ma-phone" class="ma-input" value="<?= htmlspecialchars($cust_phone, ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <p class="ma-help">Phone number is used for sign-in and cannot be changed here. Contact support to update.</p>
                    </div>

                    <div class="ma-actions">
                        <button type="submit" class="ma-btn ma-btn-primary">Save changes</button>
                    </div>
                </form>
            </div>

            <div class="ma-card ma-track-card">
                <div class="ma-card-head">
                    <div>
                        <h2 class="ma-card-title">Track an order</h2>
                        <p class="ma-card-sub">Enter the tracking code from your confirmation email, or the order link code (32 characters).</p>
                    </div>
                </div>
                <form class="ma-track-form" action="#" method="get" data-tracking-base="<?= htmlspecialchars($webshop_url, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="ma-form-row">
                        <label class="ma-label" for="ma-track-code-profile">Order tracking code</label>
                        <input type="text" id="ma-track-code-profile" name="order_code" class="ma-input ma-track-code" placeholder="e.g. 5f4dcc3b5aa765d61d8327deb882cf99" autocomplete="off" maxlength="64">
                        <p class="ma-help">You can also open <a href="<?= htmlspecialchars($webshop_url, ENT_QUOTES, 'UTF-8') ?>/your_orders">My orders</a> and use Track next to any order.</p>
                    </div>
                    <div class="ma-actions ma-actions-inline">
                        <button type="submit" class="ma-btn ma-btn-primary">View tracking</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ─────────── Orders ─────────── -->
        <div class="ma-tab<?= $active_tab === 'orders' ? ' is-active' : '' ?>" data-tab="orders" role="tabpanel">
            <div class="ma-card">
                <div class="ma-card-head">
                    <div>
                        <h2 class="ma-card-title">My orders</h2>
                        <p class="ma-card-sub" id="ma-orders-sub"><?php if (!$ma_orders_lazy): ?><?= count($orders_set) ?> order<?= count($orders_set) === 1 ? '' : 's' ?> placed<?php else: ?><span class="ma-orders-sub-ph ma-muted">Loading…</span><?php endif; ?></p>
                    </div>
                </div>

                <div id="ma-orders-panel-root">
                <?php if (!$ma_orders_lazy): ?>
                <?php if (empty($orders_set)): ?>
                    <div class="ma-orders-empty">
                        <svg class="ma-orders-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                        <p class="ma-orders-empty-title">No orders yet</p>
                        <p class="ma-orders-empty-text">When you place your first order, you'll see it here.</p>
                        <a class="ma-btn ma-btn-primary" href="<?= $webshop_url ?>">Start shopping</a>
                    </div>
                <?php else: ?>
                    <div class="ma-orders-table-wrap">
                        <table class="ma-orders-table">
                            <thead>
                                <tr>
                                    <th scope="col">Order #</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Total</th>
                                    <th scope="col" aria-label="Actions"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orders_set as $o):
                                $ref = isset($o->reference_no) ? (string) $o->reference_no : (isset($o->order_id) ? '#' . $o->order_id : '');
                                $date = !empty($o->date) ? date('d M Y', strtotime((string) $o->date)) : '';
                                $sale_status = isset($o->sale_status) ? strtolower((string) $o->sale_status) : 'pending';
                                $grand = isset($o->grand_total) ? (float) $o->grand_total : 0;
                                $oid = isset($o->order_id) ? (int) $o->order_id : 0;
                                $hash = $oid > 0 ? md5($oid) : '';
                            ?>
                                <tr>
                                    <td data-label="Order #">
                                        <?php if ($hash !== ''): ?>
                                            <a class="ma-orders-id" href="<?= $webshop_url ?>/track_order/<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') ?></a>
                                        <?php else: ?>
                                            <span class="ma-orders-id"><?= htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Date"><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Status"><span class="ma-status" data-status="<?= htmlspecialchars($sale_status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($sale_status), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="ma-orders-total" data-label="Total"><?= htmlspecialchars($currency . ' ' . number_format($grand, 2), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <div class="ma-orders-actions">
                                            <?php if ($hash !== ''): ?>
                                                <a class="ma-orders-action" href="<?= $webshop_url ?>/track_order/<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">Track</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <?php else: ?>
                    <div class="ma-lazy-block" id="ma-orders-lazy" aria-busy="true">
                        <div class="ma-lazy-line ma-lazy-line--long"></div>
                        <div class="ma-lazy-line ma-lazy-line--med"></div>
                        <div class="ma-lazy-line ma-lazy-line--long"></div>
                        <div class="ma-lazy-line ma-lazy-line--short"></div>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ─────────── Track order ─────────── -->
        <div class="ma-tab<?= $active_tab === 'tracking' ? ' is-active' : '' ?>" data-tab="tracking" role="tabpanel">
            <div class="ma-card">
                <div class="ma-card-head">
                    <div>
                        <h2 class="ma-card-title">Track your order</h2>
                        <p class="ma-card-sub">Paste the tracking code from your order confirmation, or use the same code from the &ldquo;Track&rdquo; link in your email.</p>
                    </div>
                </div>
                <form class="ma-track-form" action="#" method="get" data-tracking-base="<?= htmlspecialchars($webshop_url, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="ma-form-row">
                        <label class="ma-label" for="ma-track-code-tab">Tracking code</label>
                        <input type="text" id="ma-track-code-tab" name="order_code" class="ma-input ma-track-code" placeholder="32-character code or reference from email" autocomplete="off" maxlength="64">
                        <p class="ma-help">Tip: from <a href="<?= htmlspecialchars($webshop_url, ENT_QUOTES, 'UTF-8') ?>/your_orders">My orders</a>, click the order number or Track to open the live timeline.</p>
                    </div>
                    <div class="ma-actions ma-actions-inline">
                        <button type="submit" class="ma-btn ma-btn-primary">View tracking</button>
                        <a class="ma-btn" href="<?= htmlspecialchars($webshop_url, ENT_QUOTES, 'UTF-8') ?>/your_orders">Back to orders</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- ─────────── Addresses ─────────── -->
        <div class="ma-tab<?= $active_tab === 'addresses' ? ' is-active' : '' ?>" data-tab="addresses" role="tabpanel">
            <div class="ma-card">
                <div class="ma-card-head">
                    <div>
                        <h2 class="ma-card-title">Saved addresses</h2>
                        <p class="ma-card-sub">Manage delivery and billing addresses for faster checkout.</p>
                    </div>
                    <button type="button" id="ma-addr-add-btn" class="ma-btn ma-btn-primary">+ Add address</button>
                </div>

                <div id="ma-addr-panel-root">
                <?php if (!$ma_addresses_lazy): ?>
                <?php if (empty($addresses)): ?>
                    <div class="ma-addr-empty" id="ma-addr-empty">
                        <svg class="ma-addr-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        <p>You haven't added any addresses yet. Add one to speed up checkout.</p>
                    </div>
                <?php else: ?>
                    <div class="ma-addr-grid" id="ma-addr-list">
                        <?php foreach ($addresses as $a):
                            $is_default = isset($a['is_default']) && (int) $a['is_default'] === 1;
                            $jsonAddr = json_encode(array(
                                'id'           => isset($a['id']) ? (int) $a['id'] : 0,
                                'address_name' => isset($a['address_name']) ? (string) $a['address_name'] : '',
                                'line1'        => isset($a['line1']) ? (string) $a['line1'] : '',
                                'line2'        => isset($a['line2']) ? (string) $a['line2'] : '',
                                'city'         => isset($a['city']) ? (string) $a['city'] : '',
                                'postal_code'  => isset($a['postal_code']) ? (string) $a['postal_code'] : '',
                                'state'        => isset($a['state']) ? (string) $a['state'] : '',
                                'country'      => isset($a['country']) ? (string) $a['country'] : '',
                                'phone'        => isset($a['phone']) ? (string) $a['phone'] : '',
                                'email_id'     => isset($a['email_id']) ? (string) $a['email_id'] : '',
                                'is_default'   => $is_default ? 1 : 0,
                            ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                        <article class="ma-addr-card<?= $is_default ? ' is-default' : '' ?>">
                            <?php if ($is_default): ?><span class="ma-addr-badge">Default</span><?php endif; ?>
                            <p class="ma-addr-name"><?= htmlspecialchars(isset($a['address_name']) && $a['address_name'] !== '' ? (string) $a['address_name'] : 'Saved address', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="ma-addr-body"><?php
                                $lines = array_filter(array(
                                    isset($a['line1']) ? (string) $a['line1'] : '',
                                    isset($a['line2']) ? (string) $a['line2'] : '',
                                    trim((isset($a['city']) ? (string) $a['city'] : '') . ', ' . (isset($a['state']) ? (string) $a['state'] : '') . ' ' . (isset($a['postal_code']) ? (string) $a['postal_code'] : ''), ', '),
                                    isset($a['country']) ? (string) $a['country'] : '',
                                ), 'strlen');
                                echo htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8');
                            ?></p>
                            <?php if (!empty($a['phone'])): ?>
                                <p class="ma-addr-phone"><?= htmlspecialchars((string) $a['phone'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <div class="ma-addr-actions">
                                <button type="button" class="ma-addr-action" data-addr-edit="<?= htmlspecialchars($jsonAddr, ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                                <?php if (!$is_default && !empty($a['id'])): ?>
                                    <button type="button" class="ma-addr-action" data-addr-default="<?= (int) $a['id'] ?>">Set as default</button>
                                <?php endif; ?>
                                <?php if (!empty($a['id'])): ?>
                                    <button type="button" class="ma-addr-action" data-addr-delete="<?= (int) $a['id'] ?>" data-danger="true">Delete</button>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php else: ?>
                    <div class="ma-lazy-block" id="ma-addr-lazy" aria-busy="true">
                        <div class="ma-lazy-line ma-lazy-line--long"></div>
                        <div class="ma-lazy-line ma-lazy-line--med"></div>
                        <div class="ma-lazy-line ma-lazy-line--long"></div>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ─────────── Change password ─────────── -->
        <div class="ma-tab<?= $active_tab === 'change_password' ? ' is-active' : '' ?>" data-tab="change_password" role="tabpanel">
            <div class="ma-card">
                <div class="ma-card-head">
                    <div>
                        <h2 class="ma-card-title">Change password</h2>
                        <p class="ma-card-sub">Use 8–22 characters and a different value than your current password.</p>
                    </div>
                </div>

                <?php if ($pw_status === 'success' && $password_msg): ?>
                    <div class="ma-banner ma-banner-success"><?= htmlspecialchars((string) $password_msg, ENT_QUOTES, 'UTF-8') ?></div>
                <?php elseif ($pw_status === 'error' && $password_err): ?>
                    <div class="ma-banner ma-banner-error"><?= htmlspecialchars((string) $password_err, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form class="ma-form" id="ma-password-form" method="post" action="<?= $webshop_url ?>/change_password" autocomplete="off" novalidate>
                    <?= function_exists('webshop_csrf_hidden_input') ? webshop_csrf_hidden_input() : '' ?>

                    <div class="ma-banner ma-banner-inline" hidden></div>

                    <div class="ma-form-row">
                        <label class="ma-label" for="ma-cur-password">Current password *</label>
                        <input type="password" id="ma-cur-password" name="current_password" class="ma-input" required>
                        <p class="ma-error">Please enter your current password.</p>
                    </div>

                    <div class="ma-form-row-2">
                        <div>
                            <label class="ma-label" for="ma-new-password">New password *</label>
                            <input type="password" id="ma-new-password" name="newpassword" class="ma-input" required minlength="8" maxlength="22">
                            <p class="ma-error">Must be 8–22 characters and different from the current password.</p>
                        </div>
                        <div>
                            <label class="ma-label" for="ma-cnf-password">Confirm new password *</label>
                            <input type="password" id="ma-cnf-password" name="confirm" class="ma-input" required>
                            <p class="ma-error">Confirmation must match the new password.</p>
                        </div>
                    </div>

                    <input type="hidden" name="changePassword" value="1">

                    <div class="ma-actions">
                        <button type="submit" class="ma-btn ma-btn-primary">Update password</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<!-- Address modal (replaces Bootstrap; same fields as legacy address_modal.php) -->
<div class="ma-modal-backdrop" id="ma-addr-modal-backdrop" aria-hidden="true"></div>
<div class="ma-modal" id="ma-addr-modal" role="dialog" aria-modal="true" aria-labelledby="ma-addr-modal-title" aria-hidden="true">
    <form id="ma-addr-form">
        <div class="ma-modal-head">
            <h3 class="ma-modal-title" id="ma-addr-modal-title">Add new address</h3>
            <button type="button" class="ma-modal-close" data-ma-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="ma-modal-body">
            <div class="ma-banner ma-banner-inline" hidden></div>

            <input type="hidden" id="ma-addr-action" value="add">
            <input type="hidden" id="ma-addr-id" value="">

            <div class="ma-form-row">
                <label class="ma-label" for="ma-addr-name">Address name *</label>
                <input type="text" id="ma-addr-name" class="ma-input" placeholder="Home, Office, ..." required>
            </div>
            <div class="ma-form-row">
                <label class="ma-label" for="ma-addr-line1">Address line 1 *</label>
                <input type="text" id="ma-addr-line1" class="ma-input" required>
            </div>
            <div class="ma-form-row">
                <label class="ma-label" for="ma-addr-line2">Address line 2</label>
                <input type="text" id="ma-addr-line2" class="ma-input">
            </div>
            <div class="ma-form-row-2">
                <div>
                    <label class="ma-label" for="ma-addr-city">City *</label>
                    <input type="text" id="ma-addr-city" class="ma-input" required>
                </div>
                <div>
                    <label class="ma-label" for="ma-addr-postal">Pincode</label>
                    <input type="text" id="ma-addr-postal" class="ma-input" inputmode="numeric">
                </div>
            </div>
            <!--
                Country must be picked before the State dropdown is meaningful.
                Each <option data-id> / <option data-country-id> carries the relationship
                used by my-account.js to rebuild the state list when the country changes.
                When no country is selected, the state dropdown only shows its placeholder.
            -->
            <div class="ma-form-row-2">
                <div>
                    <label class="ma-label" for="ma-addr-country">Country *</label>
                    <select id="ma-addr-country" class="ma-select" required>
                        <option value="">Select country</option>
                        <?php
                        $countries = isset($country) && is_array($country) ? $country : array();
                        foreach ($countries as $cc) {
                            $cname = '';
                            $cid   = '';
                            if (is_object($cc)) {
                                $cname = isset($cc->name) ? (string) $cc->name : '';
                                $cid   = isset($cc->id) ? (string) $cc->id : '';
                            } elseif (is_array($cc)) {
                                $cname = isset($cc['name']) ? (string) $cc['name'] : '';
                                $cid   = isset($cc['id']) ? (string) $cc['id'] : '';
                            }
                            if ($cname === '') {
                                continue;
                            }
                            echo '<option value="' . htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') . '"'
                                . ' data-id="' . htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') . '">'
                                . htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div>
                    <label class="ma-label" for="ma-addr-state">State *</label>
                    <select id="ma-addr-state" class="ma-select" required>
                        <option value="">Select country first</option>
                        <?php if (isset($state_list) && is_array($state_list)) {
                            foreach ($state_list as $s) {
                                $sname = isset($s['name']) ? (string) $s['name'] : '';
                                $scid  = isset($s['country_id']) ? (string) $s['country_id'] : '';
                                if ($sname === '') {
                                    continue;
                                }
                                echo '<option value="' . htmlspecialchars($sname, ENT_QUOTES, 'UTF-8') . '"'
                                    . ' data-country-id="' . htmlspecialchars($scid, ENT_QUOTES, 'UTF-8') . '">'
                                    . htmlspecialchars($sname, ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                        } ?>
                    </select>
                </div>
            </div>
            <div class="ma-form-row-2">
                <div>
                    <label class="ma-label" for="ma-addr-phone">Phone *</label>
                    <input type="tel" id="ma-addr-phone" class="ma-input" required>
                </div>
                <div>
                    <label class="ma-label" for="ma-addr-email">Email</label>
                    <input type="email" id="ma-addr-email" class="ma-input">
                </div>
            </div>
            <div class="ma-checkbox-row">
                <input type="checkbox" id="ma-addr-default">
                <label for="ma-addr-default">Mark as default address</label>
            </div>
        </div>
        <div class="ma-modal-foot">
            <button type="button" class="ma-btn" data-ma-modal-close>Cancel</button>
            <button type="submit" class="ma-btn ma-btn-primary">Save address</button>
        </div>
    </form>
</div>

<?php $gp_footer_styles_in_head = true; include_once('footer.php'); ?>

</div><!-- /.gp-site-wrapper -->
<script>window.GP_MA_CTX=<?= json_encode(array(
    'webshop_url' => $webshop_url,
    'customer_id' => isset($customer_id) ? (int) $customer_id : 0,
    'active_tab'  => $active_tab,
    'currency'    => $currency,
    'lazy_panel'  => ($ma_orders_lazy || $ma_addresses_lazy || $ma_geo_lazy),
    'endpoints'   => array(
        'ajax'            => $webshop_url . '/webshop_request',
        'change_password' => $webshop_url . '/change_password',
        'address_delete'  => $webshop_url . '/address_delete',
        'set_default'     => $webshop_url . '/address_set_default/' . (isset($customer_id) ? (int) $customer_id : 0),
    ),
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php $_ma_js_ver = '20260520a'; ?>
<script src="<?= webshop_theme_assets_url('js/webshop-csrf.js?ver=<?= $_ma_js_ver ?>') ?>"></script>
<script src="<?= webshop_theme_assets_url('js/header-drawers.js?ver=<?= $_ma_js_ver ?>') ?>"></script>
<script src="<?= webshop_theme_assets_url('js/my-account.js?ver=<?= $_ma_js_ver ?>') ?>"></script>

</body>
</html>
