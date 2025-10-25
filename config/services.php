<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'facturalo' => [
        // Credentials
        'api_key' => env('FACTURALO_API_KEY'),
        'test_mode' => env('FACTURALO_TEST_MODE', true),

        // Service URLs
        'url_sandbox' => env('FACTURALO_URL_SANDBOX', 'https://dev.facturaloplus.com/api/rest/servicio'),
        'url_production' => env('FACTURALO_URL_PRODUCTION', 'https://app.facturaloplus.com/api/rest/servicio'),

        // Issuer data
        'rfc' => env('FACTURALO_RFC'),
        'razon_social' => env('FACTURALO_RAZON_SOCIAL'),
        'regimen_fiscal' => env('FACTURALO_REGIMEN_FISCAL'),
        'cp' => env('FACTURALO_CP'),
        'no_certificado' => env('FACTURALO_NO_CERTIFICADO'),
    ],

    'exchangerate_api' => [
        'key' => env('EXCHANGERATE_API_KEY'),
        'base_url' => 'https://v6.exchangerate-api.com/v6',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'microsoft' => [
        'credentials_url' => env('MICROSOFT_CREDENTIALS_URL'),
        'partner_center_base_url' => env('MICROSOFT_PARTNER_CENTER_BASE_URL'),
        'agreement_template_id' => env('MICROSOFT_AGREEMENT_TEMPLATE_ID'),

        // Partner Information (CSP Partner)
        'partner_id' => env('MICROSOFT_PARTNER_ID', 'fa233b05-e848-45c4-957f-d3e11acfc49c'),
        'mspp_id' => env('MICROSOFT_MSPP_ID', '0'),
        'partner_email' => env('MICROSOFT_PARTNER_EMAIL', 'backofficemex@readymind.ms'),
        'partner_phone' => env('MICROSOFT_PARTNER_PHONE', '5585261168'),
        'partner_name' => env('MICROSOFT_PARTNER_NAME', 'ReadyMarket of Readymind Mexico SA de CV'),

        // API Timeouts (in seconds)
        'token_timeout' => env('MICROSOFT_API_TOKEN_TIMEOUT', 60),
        'create_cart_timeout' => env('MICROSOFT_API_CREATE_CART_TIMEOUT', 120),
        'checkout_timeout' => env('MICROSOFT_API_CHECKOUT_TIMEOUT', 180),
        'budget_timeout' => env('MICROSOFT_API_BUDGET_TIMEOUT', 90),

        // Retry Configuration
        'max_retries' => env('MICROSOFT_API_MAX_RETRIES', 3),
        'retry_delay' => env('MICROSOFT_API_RETRY_DELAY', 2), // seconds

        // Security
        'fake_mode' => env('MICROSOFT_FAKE_MODE', false),
        'log_sensitive_data' => env('MICROSOFT_LOG_SENSITIVE_DATA', false),
    ],

];
