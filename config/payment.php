<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option defines the default payment gateway that will be used
    | when no specific gateway is selected. Valid values are 'cinetpay'
    | and 'tranzak'.
    |
    */

    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'cinetpay'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the payment gateways that your application
    | supports. Each gateway has its own set of credentials and settings.
    |
    */

    'gateways' => [

        'cinetpay' => [
            'api_key' => env('CINETPAY_API_KEY'),
            'site_id' => env('CINETPAY_SITE_ID'),
            'secret_key' => env('CINETPAY_SECRET_KEY'),
            'currency' => env('CINETPAY_CURRENCY', 'XOF'),
            'notify_url' => env('CINETPAY_NOTIFY_URL', env('APP_URL') . '/api/cinetpay/callback'),
            'return_url' => env('CINETPAY_RETURN_URL', env('APP_URL') . '/payment/return'),
            'timeout' => env('CINETPAY_TIMEOUT', 30),
            'retry_attempts' => env('CINETPAY_RETRY_ATTEMPTS', 3),
            'retry_delays' => [1, 2, 4], // Exponential backoff in seconds
        ],

        'tranzak' => [
            'api_key' => env('TRANZAK_API_KEY'),
            'app_id' => env('TRANZAK_APP_ID'),
            'currency' => env('TRANZAK_CURRENCY', 'XAF'),
            'base_url' => env('TRANZAK_BASE_URL', 'https://dsapi.tranzak.me'),
            'notify_url' => env('TRANZAK_NOTIFY_URL', env('APP_URL') . '/api/tranzak/callback'),
            'return_url' => env('TRANZAK_RETURN_URL', env('APP_URL') . '/payment/return'),
            'timeout' => env('TRANZAK_TIMEOUT', 30),
            'retry_attempts' => env('TRANZAK_RETRY_ATTEMPTS', 3),
            'retry_delays' => [1, 2, 4], // Exponential backoff in seconds
        ],

    ],

];
