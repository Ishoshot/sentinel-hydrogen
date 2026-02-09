<?php

declare(strict_types=1);

return [
    'client_id' => env('SLACK_CLIENT_ID'),
    'client_secret' => env('SLACK_CLIENT_SECRET'),
    'redirect_uri' => env('SLACK_REDIRECT_URI'),
    'scopes' => env('SLACK_SCOPES', 'chat:write,channels:read'),
    'oauth_authorize_url' => 'https://slack.com/oauth/v2/authorize',
    'oauth_access_url' => 'https://slack.com/api/oauth.v2.access',
    'api_base_url' => 'https://slack.com/api',
];
