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
        'team_id' => env('IAE_TEAM_ID', 'TEAM-01'),
    ],

    'central' => [
        'base_url' => env('IAE_CENTRAL_BASE_URL', 'https://iae-sso.virtualfri.id'),
        'api_key' => env('IAE_CENTRAL_API_KEY'),
        'bearer_token' => env('IAE_CENTRAL_BEARER_TOKEN'),
        'token_url' => env('IAE_CENTRAL_TOKEN_URL', 'https://iae-sso.virtualfri.id/api/v1/auth/token'),
        'timeout' => env('IAE_CENTRAL_TIMEOUT', env('SERVICE_HTTP_TIMEOUT', 5)),
    ],

    'sso' => [
        'enabled' => env('SSO_ENABLED', false),
        'provider' => env('SSO_PROVIDER', 'cloud-dosen'),
        'base_url' => env('SSO_BASE_URL', 'https://iae-sso.virtualfri.id'),
        'algorithm' => env('SSO_JWT_ALGORITHM', 'RS256'),
        'verify_signature' => env('SSO_VERIFY_SIGNATURE', true),
        'jwt_secret' => env('SSO_JWT_SECRET'),
        'jwt_public_key' => env('SSO_JWT_PUBLIC_KEY'),
        'jwks_url' => env('SSO_JWKS_URL', 'https://iae-sso.virtualfri.id/api/v1/auth/jwks'),
        'issuer' => env('SSO_ISSUER'),
        'audience' => env('SSO_AUDIENCE'),
        'role_claim' => env('SSO_ROLE_CLAIM', 'role'),
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

    'legacy_audit' => [
        'enabled' => env('LEGACY_AUDIT_ENABLED', false),
        'endpoint' => env('LEGACY_AUDIT_ENDPOINT', 'https://iae-sso.virtualfri.id/soap/v1/audit'),
        'soap_action' => env('LEGACY_AUDIT_SOAP_ACTION', 'SubmitOrderAudit'),
        'activity_name' => env('LEGACY_AUDIT_ACTIVITY_NAME', 'CheckoutOrderCreated'),
        'timeout' => env('LEGACY_AUDIT_TIMEOUT', env('SERVICE_HTTP_TIMEOUT', 5)),
    ],

    'rabbitmq' => [
        'enabled' => env('RABBITMQ_ENABLED', false),
        'publish_url' => env('RABBITMQ_PUBLISH_URL', 'https://iae-sso.virtualfri.id/api/v1/messages/publish'),
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'iae.central.exchange'),
        'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'topic'),
        'routing_key' => env('RABBITMQ_ROUTING_KEY', 'checkout.order.created'),
    ],

];
