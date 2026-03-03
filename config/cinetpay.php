<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CinetPay API Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are required to communicate with the CinetPay API.
    | They should be stored in your .env file and never committed to version control.
    |
    */

    'api_key' => env('CINETPAY_API_KEY'),
    'site_id' => env('CINETPAY_SITE_ID'),
    'secret_key' => env('CINETPAY_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Default currency and URLs for payment processing.
    |
    */

    'currency' => env('CINETPAY_CURRENCY', 'XOF'),
    'notify_url' => env('CINETPAY_NOTIFY_URL'),
    'return_url' => env('CINETPAY_RETURN_URL'),

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Timeout and retry settings for API calls.
    |
    */

    'timeout' => env('CINETPAY_TIMEOUT', 30),
    'retry_attempts' => env('CINETPAY_RETRY_ATTEMPTS', 3),
    'retry_delays' => [1, 2, 4], // Exponential backoff in seconds

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | List of required configuration keys that must be present.
    | The PaymentService will validate these at runtime.
    |
    */

    'required_keys' => [
        'api_key',
        'site_id',
        'secret_key',
        'notify_url',
        'return_url',
    ],

];
