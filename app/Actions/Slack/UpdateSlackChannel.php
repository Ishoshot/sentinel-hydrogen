<?php

declare(strict_types=1);

namespace App\Actions\Slack;

use App\Models\SlackIntegration;

final readonly class UpdateSlackChannel
{
    /**
     * Update the default channel for a Slack integration.
     */
    public function handle(SlackIntegration $integration, string $channelId, string $channelName): SlackIntegration
    {
        $integration->update([
            'channel_id' => $channelId,
            'channel_name' => $channelName,
        ]);

        return $integration->refresh();
    }
}
