<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Billing\PlanTier;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminPlanDistribution
{
    public function __construct(
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array{
     *     datasets: array<int, array{label: string, data: array<int, int>, backgroundColor: array<int, string>, borderColor: array<int, string>, borderWidth: int}>,
     *     labels: array<int, string>
     * }
     */
    public function handle(AdminDashboardFilters $filters): array
    {
        $cacheKey = $this->cacheKeyFactory->forPlanDistribution($filters);

        /** @var array{
         *     datasets: array<int, array{label: string, data: array<int, int>, backgroundColor: array<int, string>, borderColor: array<int, string>, borderWidth: int}>,
         *     labels: array<int, string>
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(120), function () use ($filters): array {
            $counts = Workspace::query()
                ->selectRaw('plans.tier as plan_tier, COUNT(workspaces.id) as total')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('workspaces.id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->groupBy('plans.tier')
                ->pluck('total', 'plan_tier');

            $labels = [];
            $data = [];
            $backgroundColors = [];
            $borderColors = [];

            foreach (PlanTier::cases() as $tier) {
                $labels[] = str($tier->value)->title()->toString();
                $data[] = (int) ($counts[$tier->value] ?? 0);
                $backgroundColors[] = $this->backgroundColorFor($tier);
                $borderColors[] = $this->borderColorFor($tier);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Workspaces by Plan',
                        'data' => $data,
                        'backgroundColor' => $backgroundColors,
                        'borderColor' => $borderColors,
                        'borderWidth' => 1,
                    ],
                ],
                'labels' => $labels,
            ];
        });

        return $result;
    }

    private function backgroundColorFor(PlanTier $tier): string
    {
        return match ($tier) {
            PlanTier::Foundation => 'rgba(113, 113, 122, 0.25)',
            PlanTier::Illuminate => 'rgba(14, 165, 233, 0.25)',
            PlanTier::Orchestrate => 'rgba(20, 184, 166, 0.25)',
            PlanTier::Sanctum => 'rgba(234, 179, 8, 0.25)',
        };
    }

    private function borderColorFor(PlanTier $tier): string
    {
        return match ($tier) {
            PlanTier::Foundation => 'rgba(113, 113, 122, 1)',
            PlanTier::Illuminate => 'rgba(14, 165, 233, 1)',
            PlanTier::Orchestrate => 'rgba(20, 184, 166, 1)',
            PlanTier::Sanctum => 'rgba(234, 179, 8, 1)',
        };
    }
}
