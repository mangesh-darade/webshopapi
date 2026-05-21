<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'webshop';
$route['404_override'] = '';
// CMS URLs often use hyphens (e.g. /webshop/about-us). Maps segment to about_us when a method exists.
$route['translate_uri_dashes'] = TRUE;
