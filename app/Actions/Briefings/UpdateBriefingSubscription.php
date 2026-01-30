<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingSchedulePreset;
use App\Models\BriefingSubscription;
use App\Services\Briefings\ValueObjects\BriefingDeliveryChannels;
use App\Services\Briefings\ValueObjects\BriefingParameters;

final readonly class UpdateBriefingSubscription
{
    /**
     * Update a briefing subscription.
     *
     * @param  BriefingSubscription  $subscription  The subscription to update
     * @param  BriefingSchedulePreset|null  $schedulePreset  The schedule preset to apply
     * @param  BriefingDeliveryChannels|null  $deliveryChannels  The delivery channels to apply
     * @param  BriefingParameters|null  $parameters  The parameters to apply
     * @param  int|null  $scheduleDay  Optional schedule day override
     * @param  int|null  $scheduleHour  Optional schedule hour override
     * @param  string|null  $slackWebhookUrl  Optional Slack webhook URL
     * @param  bool|null  $isActive  Optional activation flag
     * @return BriefingSubscription The updated subscription
     */
    public function handle(
        BriefingSubscription $subscription,
        ?BriefingSchedulePreset $schedulePreset = null,
        ?BriefingDeliveryChannels $deliveryChannels = null,
        ?BriefingParameters $parameters = null,
        ?int $scheduleDay = null,
        ?int $scheduleHour = null,
        ?string $slackWebhookUrl = null,
        ?bool $isActive = null,
    ): BriefingSubscription {
        $scheduleChanged = false;

        if ($schedulePreset instanceof BriefingSchedulePreset) {
            $subscription->schedule_preset = $schedulePreset;
            $scheduleChanged = true;
        }

        if ($scheduleDay !== null) {
            $subscription->schedule_day = $scheduleDay;
            $scheduleChanged = true;
        }

        if ($scheduleHour !== null) {
            $subscription->schedule_hour = $scheduleHour;
            $scheduleChanged = true;
        }

        if ($deliveryChannels instanceof BriefingDeliveryChannels) {
            $subscription->delivery_channels = $deliveryChannels->toArray();
        }

        if ($parameters instanceof BriefingParameters) {
            $subscription->parameters = $parameters->toArray();
        }

        if ($slackWebhookUrl !== null) {
            $subscription->slack_webhook_url = $slackWebhookUrl;
        }

        if ($isActive !== null) {
            $subscription->is_active = $isActive;
        }

        if ($scheduleChanged) {
            $subscription->next_scheduled_at = $subscription->calculateNextScheduledAt();
        }

        $subscription->save();

        return $subscription;
    }
}
