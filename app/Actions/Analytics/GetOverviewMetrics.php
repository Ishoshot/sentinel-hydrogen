<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

/**
 * Get overview metrics for workspace analytics dashboard.
 */
final readonly class GetOverviewMetrics
{
    /**
     * Get overview metrics for a workspace.
     *
     * @return array{total_runs: int, total_findings: int, average_duration_seconds: int, active_repositories: int}
     */
    public function handle(Workspace $workspace): array
    {
        return [
            'total_runs' => $workspace->runs()->count(),
            'total_findings' => $workspace->findings()->count(),
            'average_duration_seconds' => (int) $workspace->runs()
                ->whereNotNull('duration_seconds')
                ->avg('duration_seconds'),
            'active_repositories' => $workspace->repositories()
                ->whereHas('runs', function (Builder $query): void {
                    $query->where('created_at', '>=', now()->subDays(30));
                })
                ->count(),
        ];
    }
}
