<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Builders\AdminDashboardRunQueryBuilder;
use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Commands\CommandRunStatus;
use App\Enums\Reviews\RunStatus;
use App\Models\BriefingGeneration;
use App\Models\CommandRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminPipelineReliability
{
    public function __construct(
        private AdminDashboardRunQueryBuilder $runQueryBuilder = new AdminDashboardRunQueryBuilder,
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array{
     *     run_success_rate: float,
     *     command_success_rate: float,
     *     briefing_success_rate: float,
     *     avg_run_duration_seconds: int,
     *     p95_run_duration_seconds: int,
     *     total_pipeline_events: int
     * }
     */
    public function handle(AdminDashboardFilters $filters): array
    {
        $cacheKey = $this->cacheKeyFactory->forPipelineReliability($filters);

        /** @var array{
         *     run_success_rate: float,
         *     command_success_rate: float,
         *     briefing_success_rate: float,
         *     avg_run_duration_seconds: int,
         *     p95_run_duration_seconds: int,
         *     total_pipeline_events: int
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(90), function () use ($filters): array {
            $runsQuery = $this->runQueryBuilder->buildForRange($filters);

            $runCompleted = (clone $runsQuery)->where('status', RunStatus::Completed->value)->count();
            $runFailed = (clone $runsQuery)->where('status', RunStatus::Failed->value)->count();
            $runTotal = (clone $runsQuery)->count();

            $commandQuery = $this->buildCommandRunsQuery($filters);
            $commandCompleted = (clone $commandQuery)->where('command_runs.status', CommandRunStatus::Completed->value)->count();
            $commandFailed = (clone $commandQuery)->where('command_runs.status', CommandRunStatus::Failed->value)->count();
            $commandTotal = (clone $commandQuery)->count();

            $briefingQuery = $this->buildBriefingGenerationsQuery($filters);
            $briefingCompleted = (clone $briefingQuery)->where('briefing_generations.status', BriefingGenerationStatus::Completed->value)->count();
            $briefingFailed = (clone $briefingQuery)->where('briefing_generations.status', BriefingGenerationStatus::Failed->value)->count();
            $briefingTotal = (clone $briefingQuery)->count();

            $durations = (clone $runsQuery)
                ->whereNotNull('duration_seconds')
                ->orderBy('duration_seconds')
                ->pluck('duration_seconds')
                ->filter(fn (mixed $value): bool => is_numeric($value))
                ->map(fn (mixed $value): int => (int) $value)
                ->values();

            $averageDuration = $durations->count() > 0
                ? (int) round(((int) $durations->sum()) / $durations->count())
                : 0;

            $p95Duration = $durations->count() > 0
                ? $durations->get((int) max(0, ceil($durations->count() * 0.95) - 1), 0)
                : 0;

            return [
                'run_success_rate' => $this->percentage($runCompleted, $runCompleted + $runFailed),
                'command_success_rate' => $this->percentage($commandCompleted, $commandCompleted + $commandFailed),
                'briefing_success_rate' => $this->percentage($briefingCompleted, $briefingCompleted + $briefingFailed),
                'avg_run_duration_seconds' => $averageDuration,
                'p95_run_duration_seconds' => (int) $p95Duration,
                'total_pipeline_events' => $runTotal + $commandTotal + $briefingTotal,
            ];
        });

        return $result;
    }

    /**
     * @return Builder<CommandRun>
     */
    private function buildCommandRunsQuery(AdminDashboardFilters $filters): Builder
    {
        return CommandRun::query()
            ->join('workspaces', 'workspaces.id', '=', 'command_runs.workspace_id')
            ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
            ->whereBetween('command_runs.created_at', [$filters->startDate, $filters->endDate])
            ->when($filters->workspaceId !== null, function (Builder $builder) use ($filters): void {
                $builder->where('command_runs.workspace_id', $filters->workspaceId);
            })
            ->when($filters->planTier !== null, function (Builder $builder) use ($filters): void {
                $builder->where('plans.tier', $filters->planTier?->value);
            });
    }

    /**
     * @return Builder<BriefingGeneration>
     */
    private function buildBriefingGenerationsQuery(AdminDashboardFilters $filters): Builder
    {
        return BriefingGeneration::query()
            ->join('workspaces', 'workspaces.id', '=', 'briefing_generations.workspace_id')
            ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
            ->whereBetween('briefing_generations.created_at', [$filters->startDate, $filters->endDate])
            ->when($filters->workspaceId !== null, function (Builder $builder) use ($filters): void {
                $builder->where('briefing_generations.workspace_id', $filters->workspaceId);
            })
            ->when($filters->planTier !== null, function (Builder $builder) use ($filters): void {
                $builder->where('plans.tier', $filters->planTier?->value);
            });
    }

    private function percentage(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }
}
