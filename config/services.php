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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Mikrotik Configuration
    |--------------------------------------------------------------------------
    */
    'mikrotik' => [
        'host' => env('MIKROTIK_HOST', '192.168.1.88'),
        'port' => (int) env('MIKROTIK_PORT', 8728),
        'user' => env('MIKROTIK_USER', 'laravel-test'),
        'pass' => env('MIKROTIK_PASS', '12345'),
        'timeout' => (int) env('MIKROTIK_TIMEOUT', 10),
        'attempts' => (int) env('MIKROTIK_ATTEMPTS', 3),
        'delay' => (float) env('MIKROTIK_DELAY', 1.5),
        'default_profile' => env('MIKROTIK_DEFAULT_PROFILE', 'default'),
        'pending_profile' => env('MIKROTIK_PENDING_PROFILE', 'pending'),
        'legacy' => true, // Required for RouterOS v6 and below
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Mikrotik Configuration
    |--------------------------------------------------------------------------
    */
    'mikrotik_ssl' => [
        'enabled' => env('MIKROTIK_SSL_VERIFY', false),
        'port' => (int) env('MIKROTIK_SSL_PORT', 8729),
        'timeout' => (int) env('MIKROTIK_SSL_TIMEOUT', 10),
        'certificate_path' => env('MIKROTIK_SSL_CERTIFICATE_PATH'),
        'certificate_key_path' => env('MIKROTIK_SSL_CERTIFICATE_KEY_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY', 'Mid-server-_EY0Z***'),
        'client_key' => env('MIDTRANS_CLIENT_KEY', 'Mid-client-***'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized' => env('MIDTRANS_SANITIZED', true),
        'is_3ds' => env('MIDTRANS_3DS', true),
        'redirect' => [
            'finish' => env('MIDTRANS_REDIRECT_FINISH_URL', 'http://103.97.199.26:8000/payment-status-success'),
            'error' => env('MIDTRANS_REDIRECT_ERROR_URL', 'http://103.97.199.26:8000/payment-status-error'),
            'pending' => env('MIDTRANS_REDIRECT_PENDING_URL', 'http://103.97.199.26:8000/payment-status-pending'),
        ],
    ],

];
