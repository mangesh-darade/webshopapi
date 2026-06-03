<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'webshop';
$route['404_override'] = '';
$route['sitemap.xml'] = 'webshop/sitemap';
$route['sitemap-index.xml'] = 'webshop/sitemap_index';
$route['sitemap-pages.xml'] = 'webshop/sitemap_pages';
$route['sitemap-categories.xml'] = 'webshop/sitemap_categories';
$route['sitemap-products.xml'] = 'webshop/sitemap_products';
$route['sitemap.xsl'] = 'webshop/sitemap_xsl';
$route['robots.txt'] = 'webshop/robots';
// CMS URLs often use hyphens (e.g. /webshop/about-us). Maps segment to about_us when a method exists.
$route['translate_uri_dashes'] = TRUE;
