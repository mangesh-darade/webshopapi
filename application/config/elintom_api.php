<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| ElintOm POS API (Api3) — webshop-only client
|--------------------------------------------------------------------------
| Points to your main ElintOm install. Calls POST .../api3/eshop with
| privatekey + action (same as POS offline sync).
|
| privatekey must match sma_settings.api_privatekey on the ElintOm server.
| Enable API access in ElintOm settings; POS version must be >= 3.00.
*/

$config['elintom_api_base_url'] = 'http://localhost/ElintOm/';
$config['elintom_api_private_key'] = '';

/*
| Base URL for product/category images served by ElintOm (uploads/thumbs).
| Example: http://localhost/ElintOm/assets/mdata/YOUR_TENANT/uploads/
| Trailing slash required.
*/
$config['elintom_media_uploads_base_url'] = 'http://localhost/ElintOm/assets/mdata/default/uploads/';

/*
| Folder name under assets/mdata/... (used in legacy view paths).
*/
$config['elintom_customer_assets_folder'] = 'default';

/*
| Optional local overrides (not in git): elintom_api.local.php beside this file.
| Use __DIR__ so this always resolves (APPPATH is not always set during early loads).
*/
$__elintom_local = __DIR__ . DIRECTORY_SEPARATOR . 'elintom_api.local.php';
if (is_file($__elintom_local)) {
	include $__elintom_local;
}
unset($__elintom_local);
