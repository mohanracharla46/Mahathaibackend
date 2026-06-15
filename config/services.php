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

    'uber_direct' => [
        'enabled' => env('UBER_DIRECT_ENABLED', false),
        'base_url' => env('UBER_DIRECT_BASE_URL', 'https://api.uber.com'),
        'oauth_url' => env('UBER_DIRECT_OAUTH_URL', 'https://login.uber.com/oauth/v2/token'),
        'customer_id' => env('UBER_DIRECT_CUSTOMER_ID'),
        'client_id' => env('UBER_DIRECT_CLIENT_ID'),
        'client_secret' => env('UBER_DIRECT_CLIENT_SECRET'),
        'scope' => env('UBER_DIRECT_SCOPE', 'eats.deliveries'),
        'webhook_secret' => env('UBER_DIRECT_WEBHOOK_SECRET'),
        'pickup_name' => env('UBER_DIRECT_PICKUP_NAME', 'Maha Thai'),
        'pickup_business_name' => env('UBER_DIRECT_PICKUP_BUSINESS_NAME', 'Maha Thai'),
        'pickup_phone' => env('UBER_DIRECT_PICKUP_PHONE'),
        'pickup_address' => env('UBER_DIRECT_PICKUP_ADDRESS'),
        'pickup_notes' => env('UBER_DIRECT_PICKUP_NOTES'),
        'external_store_id' => env('UBER_DIRECT_EXTERNAL_STORE_ID'),
        'country' => env('UBER_DIRECT_COUNTRY', 'US'),
    ],
];
