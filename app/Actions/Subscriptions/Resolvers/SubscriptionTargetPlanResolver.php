<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Enums\Billing\PlanTier;
use App\Models\Plan;
use App\Support\PlanDefaults;

final class SubscriptionTargetPlanResolver
{
    /**
     * Resolve the target plan model for the requested tier.
     */
    public function resolve(PlanTier $targetTier): Plan
    {
        return Plan::query()->firstOrCreate(
            ['tier' => $targetTier->value],
            PlanDefaults::forTier($targetTier)
        );
    }
}
