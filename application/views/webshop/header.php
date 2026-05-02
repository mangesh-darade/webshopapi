<?php
if (!empty($api_warning)) {
    echo '<div class="alert alert-warning text-center" role="alert" style="margin:0;border-radius:0">' . html_escape($api_warning) . '</div>';
}

/*
 * Include Top Header Section As Per Settings
 */

include_once('headers/header_strip.php');



/*
 * For header style refer view files as per settings.
 * 
 * Ex. 1) headers/header_fixed_menubar.php 
 *     2) headers/header_fixed_searchbar.php 
 *     3) headers/header_theme_default.php
 * 
 */

include_once("headers/" . $webshop_settings->header_style . ".php");

?> 