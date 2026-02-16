<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Builders\AdminDashboardRunQueryBuilder;
use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminRunDurationTrend
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
        $cacheKey = $this->cacheKeyFactory->forRunDurationTrend($filters);

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
            $rows = $this->runQueryBuilder
                ->buildForRange($filters)
                ->whereNotNull('duration_seconds')
                ->selectRaw('DATE(created_at) as run_date, AVG(duration_seconds) as avg_duration, MAX(duration_seconds) as max_duration')
                ->groupBy('run_date')
                ->orderBy('run_date')
                ->get();

            $durationsByDate = [];

            foreach ($rows as $row) {
                $date = (string) data_get($row, 'run_date');

                if ($date === '') {
                    continue;
                }

                $averageDuration = $this->normalizeNumeric(data_get($row, 'avg_duration'));
                $maxDuration = $this->normalizeNumeric(data_get($row, 'max_duration'));

                $durationsByDate[$date] = [
                    'avg' => (int) round($averageDuration ?? 0),
                    'max' => (int) round($maxDuration ?? 0),
                ];
            }

            $labels = [];
            $averageSeries = [];
            $slowestSeries = [];
            $start = $filters->startDate->startOfDay();

            for ($offset = 0; $offset < $filters->totalDays(); $offset++) {
                $day = $start->addDays($offset);
                $key = $day->toDateString();

                $labels[] = CarbonImmutable::parse($key)->format('M j');
                $averageSeries[] = $durationsByDate[$key]['avg'] ?? 0;
                $slowestSeries[] = $durationsByDate[$key]['max'] ?? 0;
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Avg Duration (s)',
                        'data' => $averageSeries,
                        'backgroundColor' => 'rgba(14, 165, 233, 0.12)',
                        'borderColor' => 'rgba(14, 165, 233, 1)',
                        'tension' => 0.3,
                    ],
                    [
                        'label' => 'Slowest Run (s)',
                        'data' => $slowestSeries,
                        'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
                        'borderColor' => 'rgba(245, 158, 11, 1)',
                        'tension' => 0.3,
                    ],
                ],
                'labels' => $labels,
            ];
        });

        return $result;
    }

    private function normalizeNumeric(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
