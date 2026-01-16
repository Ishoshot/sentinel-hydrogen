<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\BriefingDeliveryChannel;
use App\Enums\BriefingSchedulePreset;
use App\Models\BriefingSubscription;

final readonly class UpdateBriefingSubscription
{
    /**
     * @param  array<BriefingDeliveryChannel>|null  $deliveryChannels
     * @param  array<string, mixed>|null  $parameters
     */
    public function handle(
        BriefingSubscription $subscription,
        ?BriefingSchedulePreset $schedulePreset = null,
        ?array $deliveryChannels = null,
        ?array $parameters = null,
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

        if ($deliveryChannels !== null) {
            $subscription->delivery_channels = array_map(
                fn (BriefingDeliveryChannel $channel): string => $channel->value,
                $deliveryChannels
            );
        }

        if ($parameters !== null) {
            $subscription->parameters = $parameters;
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
