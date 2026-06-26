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

    'iae' => [
        'service_name' => env('IAE_SERVICE_NAME', 'Checkout-Order-Service'),
        'api_version' => env('IAE_API_VERSION', 'v1'),
        'api_key' => env('IAE_API_KEY', '102022400268'),
    ],

    'integrations' => [
        'cart_promo_url' => env('CART_PROMO_SERVICE_URL', 'http://cart-promo-app:8000'),
        'cart_promo_api_key' => env('CART_PROMO_API_KEY', env('IAE_API_KEY', '102022400268')),
        'product_url' => env('PRODUCT_SERVICE_URL', 'http://product-stock-app:8000'),
        'product_api_key' => env('PRODUCT_SERVICE_API_KEY', env('IAE_API_KEY', '102022400268')),
        'validate_stock' => env('PRODUCT_STOCK_VALIDATION', false),
        'deduct_stock' => env('PRODUCT_STOCK_DEDUCTION', false),
        'timeout' => env('SERVICE_HTTP_TIMEOUT', 5),
    ],

];
