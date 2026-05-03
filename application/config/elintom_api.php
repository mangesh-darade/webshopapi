<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Two-app setup: Webshop AI (this repo) + ElintOm POS (separate folder)
|--------------------------------------------------------------------------
| ElintOm lives elsewhere on the same host, e.g. WAMP:
|   C:\wamp\www\ElintOm   →  http://localhost/ElintOm/
| This storefront does NOT query ElintOm's MySQL; it only talks HTTP:
|
|   POST {elintom_api_base_url}/api3/eshop
|   Body (application/x-www-form-urlencoded):
|     privatekey = same as ElintOm Settings → API private key (sma_settings.api_privatekey)
|     action     = getsettings | getparentcategories | getsubcategories | getallproducts | ...
|
| Server implementation: ElintOm app/controllers/Api3.php → eshop()
| Requirements on ElintOm: POS version ≥ 3.00, Settings → API access enabled.
|
| Optional overrides (priority low → high):
|   1) Environment variables (Docker / Apache SetEnv / systemd)
|   2) elintom_api.local.php — wins over env on dev machines (see elintom_api.local.example.php)
*/

$config['elintom_api_base_url'] = 'http://localhost/ElintOm/';
$config['elintom_api_private_key'] = '';

/*
| Base URL for product/category images (ElintOm serves files under assets/mdata/…).
| Example: http://localhost/ElintOm/assets/mdata/YOUR_TENANT/uploads/
| Trailing slash required. Leave empty to derive after customer folder below.
*/
$config['elintom_media_uploads_base_url'] = 'http://localhost/ElintOm/assets/mdata/default/uploads/';

/*
| Tenant folder under ElintOm assets/mdata/{folder}/ — must match ElintOm's Customer_assets.
*/
$config['elintom_customer_assets_folder'] = 'default';

/*
| Environment variables (optional): ELINTOM_API_BASE_URL, ELINTOM_API_PRIVATE_KEY,
| ELINTOM_MEDIA_UPLOADS_BASE_URL, ELINTOM_CUSTOMER_ASSETS_FOLDER
*/
$__elintom_env = array(
	'ELINTOM_API_BASE_URL' => 'elintom_api_base_url',
	'ELINTOM_API_PRIVATE_KEY' => 'elintom_api_private_key',
	'ELINTOM_MEDIA_UPLOADS_BASE_URL' => 'elintom_media_uploads_base_url',
	'ELINTOM_CUSTOMER_ASSETS_FOLDER' => 'elintom_customer_assets_folder',
);
foreach ($__elintom_env as $__ev => $__ck) {
	$__v = getenv($__ev);
	if (($__v === false || $__v === '') && isset($_SERVER[$__ev]) && (string) $_SERVER[$__ev] !== '') {
		$__v = $_SERVER[$__ev];
	}
	if ($__v !== false && $__v !== '') {
		$config[$__ck] = $__ck === 'elintom_api_private_key' ? trim((string) $__v) : (string) $__v;
	}
}
unset($__elintom_env, $__ev, $__ck, $__v);

/*
| Optional local overrides (not in git): elintom_api.local.php beside this file.
| Use __DIR__ so this always resolves (APPPATH is not always set during early loads).
*/
$__elintom_local = __DIR__ . DIRECTORY_SEPARATOR . 'elintom_api.local.php';
if (is_file($__elintom_local)) {
	include $__elintom_local;
}
unset($__elintom_local);

if (!empty($config['elintom_api_base_url'])) {
	$config['elintom_api_base_url'] = rtrim(str_replace('\\', '/', $config['elintom_api_base_url']), '/') . '/';
}

/*
| If media URL still empty, derive from API base + customer folder (same layout as ElintOm).
*/
if (isset($config['elintom_media_uploads_base_url'])
	&& (string) $config['elintom_media_uploads_base_url'] === ''
	&& !empty($config['elintom_api_base_url'])) {
	$__base = rtrim(str_replace('\\', '/', $config['elintom_api_base_url']), '/');
	$__folder = isset($config['elintom_customer_assets_folder'])
		? trim((string) $config['elintom_customer_assets_folder'], '/')
		: 'default';
	if ($__folder === '') {
		$__folder = 'default';
	}
	$config['elintom_media_uploads_base_url'] = $__base . '/assets/mdata/' . $__folder . '/uploads/';
}
