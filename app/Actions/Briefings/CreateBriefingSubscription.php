<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingSchedulePreset;
use App\Models\Briefing;
use App\Models\BriefingSubscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingDeliveryChannels;
use App\Services\Briefings\ValueObjects\BriefingParameters;

final readonly class CreateBriefingSubscription
{
    /**
     * Create a briefing subscription for a workspace member.
     *
     * @param  Workspace  $workspace  The workspace that owns the subscription
     * @param  User  $user  The member creating the subscription
     * @param  Briefing  $briefing  The briefing template to subscribe to
     * @param  BriefingSchedulePreset  $schedulePreset  The schedule preset
     * @param  BriefingDeliveryChannels  $deliveryChannels  Delivery channels for the briefing
     * @param  BriefingParameters  $parameters  Parameters for the briefing run
     * @param  int|null  $scheduleDay  Optional schedule day override
     * @param  int  $scheduleHour  The hour of day to schedule deliveries
     * @param  string|null  $slackWebhookUrl  Optional Slack webhook URL
     * @return BriefingSubscription The created subscription
     */
    public function handle(
        Workspace $workspace,
        User $user,
        Briefing $briefing,
        BriefingSchedulePreset $schedulePreset,
        BriefingDeliveryChannels $deliveryChannels,
        BriefingParameters $parameters,
        ?int $scheduleDay = null,
        int $scheduleHour = 9,
        ?string $slackWebhookUrl = null,
    ): BriefingSubscription {
        $subscription = new BriefingSubscription([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'briefing_id' => $briefing->id,
            'schedule_preset' => $schedulePreset,
            'schedule_day' => $scheduleDay,
            'schedule_hour' => $scheduleHour,
            'parameters' => $parameters->toArray(),
            'delivery_channels' => $deliveryChannels->toArray(),
            'slack_webhook_url' => $slackWebhookUrl,
            'is_active' => true,
        ]);

        $subscription->next_scheduled_at = $subscription->calculateNextScheduledAt();
        $subscription->save();

        return $subscription;
    }
}
