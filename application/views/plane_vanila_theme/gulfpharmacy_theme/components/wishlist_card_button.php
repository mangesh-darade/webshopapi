<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**

 * Favourites heart on product cards (grid, carousel, category PLP).

 */

$product_id = isset($product_id) ? (int) $product_id : 0;

$variant_id = isset($variant_id) ? (int) $variant_id : 0;

if ($product_id < 1) {

    return;

}

$lookup = function_exists('webshop_view_wishlist_lookup')

    ? webshop_view_wishlist_lookup(isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : null)

    : (isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : array());

$logged_in = function_exists('webshop_is_customer_logged_in')

    ? webshop_is_customer_logged_in()

    : !empty($logged_in);

$in_wishlist = $logged_in && function_exists('webshop_product_in_wishlist_lookup')

    ? webshop_product_in_wishlist_lookup($lookup, $product_id, $variant_id)

    : false;

$extra_class = isset($extra_class) ? trim((string) $extra_class) : '';

?>

<button type="button"

    class="gp-fav-btn gp-card-fav-btn<?= $extra_class !== '' ? ' ' . htmlspecialchars($extra_class, ENT_QUOTES, 'UTF-8') : '' ?><?= $in_wishlist ? ' is-saved' : '' ?>"

    data-wishlist-toggle

    data-product-id="<?= $product_id ?>"

    data-variant-id="<?= $variant_id ?>"

    data-in-wishlist="<?= $in_wishlist ? '1' : '0' ?>"

    aria-pressed="<?= $in_wishlist ? 'true' : 'false' ?>"

    aria-label="<?= $in_wishlist ? 'Remove from favourites' : 'Save to favourites' ?>">

    <span class="gp-fav-btn__icon" aria-hidden="true"><?= $in_wishlist ? '♥' : '♡' ?></span>

</button>

