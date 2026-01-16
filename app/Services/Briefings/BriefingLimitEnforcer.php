<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Workspace;

final class BriefingLimitEnforcer
{
    /**
     * Check if a workspace can generate a briefing.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canGenerate(Workspace $workspace, Briefing $briefing): array
    {
        if (! $briefing->is_active) {
            return $this->denied('This briefing is not currently available.');
        }

        if (! $this->isPlanEligible($workspace, $briefing)) {
            return $this->denied('This briefing is not available on your current plan.');
        }

        $monthlyLimitCheck = $this->checkMonthlyLimit($workspace);
        if (! $monthlyLimitCheck['allowed']) {
            return $monthlyLimitCheck;
        }

        $concurrentCheck = $this->checkConcurrentLimit($workspace);
        if (! $concurrentCheck['allowed']) {
            return $concurrentCheck;
        }

        return $this->allowed();
    }

    /**
     * Check if a workspace can create a subscription.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canSubscribe(Workspace $workspace, Briefing $briefing): array
    {
        if (! $briefing->is_schedulable) {
            return $this->denied('This briefing does not support scheduling.');
        }

        $features = $this->getPlanFeatures($workspace);

        if (! ($features['scheduling_enabled'] ?? false)) {
            return $this->denied('Briefing scheduling is not available on your current plan.');
        }

        if (! $this->isPlanEligible($workspace, $briefing)) {
            return $this->denied('This briefing is not available on your current plan.');
        }

        return $this->allowed();
    }

    /**
     * Check if a workspace can share a briefing externally.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function canShare(Workspace $workspace): array
    {
        $features = $this->getPlanFeatures($workspace);

        if (! ($features['external_sharing_enabled'] ?? false)) {
            return $this->denied('External sharing is not available on your current plan.');
        }

        return $this->allowed();
    }

    /** Check if the workspace's plan is eligible for a briefing. */
    private function isPlanEligible(Workspace $workspace, Briefing $briefing): bool
    {
        if ($briefing->is_system && empty($briefing->eligible_plan_ids)) {
            return true;
        }

        $features = $this->getPlanFeatures($workspace);

        if (! ($features['enabled'] ?? true)) {
            return false;
        }

        $allowedBriefingIds = $features['allowed_briefing_ids'] ?? null;

        if ($allowedBriefingIds === null) {
            return true;
        }

        return in_array($briefing->id, $allowedBriefingIds, true);
    }

    /**
     * Check the monthly generation limit.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    private function checkMonthlyLimit(Workspace $workspace): array
    {
        $features = $this->getPlanFeatures($workspace);
        $limit = $features['generations_per_month'] ?? null;

        if ($limit === null) {
            return $this->allowed();
        }

        $count = BriefingGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($count >= $limit) {
            return $this->denied(sprintf(
                'You have reached your monthly limit of %d briefing generations. Upgrade your plan for more.',
                $limit
            ));
        }

        return $this->allowed();
    }

    /**
     * Check the concurrent generation limit.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    private function checkConcurrentLimit(Workspace $workspace): array
    {
        $limit = config('briefings.limits.max_concurrent_generations', 3);

        $pendingCount = BriefingGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount >= $limit) {
            return $this->denied(sprintf(
                'You have %d briefings currently generating. Please wait for them to complete.',
                $pendingCount
            ));
        }

        return $this->allowed();
    }

    /**
     * Get briefing features from the workspace's plan.
     *
     * @return array<string, mixed>
     */
    private function getPlanFeatures(Workspace $workspace): array
    {
        $workspace->loadMissing('plan');

        $defaultFeatures = [
            'enabled' => true,
            'allowed_briefing_ids' => null,
            'generations_per_month' => null,
            'scheduling_enabled' => false,
            'external_sharing_enabled' => false,
        ];

        $plan = $workspace->plan;

        if ($plan === null) {
            return $defaultFeatures;
        }

        /** @var array<string, mixed>|null $planFeatures */
        $planFeatures = $plan->features;

        if ($planFeatures === null) {
            return $defaultFeatures;
        }

        /** @var array<string, mixed>|null $briefingFeatures */
        $briefingFeatures = $planFeatures['briefings'] ?? null;

        return is_array($briefingFeatures) ? $briefingFeatures : $defaultFeatures;
    }

    /**
     * Return an allowed result.
     *
     * @return array{allowed: true, reason: null}
     */
    private function allowed(): array
    {
        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Return a denied result with a reason.
     *
     * @return array{allowed: false, reason: string}
     */
    private function denied(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }
}
