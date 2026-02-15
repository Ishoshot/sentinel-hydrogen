<?php

declare(strict_types=1);

namespace App\Services\Slack\Clients;

use App\Models\SlackIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lists public Slack channels accessible to the bot.
 */
final readonly class SlackChannelClient
{
    /**
     * List public channels for the given integration.
     *
     * @return array<int, array{id: string, name: string, is_member: bool, num_members: int}>
     */
    public function list(SlackIntegration $integration): array
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
     * Build a full Slack API URL.
     */
    private function apiUrl(string $method): string
    {
        return mb_rtrim(config('slack.api_base_url'), '/').'/'.$method;
    }
}
