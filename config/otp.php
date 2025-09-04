<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Verification Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the OTP (One-Time Password) verification system
    | for user registration and authentication.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | OTP Verification Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether OTP verification is required for new users.
    | When enabled, users must verify their account via OTP code sent to
    | email and WhatsApp before they can login.
    |
    | Supported: true, false
    |
    */

    'enabled' => env('OTP_VERIFICATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | OTP Code Length
    |--------------------------------------------------------------------------
    |
    | The length of the OTP code to generate. Standard is 6 digits.
    |
    */

    'code_length' => env('OTP_CODE_LENGTH', 6),

    /*
    |--------------------------------------------------------------------------
    | OTP Expiration Time
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) the OTP code remains valid before expiring.
    |
    */

    'expiration_minutes' => env('OTP_EXPIRATION_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | OTP Resend Rate Limit
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a user must wait before requesting a new OTP code.
    |
    */

    'resend_rate_limit_seconds' => env('OTP_RESEND_RATE_LIMIT_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | OTP Channels
    |--------------------------------------------------------------------------
    |
    | Which channels should be used to send OTP codes.
    | Available: email, whatsapp
    |
    */

    'channels' => [
        'email' => env('OTP_EMAIL_ENABLED', true),
        'whatsapp' => env('OTP_WHATSAPP_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Verify When Disabled
    |--------------------------------------------------------------------------
    |
    | When OTP verification is disabled, automatically mark new users as
    | verified during registration.
    |
    */

    'auto_verify_when_disabled' => env('OTP_AUTO_VERIFY_WHEN_DISABLED', true),

];
