<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Get overview metrics for workspace analytics dashboard.
 */
final class OverviewMetricsController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Workspace $workspace): JsonResponse
    {
        $metrics = [
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

        return response()->json([
            'data' => $metrics,
        ]);
    }
}
