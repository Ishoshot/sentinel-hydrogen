<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Builders\AdminDashboardRunQueryBuilder;
use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Reviews\RunStatus;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminRunStatusComposition
{
    public function __construct(
        private AdminDashboardRunQueryBuilder $runQueryBuilder = new AdminDashboardRunQueryBuilder,
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
        $cacheKey = $this->cacheKeyFactory->forRunStatusComposition($filters);

        /** @var array{
         *     datasets: array<int, array{label: string, data: array<int, int>, backgroundColor: array<int, string>, borderColor: array<int, string>, borderWidth: int}>,
         *     labels: array<int, string>
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(90), function () use ($filters): array {
            $counts = $this->runQueryBuilder
                ->buildForRange($filters)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $orderedStatuses = RunStatus::cases();

            $labels = [];
            $data = [];
            $backgroundColors = [];
            $borderColors = [];

            foreach ($orderedStatuses as $status) {
                $labels[] = str($status->value)->replace('_', ' ')->title()->toString();
                $data[] = (int) ($counts[$status->value] ?? 0);
                $backgroundColors[] = $this->backgroundColorFor($status);
                $borderColors[] = $this->borderColorFor($status);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Runs by Status',
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

    private function backgroundColorFor(RunStatus $status): string
    {
        return match ($status) {
            RunStatus::Completed => 'rgba(16, 185, 129, 0.22)',
            RunStatus::Failed => 'rgba(239, 68, 68, 0.22)',
            RunStatus::InProgress => 'rgba(245, 158, 11, 0.22)',
            RunStatus::Queued => 'rgba(59, 130, 246, 0.22)',
            RunStatus::Skipped => 'rgba(113, 113, 122, 0.22)',
        };
    }

    private function borderColorFor(RunStatus $status): string
    {
        return match ($status) {
            RunStatus::Completed => 'rgba(16, 185, 129, 1)',
            RunStatus::Failed => 'rgba(239, 68, 68, 1)',
            RunStatus::InProgress => 'rgba(245, 158, 11, 1)',
            RunStatus::Queued => 'rgba(59, 130, 246, 1)',
            RunStatus::Skipped => 'rgba(113, 113, 122, 1)',
        };
    }
}
