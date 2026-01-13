<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\PlanTier;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PlanDefaults;
use Illuminate\Support\Facades\DB;

final readonly class CancelSubscription
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Cancel a workspace subscription and downgrade to Foundation plan.
     */
    public function handle(Workspace $workspace, ?User $actor = null): Subscription
    {
        $plan = Plan::query()->firstOrCreate(
            ['tier' => PlanTier::Foundation->value],
            PlanDefaults::forTier(PlanTier::Foundation)
        );

        return DB::transaction(function () use ($workspace, $plan, $actor): Subscription {
            $workspace->forceFill([
                'plan_id' => $plan->id,
                'subscription_status' => SubscriptionStatus::Canceled,
            ])->save();

            $subscription = Subscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Canceled,
                'started_at' => now(),
                'ends_at' => now(),
            ]);

            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::SubscriptionCanceled,
                description: 'Subscription canceled and downgraded to Foundation plan',
                actor: $actor,
                subject: $subscription,
                metadata: ['plan_tier' => $plan->tier],
            );

            return $subscription;
        });
    }
}
