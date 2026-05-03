<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$autoload['packages'] = array();
/* API storefront: no 'database' — catalogue/settings come from ElintOm via Elintom_api_client (see elintom_api.php).
 * Add 'database' here only if this app shares MySQL with POS (same server). */
$autoload['libraries'] = array('session', 'elintom_api_client');
$autoload['drivers'] = array();
$autoload['helper'] = array('url', 'form', 'html', 'webshop', 'elintom_mdata');
/* Do not autoload elintom_api here — must load with use_sections=TRUE; Elintom_api_client does that first. */
$autoload['config'] = array();
$autoload['language'] = array();
$autoload['model'] = array();
