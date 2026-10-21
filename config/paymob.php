<?php

return [

    /*
    | Paymob Intention API settings. Read env here so runtime services can use
    | config() and still boot when a deployment has not configured payments.
    */

    'base_url' => env('PAYMOB_BASE_URL'),

    'public_key' => env('PAYMOB_PUBLIC_KEY'),

    'secret_key' => env('PAYMOB_SECRET_KEY'),

    'api_key' => env('PAYMOB_API_KEY'),

    'hmac_secret' => env('PAYMOB_HMAC_SECRET'),

    'integration_id' => env('PAYMOB_INTEGRATION_ID'),

    'iframe_id' => env('PAYMOB_IFRAME_ID'),

    'expiration_seconds' => (int) env('PAYMOB_EXPIRATION_SECONDS', 30),

    'callback_url' => env('PAYMOB_CALLBACK_URL'),

    'redirect_url' => env('PAYMOB_REDIRECT_URL'),

];
