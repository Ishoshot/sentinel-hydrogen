<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Enums\Billing\PlanFeature;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use Carbon\CarbonInterface;

final readonly class BriefingLimitEnforcer
{
    /**
     * Create a new briefing limit enforcer.
     */
    public function __construct(
        private BriefingDataGuard $dataGuard,
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
            return BriefingLimitResult::deny('This briefing is not currently available.');
        }

        $workspaceCheck = $workspaceEligibility ?? $this->canGenerateForWorkspace($workspace, $parameters);
        if ($workspaceCheck->isDenied()) {
            return $workspaceCheck;
        }

        $workspace->loadMissing('plan');
        $plan = $workspace->plan;

        if (! $this->isPlanEligibleForBriefing($plan, $briefing)) {
            return BriefingLimitResult::deny('This briefing is not available on your current plan.');
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

        if (! $this->isBriefingsFeatureEnabled($plan)) {
            return BriefingLimitResult::deny('Briefings are not available on your current plan.');
        }

        $rateLimitCheck = $this->checkRateLimits($workspace, $plan);
        if ($rateLimitCheck->isDenied()) {
            return $rateLimitCheck;
        }

        $concurrentCheck = $this->checkConcurrentLimit($workspace);
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

        if (! $this->isBriefingsFeatureEnabled($plan)) {
            return BriefingLimitResult::deny('Briefings are not available on your current plan.');
        }

        if (! $this->isPlanEligibleForBriefing($plan, $briefing)) {
            return BriefingLimitResult::deny('This briefing is not available on your current plan.');
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

    /**
     * Check if the briefings feature is enabled for the plan.
     */
    private function isBriefingsFeatureEnabled(?Plan $plan): bool
    {
        if (! $plan instanceof Plan) {
            return true;
        }

        return $plan->hasFeature(PlanFeature::Briefings);
    }

    /**
     * Check if the plan is eligible for a specific briefing.
     *
     * Uses the briefing's eligible_plan_ids to determine eligibility.
     * If eligible_plan_ids is null, the briefing is available to all plans.
     */
    private function isPlanEligibleForBriefing(?Plan $plan, Briefing $briefing): bool
    {
        if ($briefing->eligible_plan_ids === null) {
            return true;
        }

        if (! $plan instanceof Plan) {
            return false;
        }

        return $briefing->isEligibleForPlan($plan);
    }

    /**
     * Check rate limits (daily, weekly, monthly).
     */
    private function checkRateLimits(Workspace $workspace, ?Plan $plan): BriefingLimitResult
    {
        $dailyCheck = $this->checkLimit($workspace, $plan, 'briefings.daily', now()->startOfDay(), 'daily');
        if ($dailyCheck->isDenied()) {
            return $dailyCheck;
        }

        $weeklyCheck = $this->checkLimit($workspace, $plan, 'briefings.weekly', now()->startOfWeek(), 'weekly');
        if ($weeklyCheck->isDenied()) {
            return $weeklyCheck;
        }

        $monthlyCheck = $this->checkLimit($workspace, $plan, 'briefings.monthly', now()->startOfMonth(), 'monthly');
        if ($monthlyCheck->isDenied()) {
            return $monthlyCheck;
        }

        return BriefingLimitResult::allow();
    }

    /**
     * Check a specific rate limit.
     */
    private function checkLimit(
        Workspace $workspace,
        ?Plan $plan,
        string $limitPath,
        CarbonInterface $since,
        string $period
    ): BriefingLimitResult {
        if (! $plan instanceof Plan) {
            return BriefingLimitResult::allow();
        }

        $limit = $plan->getLimit($limitPath);

        if ($limit === null) {
            return BriefingLimitResult::allow();
        }

        if ($limit === 0) {
            return BriefingLimitResult::deny(
                sprintf('Briefing generation is not available on your current plan (%s limit is 0).', $period)
            );
        }

        $count = BriefingGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', $since)
            ->count();

        if ($count >= $limit) {
            return BriefingLimitResult::deny(sprintf(
                'You have reached your %s limit of %d briefing generation%s. Please try again later or upgrade your plan.',
                $period,
                $limit,
                $limit === 1 ? '' : 's'
            ));
        }

        return BriefingLimitResult::allow();
    }

    /**
     * Check the concurrent generation limit.
     */
    private function checkConcurrentLimit(Workspace $workspace): BriefingLimitResult
    {
        $limit = config('briefings.limits.max_concurrent_generations', 3);

        $pendingCount = BriefingGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [
                BriefingGenerationStatus::Pending,
                BriefingGenerationStatus::Processing,
            ])
            ->count();

        if ($pendingCount >= $limit) {
            return BriefingLimitResult::deny(sprintf(
                'You have %d briefing%s currently generating. Please wait for them to complete.',
                $pendingCount,
                $pendingCount === 1 ? '' : 's'
            ));
        }

        return BriefingLimitResult::allow();
    }
}
