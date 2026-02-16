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

final readonly class FetchAdminExecutionHealth
{
    public function __construct(
        private AdminDashboardRunQueryBuilder $runQueryBuilder = new AdminDashboardRunQueryBuilder,
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array{
     *     stuck_queued_runs: int,
     *     long_running_runs: int,
     *     failed_command_runs: int,
     *     failed_briefing_generations: int,
     *     processing_briefings: int
     * }
     */
    public function handle(AdminDashboardFilters $filters): array
    {
        $cacheKey = $this->cacheKeyFactory->forExecutionHealth($filters);

        /** @var array{
         *     stuck_queued_runs: int,
         *     long_running_runs: int,
         *     failed_command_runs: int,
         *     failed_briefing_generations: int,
         *     processing_briefings: int
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($filters): array {
            $queuedThreshold = now()->subMinutes(15);
            $inProgressThreshold = now()->subMinutes(20);

            $stuckQueuedRuns = $this->runQueryBuilder
                ->buildForRange($filters)
                ->where('status', RunStatus::Queued->value)
                ->where('created_at', '<=', $queuedThreshold)
                ->count();

            $longRunningRuns = $this->runQueryBuilder
                ->buildForRange($filters)
                ->where('status', RunStatus::InProgress->value)
                ->where(function (Builder $query) use ($inProgressThreshold): void {
                    $query->where('started_at', '<=', $inProgressThreshold)
                        ->orWhere(function (Builder $fallbackQuery) use ($inProgressThreshold): void {
                            $fallbackQuery->whereNull('started_at')
                                ->where('created_at', '<=', $inProgressThreshold);
                        });
                })
                ->count();

            $failedCommandRuns = CommandRun::query()
                ->join('workspaces', 'workspaces.id', '=', 'command_runs.workspace_id')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->where('command_runs.status', CommandRunStatus::Failed->value)
                ->whereBetween('command_runs.created_at', [$filters->startDate, $filters->endDate])
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('command_runs.workspace_id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->count();

            $failedBriefings = BriefingGeneration::query()
                ->join('workspaces', 'workspaces.id', '=', 'briefing_generations.workspace_id')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->where('briefing_generations.status', BriefingGenerationStatus::Failed->value)
                ->whereBetween('briefing_generations.created_at', [$filters->startDate, $filters->endDate])
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('briefing_generations.workspace_id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->count();

            $processingBriefings = BriefingGeneration::query()
                ->join('workspaces', 'workspaces.id', '=', 'briefing_generations.workspace_id')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->where('briefing_generations.status', BriefingGenerationStatus::Processing->value)
                ->whereBetween('briefing_generations.created_at', [$filters->startDate, $filters->endDate])
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('briefing_generations.workspace_id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->count();

            return [
                'stuck_queued_runs' => $stuckQueuedRuns,
                'long_running_runs' => $longRunningRuns,
                'failed_command_runs' => $failedCommandRuns,
                'failed_briefing_generations' => $failedBriefings,
                'processing_briefings' => $processingBriefings,
            ];
        });

        return $result;
    }
}
