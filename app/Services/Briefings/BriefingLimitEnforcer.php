<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Enums\Briefings\BriefingLimitReasonCode;
use App\Models\Briefing;
use App\Models\Workspace;
use App\Services\Briefings\Policies\BriefingConcurrencyLimitPolicy;
use App\Services\Briefings\Policies\BriefingFreeAllowancePolicy;
use App\Services\Briefings\Policies\BriefingPlanEligibilityPolicy;
use App\Services\Briefings\Policies\BriefingRateLimitPolicy;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use App\Services\Briefings\ValueObjects\BriefingParameters;

final readonly class BriefingLimitEnforcer
{
    /**
     * Create a new briefing limit enforcer.
     */
    public function __construct(
        private BriefingDataGuard $dataGuard,
        private BriefingProviderKeyResolver $providerKeyResolver,
        private BriefingPlanEligibilityPolicy $planEligibilityChecker,
        private BriefingFreeAllowancePolicy $freeAllowanceChecker,
        private BriefingRateLimitPolicy $rateLimitChecker,
        private BriefingConcurrencyLimitPolicy $concurrencyLimitChecker,
    ) {}

    /**
     * Check if a workspace can generate a briefing.
     */
    public function canGenerate(
        Workspace $workspace,
        Briefing $briefing,
        ?BriefingParameters $parameters = null,
        ?BriefingLimitResult $workspaceEligibility = null,
    ): BriefingLimitResult {
        if (! $briefing->is_active) {
            return BriefingLimitResult::deny(
                'This briefing is not currently available.',
                BriefingLimitReasonCode::BriefingInactive,
            );
        }

        $workspaceCheck = $workspaceEligibility ?? $this->canGenerateForWorkspace($workspace, $parameters);
        if ($workspaceCheck->isDenied()) {
            return $workspaceCheck;
        }

        $workspace->loadMissing('plan');
        $plan = $workspace->plan;

        if (! $this->planEligibilityChecker->isPlanEligibleForBriefing($plan, $briefing)) {
            return BriefingLimitResult::deny(
                'This briefing is not available on your current plan.',
                BriefingLimitReasonCode::PlanNotEligible,
            );
        }

        return BriefingLimitResult::allow();
    }

    /**
     * Check if a workspace can generate any briefing.
     */
    public function canGenerateForWorkspace(
        Workspace $workspace,
        ?BriefingParameters $parameters = null,
    ): BriefingLimitResult {
        $workspace->loadMissing('plan');
        $plan = $workspace->plan;

        if (! $this->planEligibilityChecker->isBriefingsFeatureEnabled($plan)) {
            return BriefingLimitResult::deny(
                'Briefings are not available on your current plan.',
                BriefingLimitReasonCode::FeatureDisabled,
            );
        }

        $hasByokKey = $this->providerKeyResolver->hasAnyKey($workspace);

        if (! $hasByokKey) {
            $freeCheck = $this->freeAllowanceChecker->check($workspace);
            if ($freeCheck->isDenied()) {
                return $freeCheck;
            }
        }

        $rateLimitCheck = $this->rateLimitChecker->check($workspace, $plan);
        if ($rateLimitCheck->isDenied()) {
            return $rateLimitCheck;
        }

        $concurrentCheck = $this->concurrencyLimitChecker->check($workspace);
        if ($concurrentCheck->isDenied()) {
            return $concurrentCheck;
        }

        if ($parameters instanceof BriefingParameters) {
            $dataGuardCheck = $this->dataGuard->check($workspace, $parameters);
            if ($dataGuardCheck->isDenied()) {
                return $dataGuardCheck;
            }
        }

        return BriefingLimitResult::allow();
    }

    /**
     * Check if a workspace can create a subscription.
     */
    public function canSubscribe(Workspace $workspace, Briefing $briefing): BriefingLimitResult
    {
        if (! $briefing->is_schedulable) {
            return BriefingLimitResult::deny('This briefing does not support scheduling.');
        }

        $workspace->loadMissing('plan');
        $plan = $workspace->plan;

        if (! $this->planEligibilityChecker->isBriefingsFeatureEnabled($plan)) {
            return BriefingLimitResult::deny(
                'Briefings are not available on your current plan.',
                BriefingLimitReasonCode::FeatureDisabled,
            );
        }

        if (! $this->planEligibilityChecker->isPlanEligibleForBriefing($plan, $briefing)) {
            return BriefingLimitResult::deny(
                'This briefing is not available on your current plan.',
                BriefingLimitReasonCode::PlanNotEligible,
            );
        }

        return BriefingLimitResult::allow();
    }

    /**
     * Check if a workspace can share a briefing externally.
     *
     * Sharing is free for all plans.
     */
    public function canShare(): BriefingLimitResult
    {
        return BriefingLimitResult::allow();
    }
}
