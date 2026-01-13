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
use InvalidArgumentException;

final readonly class UpgradeSubscription
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Upgrade a workspace subscription to a higher tier.
     */
    public function handle(Workspace $workspace, PlanTier $tier, ?User $actor = null): Subscription
    {
        $currentTier = $workspace->plan?->tier;

        if ($currentTier !== null) {
            $currentRank = PlanTier::from($currentTier)->rank();

            if ($tier->rank() <= $currentRank) {
                throw new InvalidArgumentException('Plan upgrades must move to a higher tier.');
            }
        }

        $plan = Plan::query()->firstOrCreate(
            ['tier' => $tier->value],
            PlanDefaults::forTier($tier)
        );

        return DB::transaction(function () use ($workspace, $plan, $actor): Subscription {
            $workspace->forceFill([
                'plan_id' => $plan->id,
                'subscription_status' => SubscriptionStatus::Active,
            ])->save();

            $subscription = Subscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'started_at' => now(),
                'ends_at' => null,
            ]);

            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::SubscriptionUpgraded,
                description: sprintf('Subscription upgraded to %s plan', ucfirst($plan->tier)),
                actor: $actor,
                subject: $subscription,
                metadata: ['plan_tier' => $plan->tier],
            );

            return $subscription;
        });
    }
}
