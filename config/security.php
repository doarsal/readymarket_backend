<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security-related configuration options for your
    | Laravel application. These settings help protect your application
    | from common security vulnerabilities.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for different types of operations
    |
    */
    'rate_limits' => [
        'auth' => [
            'attempts' => 10,
            'decay_minutes' => 1,
        ],
        'payment' => [
            'attempts' => 5,
            'decay_minutes' => 1,
        ],
        'api_general' => [
            'attempts' => 100,
            'decay_minutes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Security
    |--------------------------------------------------------------------------
    |
    | Settings for account security features
    |
    */
    'account' => [
        'max_failed_login_attempts' => 5,
        'lockout_duration_minutes' => 30,
        'password_expires_days' => 90,
        'require_email_verification' => true,
        'two_factor_enabled' => false, // Para futuro uso
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Security
    |--------------------------------------------------------------------------
    |
    | Settings for API token security
    |
    */
    'tokens' => [
        'expire_after_minutes' => 1440, // 24 horas
        'refresh_threshold_minutes' => 60, // Refrescar cuando quedan 1 hora
        'max_tokens_per_user' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Settings for session security
    |
    */
    'session' => [
        'secure_cookies' => env('APP_ENV') === 'production',
        'http_only_cookies' => true,
        'same_site_cookies' => 'strict',
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Security
    |--------------------------------------------------------------------------
    |
    | Settings for IP-based security features
    |
    */
    'ip' => [
        'track_user_ips' => true,
        'alert_on_new_ip' => false, // Para futuro uso
        'max_ips_per_user' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security
    |--------------------------------------------------------------------------
    |
    | Settings for content security policies
    |
    */
    'content' => [
        'max_upload_size' => 10240, // 10MB
        'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'scan_uploads' => false, // Para futuro uso (antivirus)
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Security
    |--------------------------------------------------------------------------
    |
    | Settings for security-related logging
    |
    */
    'logging' => [
        'log_failed_logins' => true,
        'log_successful_logins' => false,
        'log_sensitive_operations' => true,
        'hash_sensitive_data' => true,
    ],

];
