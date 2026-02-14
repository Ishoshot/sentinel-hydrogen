<?php

declare(strict_types=1);

namespace App\Services\Slack;

use App\Models\SlackIntegration;
use App\Services\Slack\Contracts\SlackServiceContract;
use App\Services\Slack\Support\SlackChannelLister;
use App\Services\Slack\Support\SlackMessageSender;
use App\Services\Slack\Support\SlackOAuthClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SlackService implements SlackServiceContract
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private SlackOAuthClient $oauthClient = new SlackOAuthClient,
        private SlackMessageSender $messageSender = new SlackMessageSender,
        private SlackChannelLister $channelLister = new SlackChannelLister,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        return $this->oauthClient->exchangeCodeForToken($code, $redirectUri);
    }

    /**
     * {@inheritDoc}
     */
    public function sendMessage(SlackIntegration $integration, string $channelId, string $text, array $blocks = []): void
    {
        $this->messageSender->send($integration, $channelId, $text, $blocks);
    }

    /**
     * {@inheritDoc}
     */
    public function listChannels(SlackIntegration $integration): array
    {
        return $this->channelLister->list($integration);
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
