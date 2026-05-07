<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* plane_vanila_theme/nw_theme/index.php
 * Thin override for the NW theme home page that wires CMS dynamic sections.
 * Falls back to webshop/nw_theme/index.php if no plane_vanila override needed.
 */
$isDynamic     = !empty($is_dynamic_cms_page);
$cmsHeaderHtml = isset($cms_header_sections_html) ? (string)$cms_header_sections_html : '';
$cmsFooterHtml = isset($cms_footer_sections_html) ? (string)$cms_footer_sections_html : '';
$cmsBodyHtml   = isset($cms_body_html)            ? (string)$cms_body_html            : '';
$dynBanner     = isset($page_banner_image_url)    ? trim((string)$page_banner_image_url) : '';
$dynLogo       = isset($page_logo_image_url)      ? trim((string)$page_logo_image_url)   : '';
$uploadsB      = isset($uploads) ? $uploads : '';
$pageTitle     = !empty($page_title) ? $page_title : 'Welcome';

$bodyHtml = '';
if (!empty($home_section_html_block))       $bodyHtml = (string)$home_section_html_block;
elseif (!empty($cmsBodyHtml))              $bodyHtml = $cmsBodyHtml;
elseif (isset($home_page_cms) && is_object($home_page_cms) && !empty($home_page_cms->page_text))
    $bodyHtml = (string)$home_page_cms->page_text;

$catItems = !empty($main_categories) && is_array($main_categories) ? $main_categories
          : (isset($this->data['categories']['main']) && is_array($this->data['categories']['main']) ? $this->data['categories']['main'] : []);

// Delegate fully to the full nw_theme index view (it already handles CMS data).
$this->load->view('webshop/nw_theme/index', $this->data);
