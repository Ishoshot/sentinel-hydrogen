<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\BriefingDeliveryChannel;
use App\Enums\BriefingSchedulePreset;
use App\Models\Briefing;
use App\Models\BriefingSubscription;
use App\Models\User;
use App\Models\Workspace;

final readonly class CreateBriefingSubscription
{
    /**
     * @param  array<BriefingDeliveryChannel>  $deliveryChannels
     * @param  array<string, mixed>  $parameters
     */
    public function handle(
        Workspace $workspace,
        User $user,
        Briefing $briefing,
        BriefingSchedulePreset $schedulePreset,
        array $deliveryChannels,
        array $parameters = [],
        ?int $scheduleDay = null,
        int $scheduleHour = 9,
        ?string $slackWebhookUrl = null,
    ): BriefingSubscription {
        $channelValues = array_map(
            fn (BriefingDeliveryChannel $channel): string => $channel->value,
            $deliveryChannels
        );

        $subscription = new BriefingSubscription([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'briefing_id' => $briefing->id,
            'schedule_preset' => $schedulePreset,
            'schedule_day' => $scheduleDay,
            'schedule_hour' => $scheduleHour,
            'parameters' => $parameters,
            'delivery_channels' => $channelValues,
            'slack_webhook_url' => $slackWebhookUrl,
            'is_active' => true,
        ]);

        $subscription->next_scheduled_at = $subscription->calculateNextScheduledAt();
        $subscription->save();

        return $subscription;
    }
}
