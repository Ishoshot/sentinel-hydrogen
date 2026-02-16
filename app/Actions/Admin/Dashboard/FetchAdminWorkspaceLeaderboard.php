<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Reviews\RunStatus;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminWorkspaceLeaderboard
{
    public function __construct(
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array<int, array{workspace_name: string, total_runs: int, completed_runs: int, failed_runs: int, success_rate: float, avg_duration_seconds: int|null}>
     */
    public function handle(AdminDashboardFilters $filters, int $limit = 8): array
    {
        $cacheKey = $this->cacheKeyFactory->forWorkspaceLeaderboard($filters, $limit);

        /** @var array<int, array{workspace_name: string, total_runs: int, completed_runs: int, failed_runs: int, success_rate: float, avg_duration_seconds: int|null}> $result */
        $result = Cache::remember($cacheKey, now()->addSeconds(90), function () use ($filters, $limit): array {
            $rows = Workspace::query()
                ->selectRaw('workspaces.name as workspace_name')
                ->selectRaw('COUNT(runs.id) as total_runs')
                ->selectRaw('SUM(CASE WHEN runs.status = ? THEN 1 ELSE 0 END) as completed_runs', [RunStatus::Completed->value])
                ->selectRaw('SUM(CASE WHEN runs.status = ? THEN 1 ELSE 0 END) as failed_runs', [RunStatus::Failed->value])
                ->selectRaw('AVG(runs.duration_seconds) as avg_duration_seconds')
                ->join('runs', function (JoinClause $join) use ($filters): void {
                    $join->on('runs.workspace_id', '=', 'workspaces.id')
                        ->whereBetween('runs.created_at', [
                            $filters->startDate,
                            $filters->endDate,
                        ]);
                })
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('workspaces.id', $filters->workspaceId);
                })
                ->when($filters->planTier instanceof \App\Enums\Billing\PlanTier, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->when($filters->runStatus instanceof RunStatus, function (Builder $query) use ($filters): void {
                    $query->where('runs.status', $filters->runStatus?->value);
                })
                ->groupBy('workspaces.id', 'workspaces.name')
                ->orderByDesc('total_runs')
                ->limit($limit)
                ->get();

            return $this->mapRows($rows);
        });

        return $result;
    }

    /**
     * @param  Collection<int, Workspace>  $rows
     * @return array<int, array{workspace_name: string, total_runs: int, completed_runs: int, failed_runs: int, success_rate: float, avg_duration_seconds: int|null}>
     */
    private function mapRows(Collection $rows): array
    {
        return $rows
            ->map(function (Workspace $row): array {
                $totalRuns = (int) data_get($row, 'total_runs', 0);
                $completedRuns = (int) data_get($row, 'completed_runs', 0);
                $failedRuns = (int) data_get($row, 'failed_runs', 0);
                $averageDurationSeconds = data_get($row, 'avg_duration_seconds');
                $averageDuration = is_numeric($averageDurationSeconds) ? (float) $averageDurationSeconds : null;
                $successRate = $totalRuns > 0 ? round(($completedRuns / $totalRuns) * 100, 1) : 0.0;

                return [
                    'workspace_name' => (string) data_get($row, 'workspace_name', 'Unknown'),
                    'total_runs' => $totalRuns,
                    'completed_runs' => $completedRuns,
                    'failed_runs' => $failedRuns,
                    'success_rate' => $successRate,
                    'avg_duration_seconds' => $averageDuration === null ? null : (int) round($averageDuration),
                ];
            })
            ->values()
            ->all();
    }
}
