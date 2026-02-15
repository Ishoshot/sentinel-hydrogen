<?php

declare(strict_types=1);

namespace App\Services\Briefings\Policies;

use App\Enums\Billing\PlanFeature;
use App\Models\Briefing;
use App\Models\Plan;

final class BriefingPlanEligibilityPolicy
{
    /**
     * Check if the briefings feature is enabled for the plan.
     */
    public function isBriefingsFeatureEnabled(?Plan $plan): bool
    {
        if (! $plan instanceof Plan) {
            return true;
        }

        return $plan->hasFeature(PlanFeature::Briefings);
    }

    /**
     * Check if the plan is eligible for a specific briefing.
     */
    public function isPlanEligibleForBriefing(?Plan $plan, Briefing $briefing): bool
    {
        if ($briefing->eligible_plan_ids === null) {
            return true;
        }

        if (! $plan instanceof Plan) {
            return false;
        }

        return $briefing->isEligibleForPlan($plan);
    }
}
