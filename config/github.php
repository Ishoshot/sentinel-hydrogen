<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub App Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GitHub App integration including App ID, private key
    | for JWT authentication, and webhook secret for signature verification.
    |
    */

    'app_id' => env('GITHUB_APP_ID'),

    'app_name' => env('GITHUB_APP_NAME'),

    'private_key' => env('GITHUB_PRIVATE_KEY'),

    'private_key_path' => env('GITHUB_PRIVATE_KEY_PATH', storage_path('app/github/private-key.pem')),

    'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Installation Token Cache
    |--------------------------------------------------------------------------
    |
    | Installation access tokens are valid for 1 hour. We cache them for
    | 50 minutes to ensure they're refreshed before expiration.
    |
    */

    'token_cache_ttl' => env('GITHUB_TOKEN_CACHE_TTL', 3000), // 50 minutes in seconds

];
