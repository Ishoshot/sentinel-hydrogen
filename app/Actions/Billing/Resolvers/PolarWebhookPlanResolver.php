<?php

declare(strict_types=1);

namespace App\Actions\Billing\Resolvers;

use App\Enums\Billing\PlanTier;
use App\Models\Plan;
use App\Support\PlanDefaults;

final class PolarWebhookPlanResolver
{
    /**
     * Resolve a plan model for the given tier value.
     */
    public function resolve(?string $tier): ?Plan
    {
        if (! is_string($tier)) {
            return null;
        }

        $planTier = PlanTier::tryFrom($tier);

        if ($planTier === null) {
            return null;
        }

        return Plan::query()->firstOrCreate(
            ['tier' => $planTier->value],
            PlanDefaults::forTier($planTier)
        );
    }
}
