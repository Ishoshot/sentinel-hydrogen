<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Builders\AdminDashboardRunQueryBuilder;
use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Commands\CommandRunStatus;
use App\Models\BriefingGeneration;
use App\Models\CommandRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminPipelineThroughputTrend
{
    public function __construct(
        private AdminDashboardRunQueryBuilder $runQueryBuilder = new AdminDashboardRunQueryBuilder,
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array{
     *     datasets: array<int, array{label: string, data: array<int, int>, backgroundColor: string, borderColor: string, tension: float}>,
     *     labels: array<int, string>
     * }
     */
    public function handle(AdminDashboardFilters $filters): array
    {
        $cacheKey = $this->cacheKeyFactory->forPipelineThroughputTrend($filters);

        /** @var array{
         *     datasets: array<int, array{label: string, data: array<int, int>, backgroundColor: string, borderColor: string, tension: float}>,
         *     labels: array<int, string>
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(120), function () use ($filters): array {
            $runsByDay = $this->runQueryBuilder
                ->buildForRange($filters)
                ->selectRaw('DATE(created_at) as metric_date, COUNT(*) as total')
                ->groupBy('metric_date')
                ->orderBy('metric_date')
                ->pluck('total', 'metric_date');

            $commandsByDay = $this->buildCommandRunsQuery($filters)
                ->selectRaw('DATE(command_runs.created_at) as metric_date, COUNT(*) as total')
                ->groupBy('metric_date')
                ->orderBy('metric_date')
                ->pluck('total', 'metric_date');

            $briefingsByDay = $this->buildBriefingGenerationsQuery($filters)
                ->selectRaw('DATE(briefing_generations.created_at) as metric_date, COUNT(*) as total')
                ->groupBy('metric_date')
                ->orderBy('metric_date')
                ->pluck('total', 'metric_date');

            $labels = [];
            $runsSeries = [];
            $commandsSeries = [];
            $briefingsSeries = [];
            $start = $filters->startDate->startOfDay();

            for ($offset = 0; $offset < $filters->totalDays(); $offset++) {
                $day = $start->addDays($offset);
                $key = $day->toDateString();

                $labels[] = CarbonImmutable::parse($key)->format('M j');
                $runsSeries[] = (int) ($runsByDay[$key] ?? 0);
                $commandsSeries[] = (int) ($commandsByDay[$key] ?? 0);
                $briefingsSeries[] = (int) ($briefingsByDay[$key] ?? 0);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Runs',
                        'data' => $runsSeries,
                        'backgroundColor' => 'rgba(20, 184, 166, 0.12)',
                        'borderColor' => 'rgba(20, 184, 166, 1)',
                        'tension' => 0.32,
                    ],
                    [
                        'label' => 'Commands',
                        'data' => $commandsSeries,
                        'backgroundColor' => 'rgba(14, 165, 233, 0.12)',
                        'borderColor' => 'rgba(14, 165, 233, 1)',
                        'tension' => 0.32,
                    ],
                    [
                        'label' => 'Briefings',
                        'data' => $briefingsSeries,
                        'backgroundColor' => 'rgba(168, 85, 247, 0.12)',
                        'borderColor' => 'rgba(168, 85, 247, 1)',
                        'tension' => 0.32,
                    ],
                ],
                'labels' => $labels,
            ];
        });

        return $result;
    }

    /**
     * @return Builder<CommandRun>
     */
    private function buildCommandRunsQuery(AdminDashboardFilters $filters): Builder
    {
        $query = CommandRun::query()
            ->join('workspaces', 'workspaces.id', '=', 'command_runs.workspace_id')
            ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
            ->whereBetween('command_runs.created_at', [$filters->startDate, $filters->endDate])
            ->when($filters->workspaceId !== null, function (Builder $builder) use ($filters): void {
                $builder->where('command_runs.workspace_id', $filters->workspaceId);
            })
            ->when($filters->planTier !== null, function (Builder $builder) use ($filters): void {
                $builder->where('plans.tier', $filters->planTier?->value);
            });

        if ($filters->runStatus !== null) {
            if (! in_array($filters->runStatus->value, CommandRunStatus::values(), true)) {
                $query->whereRaw('1 = 0');

                return $query;
            }

            $query->where('command_runs.status', $filters->runStatus->value);
        }

        return $query;
    }

    /**
     * @return Builder<BriefingGeneration>
     */
    private function buildBriefingGenerationsQuery(AdminDashboardFilters $filters): Builder
    {
        $query = BriefingGeneration::query()
            ->join('workspaces', 'workspaces.id', '=', 'briefing_generations.workspace_id')
            ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
            ->whereBetween('briefing_generations.created_at', [$filters->startDate, $filters->endDate])
            ->when($filters->workspaceId !== null, function (Builder $builder) use ($filters): void {
                $builder->where('briefing_generations.workspace_id', $filters->workspaceId);
            })
            ->when($filters->planTier !== null, function (Builder $builder) use ($filters): void {
                $builder->where('plans.tier', $filters->planTier?->value);
            });

        if ($filters->runStatus !== null) {
            if (! in_array($filters->runStatus->value, BriefingGenerationStatus::values(), true)) {
                $query->whereRaw('1 = 0');

                return $query;
            }

            $query->where('briefing_generations.status', $filters->runStatus->value);
        }

        return $query;
    }
}
