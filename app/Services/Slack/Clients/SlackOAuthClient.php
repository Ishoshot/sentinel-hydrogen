<?php

declare(strict_types=1);

namespace App\Services\Slack\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles Slack OAuth token exchange using client credentials.
 */
final readonly class SlackOAuthClient
{
    /**
     * Exchange an authorization code for an access token.
     *
     * @return array{access_token: string, bot_user_id: string, team: array{id: string, name: string}, scope: string, authed_user: array{id: string}}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post(config('slack.oauth_access_url'), [
            'client_id' => config('slack.client_id'),
            'client_secret' => config('slack.client_secret'),
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $response->throw();

        /** @var array<string, mixed> $data */
        $data = $response->json();

        if (($data['ok'] ?? false) !== true) {
            /** @var string $error */
            $error = $data['error'] ?? 'unknown_error';
            Log::error('Slack OAuth token exchange failed', ['error' => $error]);

            throw new RuntimeException('Slack OAuth failed: '.$error);
        }

        /** @var array{id: string, name: string} $team */
        $team = $data['team'] ?? ['id' => '', 'name' => ''];

        /** @var array{id: string} $authedUser */
        $authedUser = $data['authed_user'] ?? ['id' => ''];

        return [
            'access_token' => (string) ($data['access_token'] ?? ''),
            'bot_user_id' => (string) ($data['bot_user_id'] ?? ''),
            'team' => $team,
            'scope' => (string) ($data['scope'] ?? ''),
            'authed_user' => $authedUser,
        ];
    }
}
