<?php

declare(strict_types=1);

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

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URL', '/auth/github/callback'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL', '/auth/google/callback'),
    ],

    'polar' => [
        'api_url' => env('POLAR_API_URL', 'https://sandbox-api.polar.sh/v1'),
        'access_token' => env('POLAR_ACCESS_TOKEN'),
        'product_ids' => [
            'monthly' => [
                'illuminate' => env('POLAR_PRODUCT_ILLUMINATE_MONTHLY'),
                'orchestrate' => env('POLAR_PRODUCT_ORCHESTRATE_MONTHLY'),
                'sanctum' => env('POLAR_PRODUCT_SANCTUM_MONTHLY'),
            ],
            'yearly' => [
                'illuminate' => env('POLAR_PRODUCT_ILLUMINATE_YEARLY'),
                'orchestrate' => env('POLAR_PRODUCT_ORCHESTRATE_YEARLY'),
                'sanctum' => env('POLAR_PRODUCT_SANCTUM_YEARLY'),
            ],
        ],
        'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),
    ],

];
