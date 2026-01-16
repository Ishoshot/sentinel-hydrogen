<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Models\Repository;
use App\Models\Workspace;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Get repository activity showing runs per repository.
 */
final class RepositoryActivityController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        // Get repository IDs for this workspace first to avoid HasManyThrough GROUP BY issues
        $repositoryIds = $workspace->repositories()->pluck('repositories.id');

        // Get findings count per repository in a single query
        $findingsCounts = DB::table('findings')
            ->join('runs', 'findings.run_id', '=', 'runs.id')
            ->whereIn('runs.repository_id', $repositoryIds)
            ->select('runs.repository_id', DB::raw('COUNT(*) as count'))
            ->groupBy('runs.repository_id')
            ->pluck('count', 'repository_id');

        $activity = Repository::query()
            ->whereIn('repositories.id', $repositoryIds)
            ->select(
                'repositories.id',
                'repositories.name',
                DB::raw('COUNT(runs.id) as runs_count'),
                DB::raw('MAX(runs.created_at) as last_run_at'),
            )
            ->leftJoin('runs', function (JoinClause $join) use ($days): void {
                $join->on('repositories.id', '=', 'runs.repository_id')
                    ->where('runs.created_at', '>=', now()->subDays($days));
            })
            ->groupBy('repositories.id', 'repositories.name')
            ->orderByDesc('runs_count')
            ->get()
            ->map(static function (Repository $repository) use ($findingsCounts): array {
                /** @var int|string $runsCount */
                $runsCount = $repository->getAttribute('runs_count');
                /** @var string|null $lastRunAt */
                $lastRunAt = $repository->getAttribute('last_run_at');

                return [
                    'repository_id' => $repository->id,
                    'repository_name' => $repository->name,
                    'runs_count' => (int) $runsCount,
                    'findings_count' => (int) ($findingsCounts[$repository->id] ?? 0),
                    'last_run_at' => $lastRunAt,
                ];
            });

        return response()->json([
            'data' => $activity,
        ]);
    }
}
