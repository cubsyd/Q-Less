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

    'openai' => [
        'provider' => env('CHATBOT_PROVIDER', env('OPENAI_API_KEY') ? 'openai' : 'local'),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => env('OPENAI_TIMEOUT', 25),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
    ],

    'app_urls' => [
        'backend_url' => env('BACKEND_URL', env('APP_URL', 'http://localhost:8000')),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:4200'),
    ],

    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'currency' => env('MERCADOPAGO_CURRENCY', 'COP'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:4200'),
        'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL'),
        'simulated' => env('MERCADOPAGO_SIMULATED', env('MERCADOPAGO_ACCESS_TOKEN') ? false : true),
    ],

];
