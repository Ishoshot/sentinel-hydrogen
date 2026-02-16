<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Builders\AdminDashboardRunQueryBuilder;
use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Models\AiOption;
use App\Models\Briefing;
use App\Models\Promotion;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminOverview
{
    public function __construct(
        private AdminDashboardRunQueryBuilder $runQueryBuilder = new AdminDashboardRunQueryBuilder,
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array{
     *     workspace_count: int,
     *     runs_current_period: int,
     *     runs_previous_period: int,
     *     run_delta: int,
     *     active_promotions: int,
     *     active_briefings: int,
     *     active_ai_models: int
     * }
     */
    public function handle(AdminDashboardFilters $filters): array
    {
        $cacheKey = $this->cacheKeyFactory->forOverview($filters);

        /** @var array{
         *     workspace_count: int,
         *     runs_current_period: int,
         *     runs_previous_period: int,
         *     run_delta: int,
         *     active_promotions: int,
         *     active_briefings: int,
         *     active_ai_models: int
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(90), function () use ($filters): array {
            $previousRange = $filters->previousRange();

            $runsInCurrentPeriod = $this->runQueryBuilder
                ->buildForRange($filters)
                ->count();

            $runsInPreviousPeriod = $this->runQueryBuilder
                ->buildBase($filters)
                ->whereBetween('created_at', [
                    $previousRange['start'],
                    $previousRange['end'],
                ])
                ->count();

            $workspaceCount = Workspace::query()
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->whereKey($filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->whereHas('plan', function (Builder $planQuery) use ($filters): void {
                        $planQuery->where('tier', $filters->planTier?->value);
                    });
                })
                ->count();

            $activeBriefings = Briefing::query()
                ->where('is_active', true)
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where(function (Builder $workspaceBriefingsQuery) use ($filters): void {
                        $workspaceBriefingsQuery->whereNull('workspace_id')
                            ->orWhere('workspace_id', $filters->workspaceId);
                    });
                })
                ->count();

            return [
                'workspace_count' => $workspaceCount,
                'runs_current_period' => $runsInCurrentPeriod,
                'runs_previous_period' => $runsInPreviousPeriod,
                'run_delta' => $runsInCurrentPeriod - $runsInPreviousPeriod,
                'active_promotions' => Promotion::query()->where('is_active', true)->count(),
                'active_briefings' => $activeBriefings,
                'active_ai_models' => AiOption::query()->where('is_active', true)->count(),
            ];
        });

        return $result;
    }
}
