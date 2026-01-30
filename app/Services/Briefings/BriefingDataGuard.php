<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\Run;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use App\Services\Briefings\ValueObjects\BriefingParameters;

final class BriefingDataGuard
{
    /**
     * Check whether the workspace has enough data to generate a briefing.
     */
    public function check(Workspace $workspace, BriefingParameters $parameters): BriefingLimitResult
    {
        /** @var array<string, mixed> $config */
        $config = config('briefings.data_guard', []);

        if (! (bool) ($config['enabled'] ?? true)) {
            return BriefingLimitResult::allow();
        }

        $minRuns = (int) ($config['min_runs'] ?? 0);
        $minActiveDays = (int) ($config['min_active_days'] ?? 0);
        $minRepositories = (int) ($config['min_repositories'] ?? 0);

        if ($minRuns <= 0 && $minActiveDays <= 0 && $minRepositories <= 0) {
            return BriefingLimitResult::allow();
        }

        $dateRange = BriefingDateRange::fromArray($parameters->toArray());
        $repositoryIds = $parameters->get('repository_ids', []);

        if (! is_array($repositoryIds)) {
            $repositoryIds = [];
        }

        $runsQuery = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end]);

        if ($repositoryIds !== []) {
            $runsQuery->whereIn('repository_id', $repositoryIds);
        }

        $totalRuns = (int) (clone $runsQuery)->count();

        if ($minRuns > 0 && $totalRuns < $minRuns) {
            return BriefingLimitResult::deny(
                sprintf(
                    'Not enough run history to generate this briefing. Need at least %d runs in the selected period (found %d).',
                    $minRuns,
                    $totalRuns
                )
            );
        }

        $activeDays = (int) (clone $runsQuery)
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as active_days')
            ->value('active_days');

        if ($minActiveDays > 0 && $activeDays < $minActiveDays) {
            return BriefingLimitResult::deny(
                sprintf(
                    'Not enough activity days to generate this briefing. Need activity on at least %d days in the selected period (found %d).',
                    $minActiveDays,
                    $activeDays
                )
            );
        }

        $repositoryCount = (int) (clone $runsQuery)
            ->distinct()
            ->count('repository_id');

        if ($minRepositories > 0 && $repositoryCount < $minRepositories) {
            return BriefingLimitResult::deny(
                sprintf(
                    'Not enough repository coverage to generate this briefing. Need activity across at least %d repositories in the selected period (found %d).',
                    $minRepositories,
                    $repositoryCount
                )
            );
        }

        return BriefingLimitResult::allow();
    }
}
