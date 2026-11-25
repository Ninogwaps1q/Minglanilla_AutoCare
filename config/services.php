<?php

return [
    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
        'daily_limit' => (int) env('SMS_DAILY_LIMIT', 5),
    ],

    'web_push' => [
        'public_key' => env('WEB_PUSH_PUBLIC_KEY'),
        'private_key' => env('WEB_PUSH_PRIVATE_KEY'),
        'subject' => env('WEB_PUSH_SUBJECT'),
    ],

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

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'payment_methods' => array_values(array_filter(explode(',', env('PAYMONGO_PAYMENT_METHODS', 'card,gcash,paymaya,grab_pay,shopee_pay,qrph')))),
        'method_labels' => ['card' => 'Card', 'gcash' => 'GCash', 'paymaya' => 'Maya', 'grab_pay' => 'GrabPay', 'shopeepay' => 'ShopeePay', 'shopee_pay' => 'ShopeePay', 'qrph' => 'QR Ph'],
        'legacy_manual_payments' => env('PAYMONGO_LEGACY_MANUAL_PAYMENTS', false),
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => rtrim(env('APP_URL', 'http://localhost'), '/').'/auth/google/callback',
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => rtrim(env('APP_URL', 'http://localhost'), '/').'/auth/facebook/callback',
    ],

];
