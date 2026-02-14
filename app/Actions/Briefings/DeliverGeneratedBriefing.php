<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Actions\Briefings\Support\EmailBriefingDeliverer;
use App\Actions\Briefings\Support\SlackBriefingDeliverer;
use App\Enums\Briefings\BriefingDeliveryChannel;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Support\Facades\Log;

final class DeliverGeneratedBriefing
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly EmailBriefingDeliverer $emailDeliverer = new EmailBriefingDeliverer,
        private readonly SlackBriefingDeliverer $slackDeliverer = new SlackBriefingDeliverer,
    ) {}

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
            BriefingDeliveryChannel::Email => $this->emailDeliverer->deliver($generation, $subscription),
            BriefingDeliveryChannel::Slack => $this->slackDeliverer->deliver($generation, $subscription, $slackService),
            BriefingDeliveryChannel::Push => $this->deliverViaPush($generation, $subscription),
        };
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
