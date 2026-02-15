<?php

declare(strict_types=1);

namespace App\Services\Slack\Clients;

use App\Models\SlackIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends messages to Slack channels via the API.
 */
final readonly class SlackMessageClient
{
    /**
     * Send a message to a Slack channel.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function send(SlackIntegration $integration, string $channelId, string $text, array $blocks = []): void
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
     * Build a full Slack API URL.
     */
    private function apiUrl(string $method): string
    {
        return mb_rtrim(config('slack.api_base_url'), '/').'/'.$method;
    }
}
