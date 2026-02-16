<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Commands\CommandRunStatus;
use App\Enums\Reviews\RunStatus;
use App\Models\BriefingGeneration;
use App\Models\CommandRun;
use App\Models\Run;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminOperationalAlerts
{
    public function __construct(
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array<int, array{
     *     alert_type: string,
     *     severity: string,
     *     summary: string,
     *     workspace_name: string,
     *     repository_name: string,
     *     opened_at: string
     * }>
     */
    public function handle(AdminDashboardFilters $filters, int $limit = 20): array
    {
        $cacheKey = $this->cacheKeyFactory->forOperationalAlerts($filters, $limit);

        /** @var array<int, array{
         *     alert_type: string,
         *     severity: string,
         *     summary: string,
         *     workspace_name: string,
         *     repository_name: string,
         *     opened_at: string
         * }> $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($filters, $limit): array {
            $segmentLimit = max((int) ceil($limit / 4), 5);
            $staleThreshold = now()->subMinutes(15);

            $failedRuns = Run::query()
                ->selectRaw('runs.created_at as opened_at')
                ->selectRaw('workspaces.name as workspace_name')
                ->selectRaw('COALESCE(repositories.full_name, ?) as repository_name', ['N/A'])
                ->join('workspaces', 'workspaces.id', '=', 'runs.workspace_id')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->leftJoin('repositories', 'repositories.id', '=', 'runs.repository_id')
                ->where('runs.status', RunStatus::Failed->value)
                ->whereBetween('runs.created_at', [$filters->startDate, $filters->endDate])
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('runs.workspace_id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->when($filters->runStatus !== null, function (Builder $query) use ($filters): void {
                    $query->where('runs.status', $filters->runStatus?->value);
                })
                ->latest('runs.created_at')
                ->limit($segmentLimit)
                ->get()
                ->map(fn (Run $row): array => [
                    'alert_type' => 'Run Failure',
                    'severity' => 'critical',
                    'summary' => 'Review run failed and requires investigation',
                    'workspace_name' => (string) data_get($row, 'workspace_name', 'Unknown'),
                    'repository_name' => (string) data_get($row, 'repository_name', 'N/A'),
                    'opened_at' => $this->formatOpenedAt(data_get($row, 'opened_at')),
                ]);

            $staleQueuedRuns = Run::query()
                ->selectRaw('runs.created_at as opened_at')
                ->selectRaw('workspaces.name as workspace_name')
                ->selectRaw('COALESCE(repositories.full_name, ?) as repository_name', ['N/A'])
                ->join('workspaces', 'workspaces.id', '=', 'runs.workspace_id')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->leftJoin('repositories', 'repositories.id', '=', 'runs.repository_id')
                ->where('runs.status', RunStatus::Queued->value)
                ->whereBetween('runs.created_at', [$filters->startDate, $filters->endDate])
                ->where('runs.created_at', '<=', $staleThreshold)
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('runs.workspace_id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->when($filters->runStatus !== null, function (Builder $query) use ($filters): void {
                    $query->where('runs.status', $filters->runStatus?->value);
                })
                ->latest('runs.created_at')
                ->limit($segmentLimit)
                ->get()
                ->map(fn (Run $row): array => [
                    'alert_type' => 'Queue Delay',
                    'severity' => 'warning',
                    'summary' => 'Run remained queued beyond expected SLA',
                    'workspace_name' => (string) data_get($row, 'workspace_name', 'Unknown'),
                    'repository_name' => (string) data_get($row, 'repository_name', 'N/A'),
                    'opened_at' => $this->formatOpenedAt(data_get($row, 'opened_at')),
                ]);

            $failedCommands = CommandRun::query()
                ->selectRaw('command_runs.created_at as opened_at')
                ->selectRaw('workspaces.name as workspace_name')
                ->selectRaw('COALESCE(repositories.full_name, ?) as repository_name', ['N/A'])
                ->join('workspaces', 'workspaces.id', '=', 'command_runs.workspace_id')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->leftJoin('repositories', 'repositories.id', '=', 'command_runs.repository_id')
                ->where('command_runs.status', CommandRunStatus::Failed->value)
                ->whereBetween('command_runs.created_at', [$filters->startDate, $filters->endDate])
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('command_runs.workspace_id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->latest('command_runs.created_at')
                ->limit($segmentLimit)
                ->get()
                ->map(fn (CommandRun $row): array => [
                    'alert_type' => 'Command Failure',
                    'severity' => 'high',
                    'summary' => 'Manual command execution failed',
                    'workspace_name' => (string) data_get($row, 'workspace_name', 'Unknown'),
                    'repository_name' => (string) data_get($row, 'repository_name', 'N/A'),
                    'opened_at' => $this->formatOpenedAt(data_get($row, 'opened_at')),
                ]);

            $failedBriefings = BriefingGeneration::query()
                ->selectRaw('briefing_generations.created_at as opened_at')
                ->selectRaw('workspaces.name as workspace_name')
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
                ->latest('briefing_generations.created_at')
                ->limit($segmentLimit)
                ->get()
                ->map(fn (BriefingGeneration $row): array => [
                    'alert_type' => 'Briefing Failure',
                    'severity' => 'high',
                    'summary' => 'Scheduled briefing generation failed',
                    'workspace_name' => (string) data_get($row, 'workspace_name', 'Unknown'),
                    'repository_name' => 'N/A',
                    'opened_at' => $this->formatOpenedAt(data_get($row, 'opened_at')),
                ]);

            /** @var Collection<int, array{alert_type: string, severity: string, summary: string, workspace_name: string, repository_name: string, opened_at: string}> $merged */
            $merged = collect()
                ->merge($failedRuns)
                ->merge($staleQueuedRuns)
                ->merge($failedCommands)
                ->merge($failedBriefings)
                ->sortByDesc('opened_at')
                ->values();

            return $merged->take($limit)->all();
        });

        return $result;
    }

    private function formatOpenedAt(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateTimeString();
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value)->toDateTimeString();
        }

        return now()->toDateTimeString();
    }
}
