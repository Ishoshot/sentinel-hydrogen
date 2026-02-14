<?php

declare(strict_types=1);

namespace App\Services\Plans\Support;

use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Workspace;
use App\Support\PlanDefaults;

final readonly class PlanResolver
{
    /**
     * Resolve the workspace plan, creating and assigning the default plan when missing.
     */
    public function resolve(Workspace $workspace): Plan
    {
        if ($workspace->plan !== null) {
            return $workspace->plan;
        }

        $plan = Plan::query()->firstOrCreate(
            ['tier' => PlanTier::Foundation->value],
            PlanDefaults::forTier(PlanTier::Foundation)
        );

        $workspace->forceFill([
            'plan_id' => $plan->id,
            'subscription_status' => $workspace->subscription_status ?? SubscriptionStatus::Active,
        ])->save();

        return $plan;
    }
}
