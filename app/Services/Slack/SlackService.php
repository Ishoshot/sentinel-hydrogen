<?php

declare(strict_types=1);

namespace App\Services\Slack;

use App\Models\SlackIntegration;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class SlackService implements SlackServiceContract
{
    /**
     * {@inheritDoc}
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

    /**
     * {@inheritDoc}
     */
    public function sendMessage(SlackIntegration $integration, string $channelId, string $text, array $blocks = []): void
    {
        $payload = [
            'channel' => $channelId,
            'text' => $text,
        ];

        if ($blocks !== []) {
            $payload['blocks'] = $blocks;
        }

        /** @var string $token */
        $token = $integration->access_token;

        $response = Http::withToken($token)
            ->post($this->apiUrl('chat.postMessage'), $payload);

        /** @var array<string, mixed> $data */
        $data = $response->json();

        if ($response->failed() || ($data['ok'] ?? false) !== true) {
            Log::error('Slack message delivery failed', [
                'status' => $response->status(),
                'error' => $data['error'] ?? 'unknown',
            ]);

            $response->throw();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function listChannels(SlackIntegration $integration): array
    {
        /** @var string $token */
        $token = $integration->access_token;

        $response = Http::withToken($token)
            ->get($this->apiUrl('conversations.list'), [
                'types' => 'public_channel',
                'exclude_archived' => true,
                'limit' => 200,
            ]);

        $response->throw();

        /** @var array<string, mixed> $data */
        $data = $response->json();

        if (($data['ok'] ?? false) !== true) {
            Log::error('Failed to list Slack channels', [
                'error' => $data['error'] ?? 'unknown',
            ]);

            return [];
        }

        /** @var array<int, array<string, mixed>> $channels */
        $channels = $data['channels'] ?? [];

        return array_map(fn (array $channel): array => [
            'id' => (string) $channel['id'],
            'name' => (string) $channel['name'],
            'is_member' => (bool) ($channel['is_member'] ?? false),
            'num_members' => (int) ($channel['num_members'] ?? 0),
        ], $channels);
    }

    /**
     * {@inheritDoc}
     */
    public function testConnection(SlackIntegration $integration): bool
    {
        return $this->postAuthEndpoint($integration, 'auth.test', 'Slack connection test failed');
    }

    /**
     * {@inheritDoc}
     */
    public function revokeToken(SlackIntegration $integration): bool
    {
        return $this->postAuthEndpoint($integration, 'auth.revoke', 'Slack token revocation failed');
    }

    /**
     * Post to a Slack auth endpoint and return whether the call succeeded.
     */
    private function postAuthEndpoint(SlackIntegration $integration, string $method, string $failureMessage): bool
    {
        try {
            /** @var string $token */
            $token = $integration->access_token;

            $response = Http::withToken($token)
                ->post($this->apiUrl($method));

            /** @var array<string, mixed> $data */
            $data = $response->json();

            return $response->successful() && ($data['ok'] ?? false) === true;
        } catch (Throwable $throwable) {
            Log::warning($failureMessage, [
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build a full Slack API URL.
     */
    private function apiUrl(string $method): string
    {
        return mb_rtrim(config('slack.api_base_url'), '/').'/'.$method;
    }
}
