<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -----------------------------------------------------------------------
| WEBSHOP SHIPPING CONFIGURATION
| -----------------------------------------------------------------------
| Flat shipping fee charged on every order, plus an optional cart-subtotal
| threshold above which shipping becomes free.
|
| Both values are also read from the ElintOm API webshop_settings block
| (keys: shipping_charges, shipping_charge, shipping_cost, shipping_amount,
|  delivery_charges, delivery_charge, delivery_fee — and free_shipping_above,
|  free_shipping_threshold, free_shipping_min, free_delivery_above,
|  free_delivery_threshold). API values, when present, win over the values
| set here, so this file acts as a local fallback.
|
| Set `flat_fee` to 0 for a free-shipping storefront. Set `free_above` to 0
| to disable the threshold (always charge `flat_fee`).
| -----------------------------------------------------------------------
*/

$config['webshop_shipping'] = array(
    // Flat shipping fee in the same currency as $Settings->symbol.
    'flat_fee'   => 0,

    // Cart subtotal (after discount) at or above which shipping becomes free.
    // 0 disables the threshold.
    'free_above' => 0,
);
