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

final readonly class CreateSubscription
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Create a subscription for a workspace.
     */
    public function handle(
        Workspace $workspace,
        PlanTier $tier = PlanTier::Foundation,
        ?User $actor = null,
        SubscriptionStatus $status = SubscriptionStatus::Active,
    ): Subscription {
        $plan = Plan::query()->firstOrCreate(
            ['tier' => $tier->value],
            PlanDefaults::forTier($tier)
        );

        return DB::transaction(function () use ($workspace, $plan, $status, $actor): Subscription {
            $workspace->forceFill([
                'plan_id' => $plan->id,
                'subscription_status' => $status,
            ])->save();

            $subscription = Subscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'started_at' => now(),
                'ends_at' => null,
            ]);

            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::SubscriptionCreated,
                description: sprintf('Subscription created: %s plan', ucfirst($plan->tier)),
                actor: $actor,
                subject: $subscription,
                metadata: ['plan_tier' => $plan->tier],
            );

            return $subscription;
        });
    }
}
