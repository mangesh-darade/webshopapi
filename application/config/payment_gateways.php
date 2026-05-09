<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -----------------------------------------------------------------------
| PAYMENT GATEWAY CONFIGURATION
| -----------------------------------------------------------------------
| This file is loaded by various payment-related methods.
| In API (DB-less) mode, actual gateway availability comes from the
| ElintOm API via get_payment_gatways(). Set keys here only if running
| with a local POS database that shares gateway credentials.
|
| All gateways are disabled by default. Enable and fill credentials
| only for gateways your store actually uses.
| -----------------------------------------------------------------------
*/

$config['payment_gateways'] = [

    'ccavenue' => [
        'API_KEY'     => '',
        'ACCESS_CODE' => '',
        'API_URL'     => 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction',
    ],

    'razorpay' => [
        'KEY_ID'     => '',
        'KEY_SECRET' => '',
        'API_URL'    => 'https://api.razorpay.com/v1/',
    ],

    'paytm' => [
        'MERCHANT_KEY' => '',
        'MERCHANT_MID' => '',
        'API_URL'      => 'https://securegw.paytm.in/theia/processTransaction',
    ],

    'instamojo' => [
        'API_KEY'    => '',
        'AUTH_TOKEN' => '',
        'API_URL'    => 'https://www.instamojo.com/api/1.1/',
    ],

    'stripe' => [
        'SECRET_KEY'      => '',
        'PUBLISHABLE_KEY' => '',
    ],

    'paypal_pro' => [
        'USERNAME'  => '',
        'PASSWORD'  => '',
        'SIGNATURE' => '',
        'API_URL'   => 'https://api-3t.paypal.com/nvp',
    ],

];
