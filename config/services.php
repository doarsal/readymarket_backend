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
    ],

];
