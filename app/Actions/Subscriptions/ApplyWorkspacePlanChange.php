<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Activities\LogActivity;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Workspace\ActivityType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final readonly class ApplyWorkspacePlanChange
{
    /**
     * Create a new action instance.
     */
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Apply an active plan to a workspace and create the corresponding subscription record.
     */
    public function applyActivePlan(
        Workspace $workspace,
        Plan $plan,
        ActivityType $activityType,
        ?User $actor,
    ): Subscription {
        return DB::transaction(function () use ($workspace, $plan, $activityType, $actor): Subscription {
            $workspace->forceFill([
                'plan_id' => $plan->id,
                'subscription_status' => SubscriptionStatus::Active,
            ])->save();

            $periodStart = now();
            $periodEnd = now()->addMonth();

            $subscription = Subscription::query()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'started_at' => now(),
                'ends_at' => null,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
            ]);

            $description = sprintf(
                'Subscription %s to %s plan',
                $activityType === ActivityType::SubscriptionDowngraded ? 'downgraded' : 'upgraded',
                ucfirst($plan->tier)
            );

            $this->logActivity->handle(
                workspace: $workspace,
                type: $activityType,
                description: $description,
                actor: $actor,
                subject: $subscription,
                metadata: ['plan_tier' => $plan->tier],
            );

            return $subscription;
        });
    }

    /**
     * Cancel a subscription and transition the workspace to the Foundation plan.
     */
    public function cancelToFoundation(
        Workspace $workspace,
        Plan $foundationPlan,
        ?Subscription $latestSubscription,
        ?User $actor,
    ): Subscription {
        return DB::transaction(function () use ($workspace, $foundationPlan, $latestSubscription, $actor): Subscription {
            $workspace->forceFill([
                'plan_id' => $foundationPlan->id,
                'subscription_status' => SubscriptionStatus::Canceled,
            ])->save();

            if ($latestSubscription instanceof Subscription) {
                $latestSubscription->forceFill([
                    'status' => SubscriptionStatus::Canceled,
                    'ends_at' => now(),
                ])->save();

                $subscription = $latestSubscription;
            } else {
                $subscription = Subscription::query()->create([
                    'workspace_id' => $workspace->id,
                    'plan_id' => $foundationPlan->id,
                    'status' => SubscriptionStatus::Canceled,
                    'started_at' => now(),
                    'ends_at' => now(),
                ]);
            }

            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::SubscriptionCanceled,
                description: 'Subscription canceled and downgraded to Foundation plan',
                actor: $actor,
                subject: $subscription,
                metadata: ['plan_tier' => $foundationPlan->tier],
            );

            return $subscription;
        });
    }
}
