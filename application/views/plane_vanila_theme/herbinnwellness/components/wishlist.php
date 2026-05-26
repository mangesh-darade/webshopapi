<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Wishlist component entry — delegates to wishlist_items partial.
 */
$CI =& get_instance();
$CI->load->view(webshop_plane_vanila_view('components/wishlist/wishlist_items'), get_defined_vars());
