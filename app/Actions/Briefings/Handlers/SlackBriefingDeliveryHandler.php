<?php

declare(strict_types=1);

namespace App\Actions\Briefings\Handlers;

use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles briefing delivery via Slack message.
 */
final readonly class SlackBriefingDeliveryHandler
{
    /**
     * Deliver a generated briefing into the configured Slack channel.
     */
    public function deliver(
        BriefingGeneration $generation,
        BriefingSubscription $subscription,
        SlackServiceContract $slackService,
    ): void {
        $subscription->loadMissing('workspace.slackIntegration');
        $slackIntegration = $subscription->workspace?->slackIntegration;

        if ($slackIntegration === null || ! $slackIntegration->isFullyConfigured()) {
            Log::warning('Cannot deliver briefing via Slack - no fully configured Slack integration', [
                'subscription_id' => $subscription->id,
                'workspace_id' => $subscription->workspace_id,
            ]);

            return;
        }

        $generation->loadMissing('briefing');

        $excerpt = $generation->excerpts['slack'] ?? $generation->excerpts['short'] ?? null;

        if ($excerpt === null) {
            Log::warning('Cannot deliver briefing via Slack - no excerpt available', [
                'generation_id' => $generation->id,
            ]);

            return;
        }

        /** @var string $channelId */
        $channelId = $slackIntegration->channel_id;

        try {
            $slackService->sendMessage(
                integration: $slackIntegration,
                channelId: $channelId,
                text: $generation->briefing?->title ?? 'Briefing Ready',
                blocks: [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => $excerpt,
                        ],
                    ],
                ],
            );

            Log::info('Briefing delivered via Slack', [
                'generation_id' => $generation->id,
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to deliver briefing via Slack', [
                'generation_id' => $generation->id,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }
}
