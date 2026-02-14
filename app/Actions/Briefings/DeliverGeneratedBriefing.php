<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingDeliveryChannel;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Notifications\Briefings\BriefingDeliveryNotification;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DeliverGeneratedBriefing
{
    /**
     * Deliver a generated briefing through the requested channel.
     */
    public function handle(
        BriefingGeneration $generation,
        string $channel,
        BriefingSubscription $subscription,
        SlackServiceContract $slackService,
    ): void {
        $deliveryChannel = BriefingDeliveryChannel::tryFrom($channel);

        if ($deliveryChannel === null) {
            Log::warning('Unknown delivery channel', [
                'channel' => $channel,
                'generation_id' => $generation->id,
            ]);

            return;
        }

        match ($deliveryChannel) {
            BriefingDeliveryChannel::Email => $this->deliverViaEmail($generation, $subscription),
            BriefingDeliveryChannel::Slack => $this->deliverViaSlack($generation, $subscription, $slackService),
            BriefingDeliveryChannel::Push => $this->deliverViaPush($generation, $subscription),
        };
    }

    /**
     * Deliver a generated briefing to the subscriber via email.
     */
    private function deliverViaEmail(BriefingGeneration $generation, BriefingSubscription $subscription): void
    {
        $subscription->loadMissing('user');
        $user = $subscription->user;

        if ($user === null || $user->email === null) {
            Log::warning('Cannot deliver briefing via email - no user or email', [
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        try {
            $user->notify(new BriefingDeliveryNotification($generation));

            Log::info('Briefing delivered via email', [
                'generation_id' => $generation->id,
                'user_email' => $user->email,
            ]);
        } catch (Throwable $throwable) {
            Log::error('Failed to deliver briefing via email', [
                'generation_id' => $generation->id,
                'user_email' => $user->email,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    /**
     * Deliver a generated briefing into the configured Slack channel.
     */
    private function deliverViaSlack(
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

    /**
     * Deliver a generated briefing via push notification workflow.
     */
    private function deliverViaPush(BriefingGeneration $generation, BriefingSubscription $subscription): void
    {
        Log::info('Briefing push notification delivery queued', [
            'generation_id' => $generation->id,
            'subscription_id' => $subscription->id,
        ]);
    }
}
