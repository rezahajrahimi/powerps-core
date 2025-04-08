<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cryptomus API Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are used to authenticate with the Cryptomus API.
    | You can obtain your Merchant ID and API Key from your Cryptomus account.
    |
    */

    'merchant_id' => env('CRYPTOMUS_MERCHANT_ID'),
    'api_key' => env('CRYPTOMUS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Cryptomus API Base URL
    |--------------------------------------------------------------------------
    |
    | This is the base URL for the Cryptomus API endpoints.
    |
    */

    'base_url' => 'https://api.cryptomus.com/v1',

    /*
    |--------------------------------------------------------------------------
    | Payment Confirmation Timeout (in seconds)
    |--------------------------------------------------------------------------
    |
    | Define how long to wait for payment confirmation before considering it timed out.
    | Cryptomus might have its own timeout, but this can be used for internal checks.
    | Default: 3600 seconds (1 hour)
    |
    */
    'payment_timeout' => env('CRYPTOMUS_PAYMENT_TIMEOUT', 3600),

];
