<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminRepositoryReliability
{
    public function __construct(
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array<int, array{repository_name: string, total_runs: int, completed_runs: int, failed_runs: int, reliability_score: float, avg_duration_seconds: int|null}>
     */
    public function handle(AdminDashboardFilters $filters, int $limit = 8): array
    {
        $cacheKey = $this->cacheKeyFactory->forRepositoryReliability($filters, $limit);

        /** @var array<int, array{repository_name: string, total_runs: int, completed_runs: int, failed_runs: int, reliability_score: float, avg_duration_seconds: int|null}> $result */
        $result = Cache::remember($cacheKey, now()->addSeconds(90), function () use ($filters, $limit): array {
            $rows = Repository::query()
                ->selectRaw('repositories.full_name as repository_name')
                ->selectRaw('COUNT(runs.id) as total_runs')
                ->selectRaw('SUM(CASE WHEN runs.status = ? THEN 1 ELSE 0 END) as completed_runs', [RunStatus::Completed->value])
                ->selectRaw('SUM(CASE WHEN runs.status = ? THEN 1 ELSE 0 END) as failed_runs', [RunStatus::Failed->value])
                ->selectRaw('AVG(runs.duration_seconds) as avg_duration_seconds')
                ->join('runs', function (JoinClause $join) use ($filters): void {
                    $join->on('runs.repository_id', '=', 'repositories.id')
                        ->whereBetween('runs.created_at', [
                            $filters->startDate,
                            $filters->endDate,
                        ]);
                })
                ->join('workspaces', 'workspaces.id', '=', 'repositories.workspace_id')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('repositories.workspace_id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->when($filters->runStatus !== null, function (Builder $query) use ($filters): void {
                    $query->where('runs.status', $filters->runStatus?->value);
                })
                ->groupBy('repositories.id', 'repositories.full_name')
                ->orderByDesc('total_runs')
                ->limit($limit)
                ->get();

            return $this->mapRows($rows);
        });

        return $result;
    }

    /**
     * @param  Collection<int, Repository>  $rows
     * @return array<int, array{repository_name: string, total_runs: int, completed_runs: int, failed_runs: int, reliability_score: float, avg_duration_seconds: int|null}>
     */
    private function mapRows(Collection $rows): array
    {
        return $rows
            ->map(function (Repository $row): array {
                $totalRuns = (int) data_get($row, 'total_runs', 0);
                $completedRuns = (int) data_get($row, 'completed_runs', 0);
                $failedRuns = (int) data_get($row, 'failed_runs', 0);
                $averageDurationSeconds = data_get($row, 'avg_duration_seconds');
                $averageDuration = is_numeric($averageDurationSeconds) ? (float) $averageDurationSeconds : null;
                $reliabilityScore = $totalRuns > 0 ? round(($completedRuns / $totalRuns) * 100, 1) : 0.0;

                return [
                    'repository_name' => (string) data_get($row, 'repository_name', 'Unknown'),
                    'total_runs' => $totalRuns,
                    'completed_runs' => $completedRuns,
                    'failed_runs' => $failedRuns,
                    'reliability_score' => $reliabilityScore,
                    'avg_duration_seconds' => $averageDuration === null ? null : (int) round($averageDuration),
                ];
            })
            ->values()
            ->all();
    }
}
