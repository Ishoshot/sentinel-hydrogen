<?php

declare(strict_types=1);

namespace App\Services\Briefings\Policies;

use App\Enums\Briefings\BriefingLimitReasonCode;
use App\Models\BriefingGeneration;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use Carbon\CarbonInterface;

final class BriefingRateLimitPolicy
{
    /**
     * Check rate limits (daily, weekly, monthly).
     */
    public function check(Workspace $workspace, ?Plan $plan): BriefingLimitResult
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
     * Check a specific rate limit (daily, weekly, or monthly).
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
                sprintf('Briefing generation is not available on your current plan (%s limit is 0).', $period),
                BriefingLimitReasonCode::RateLimitReached,
                'Upgrade your plan to enable briefing generation.',
            );
        }

        $periodCount = BriefingGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', $since)
            ->count();

        if ($periodCount >= $limit) {
            $retryAt = $this->resolveRetryAt($period);
            $guidance = sprintf(
                'Your %s limit resets %s. Upgrade your plan for higher limits.',
                $period,
                $retryAt,
            );

            return BriefingLimitResult::deny(
                sprintf(
                    'You have reached your %s limit of %d briefing generation%s.',
                    $period,
                    $limit,
                    $limit === 1 ? '' : 's'
                ),
                BriefingLimitReasonCode::RateLimitReached,
                $guidance,
            );
        }

        return BriefingLimitResult::allow();
    }

    /**
     * Resolve a human-readable retry-at description for a rate limit period.
     */
    private function resolveRetryAt(string $period): string
    {
        return match ($period) {
            'daily' => 'tomorrow at '.now()->addDay()->startOfDay()->format('H:i'),
            'weekly' => 'on '.now()->addWeek()->startOfWeek()->format('l'),
            'monthly' => 'on '.now()->addMonth()->startOfMonth()->format('M j'),
            default => 'soon',
        };
    }
}
