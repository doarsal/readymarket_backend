<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MITEC Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MITEC payment gateway integration
    |
    */

    'environment' => env('MITEC_ENVIRONMENT', 'sandbox'),

    // Business credentials
    'key_hex' => env('MITEC_KEY_HEX'),
    'id_company' => env('MITEC_ID_COMPANY'),
    'id_branch' => env('MITEC_ID_BRANCH', 1),
    'country' => env('MITEC_COUNTRY', 'MEX'),
    'bs_user' => env('MITEC_BS_USER'),
    'bs_pwd' => env('MITEC_BS_PWD'),
    'data0' => env('MITEC_DATA0'),

    // URLs
    'base_url' => env('MITEC_BASE_URL'),
    '3ds_url' => env('MITEC_3DS_URL'),
    'payment_url' => env('MITEC_PAYMENT_URL'),
    'webhook_url' => env('MITEC_WEBHOOK_URL'),
    'response_url' => env('MITEC_RESPONSE_URL'),

    // Merchants
    'merchant_amex' => env('MITEC_MERCHANT_AMEX'),
    'merchant_default' => env('MITEC_MERCHANT_DEFAULT'),

    // Testing
    'test_ip' => env('MITEC_TEST_IP', '127.0.0.1'),

    // Transaction settings
    'default_currency' => env('MITEC_DEFAULT_CURRENCY', 'MXN'),
    'default_cobro' => env('MITEC_DEFAULT_COBRO', '1'),
    'min_amount' => env('MITEC_MIN_AMOUNT', 0.01),
    'max_amount' => env('MITEC_MAX_AMOUNT', 999999.99),
    'billing_required' => env('MITEC_BILLING_REQUIRED', false),
];
