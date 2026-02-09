<?php

declare(strict_types=1);

namespace App\Services\Slack\Contracts;

use App\Models\SlackIntegration;

interface SlackServiceContract
{
    /**
     * Exchange an OAuth authorization code for an access token.
     *
     * @return array{access_token: string, bot_user_id: string, team: array{id: string, name: string}, scope: string, authed_user: array{id: string}}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array;

    /**
     * Send a message to a Slack channel via the API.
     *
     * @param  array<int, array<string, mixed>>  $blocks  Optional Block Kit blocks
     */
    public function sendMessage(SlackIntegration $integration, string $channelId, string $text, array $blocks = []): void;

    /**
     * List public channels accessible to the bot.
     *
     * @return array<int, array{id: string, name: string, is_member: bool, num_members: int}>
     */
    public function listChannels(SlackIntegration $integration): array;

    /**
     * Test the connection by calling auth.test.
     */
    public function testConnection(SlackIntegration $integration): bool;

    /**
     * Revoke the bot token.
     */
    public function revokeToken(SlackIntegration $integration): bool;
}
