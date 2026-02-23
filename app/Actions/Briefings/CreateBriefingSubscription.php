<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Enums\Briefings\BriefingSchedulePreset;
use App\Models\Briefing;
use App\Models\BriefingSubscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Briefings\BriefingParameterValidator;
use App\Services\Briefings\ValueObjects\BriefingDeliveryChannels;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final readonly class CreateBriefingSubscription
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private BriefingParameterValidator $parameterValidator,
    ) {}

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
     * @return BriefingSubscription The created subscription
     *
     * @throws ValidationException When a subscription already exists for this user and briefing
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
    ): BriefingSubscription {
        if (! $briefing->is_schedulable) {
            throw ValidationException::withMessages([
                'briefing_id' => ['This briefing does not support scheduling.'],
            ]);
        }

        $validatedParameters = $this->parameterValidator->validate(
            $briefing,
            $parameters->toArray(),
        );

        $subscription = new BriefingSubscription([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'briefing_id' => $briefing->id,
            'schedule_preset' => $schedulePreset,
            'schedule_day' => $scheduleDay,
            'schedule_hour' => $scheduleHour,
            'parameters' => $validatedParameters->toArray(),
            'delivery_channels' => $deliveryChannels->toArray(),
            'is_active' => true,
        ]);

        $subscription->next_scheduled_at = $subscription->calculateNextScheduledAt();

        try {
            $subscription->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'briefing_id' => ['You already have a subscription for this briefing in this workspace.'],
            ]);
        }

        return $subscription;
    }
}
