<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Builders\AdminDashboardRunQueryBuilder;
use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminRunsTrend
{
    public function __construct(
        private AdminDashboardRunQueryBuilder $runQueryBuilder = new AdminDashboardRunQueryBuilder,
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array{
     *     datasets: array<int, array{
     *         label: string,
     *         data: array<int, int>,
     *         backgroundColor: string,
     *         borderColor: string,
     *         tension: float
     *     }>,
     *     labels: array<int, string>
     * }
     */
    public function handle(AdminDashboardFilters $filters): array
    {
        $cacheKey = $this->cacheKeyFactory->forRunsTrend($filters);

        /** @var array{
         *     datasets: array<int, array{
         *         label: string,
         *         data: array<int, int>,
         *         backgroundColor: string,
         *         borderColor: string,
         *         tension: float
         *     }>,
         *     labels: array<int, string>
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(120), function () use ($filters): array {
            $runsByDay = $this->runQueryBuilder
                ->buildForRange($filters)
                ->selectRaw('DATE(created_at) as run_date, COUNT(*) as total')
                ->groupBy('run_date')
                ->orderBy('run_date')
                ->pluck('total', 'run_date');

            $labels = [];
            $dataPoints = [];

            $start = $filters->startDate->startOfDay();
            $days = $filters->totalDays();

            for ($offset = 0; $offset < $days; $offset++) {
                $day = $start->addDays($offset);
                $key = $day->toDateString();

                $labels[] = CarbonImmutable::parse($key)->format('M j');
                $dataPoints[] = (int) ($runsByDay[$key] ?? 0);
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Runs',
                        'data' => $dataPoints,
                        'backgroundColor' => 'rgba(20, 184, 166, 0.14)',
                        'borderColor' => 'rgba(20, 184, 166, 1)',
                        'tension' => 0.32,
                    ],
                ],
                'labels' => $labels,
            ];
        });

        return $result;
    }
}
