<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
    | Service Lain
    |--------------------------------------------------------------------------
    |
    | Alamat dan timeout setiap service dibaca dari environment variable.
    | Timeout dipakai oleh lapisan client dan tidak boleh melebihi 5 detik.
    |
    */

    'product' => [
        'url' => env('PRODUCT_SERVICE_BASE_URL', 'http://localhost:3000'),
        'timeout' => (int) env('PRODUCT_SERVICE_TIMEOUT', 5),
    ],

    'cart' => [
        'url' => env('CART_SERVICE_BASE_URL', 'http://localhost:3001'),
        'timeout' => (int) env('CART_SERVICE_TIMEOUT', 5),
    ],

];
