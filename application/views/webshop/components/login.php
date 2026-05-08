<?php defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/_map.php';
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : '';
$mapped = flow_mapped_view_path('login', $theme);
if ($mapped !== '' && is_file(VIEWPATH . $mapped)) {
    require VIEWPATH . $mapped;
}
?>
