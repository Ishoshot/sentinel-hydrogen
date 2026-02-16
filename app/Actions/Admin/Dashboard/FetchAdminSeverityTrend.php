<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminSeverityTrend
{
    public function __construct(
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array{
     *     datasets: array<int, array{label: string, data: array<int, int>, borderColor: string, backgroundColor: string, tension: float}>,
     *     labels: array<int, string>
     * }
     */
    public function handle(AdminDashboardFilters $filters): array
    {
        $cacheKey = $this->cacheKeyFactory->forSeverityTrend($filters);

        /** @var array{
         *     datasets: array<int, array{label: string, data: array<int, int>, borderColor: string, backgroundColor: string, tension: float}>,
         *     labels: array<int, string>
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(120), function () use ($filters): array {
            $rows = $this->buildQuery($filters)
                ->selectRaw('DATE(findings.created_at) as finding_date, findings.severity, COUNT(*) as total')
                ->groupBy('finding_date', 'findings.severity')
                ->orderBy('finding_date')
                ->get();

            return $this->buildChartData($filters, $rows);
        });

        return $result;
    }

    /**
     * @return Builder<Finding>
     */
    private function buildQuery(AdminDashboardFilters $filters): Builder
    {
        return Finding::query()
            ->join('runs', 'runs.id', '=', 'findings.run_id')
            ->join('workspaces', 'workspaces.id', '=', 'findings.workspace_id')
            ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
            ->whereBetween('findings.created_at', [
                $filters->startDate,
                $filters->endDate,
            ])
            ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                $query->where('findings.workspace_id', $filters->workspaceId);
            })
            ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                $query->where('plans.tier', $filters->planTier?->value);
            })
            ->when($filters->runStatus !== null, function (Builder $query) use ($filters): void {
                $query->where('runs.status', $filters->runStatus?->value);
            });
    }

    /**
     * @param  Collection<int, Finding>  $rows
     * @return array{
     *     datasets: array<int, array{label: string, data: array<int, int>, borderColor: string, backgroundColor: string, tension: float}>,
     *     labels: array<int, string>
     * }
     */
    private function buildChartData(AdminDashboardFilters $filters, Collection $rows): array
    {
        $start = $filters->startDate->startOfDay();
        $days = $filters->totalDays();

        $labels = [];
        $dateKeys = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $day = $start->addDays($offset);
            $dateKeys[] = $day->toDateString();
            $labels[] = $day->format('M j');
        }

        $countsByDateAndSeverity = [];

        foreach ($rows as $row) {
            $date = (string) data_get($row, 'finding_date');
            $severityValue = data_get($row, 'severity');
            $severity = $severityValue instanceof SentinelConfigSeverity
                ? $severityValue->value
                : (string) $severityValue;
            $total = (int) data_get($row, 'total', 0);

            if ($date === '' || $severity === '') {
                continue;
            }

            $countsByDateAndSeverity[$date][$severity] = $total;
        }

        $datasets = [];

        foreach (SentinelConfigSeverity::cases() as $severity) {
            $dataPoints = [];

            foreach ($dateKeys as $dateKey) {
                $dataPoints[] = (int) ($countsByDateAndSeverity[$dateKey][$severity->value] ?? 0);
            }

            $datasets[] = [
                'label' => str($severity->value)->title()->toString(),
                'data' => $dataPoints,
                'borderColor' => $this->borderColorFor($severity),
                'backgroundColor' => $this->backgroundColorFor($severity),
                'tension' => 0.25,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    private function borderColorFor(SentinelConfigSeverity $severity): string
    {
        return match ($severity) {
            SentinelConfigSeverity::Critical => 'rgba(239, 68, 68, 1)',
            SentinelConfigSeverity::High => 'rgba(249, 115, 22, 1)',
            SentinelConfigSeverity::Medium => 'rgba(245, 158, 11, 1)',
            SentinelConfigSeverity::Low => 'rgba(16, 185, 129, 1)',
            SentinelConfigSeverity::Info => 'rgba(59, 130, 246, 1)',
        };
    }

    private function backgroundColorFor(SentinelConfigSeverity $severity): string
    {
        return match ($severity) {
            SentinelConfigSeverity::Critical => 'rgba(239, 68, 68, 0.16)',
            SentinelConfigSeverity::High => 'rgba(249, 115, 22, 0.16)',
            SentinelConfigSeverity::Medium => 'rgba(245, 158, 11, 0.16)',
            SentinelConfigSeverity::Low => 'rgba(16, 185, 129, 0.16)',
            SentinelConfigSeverity::Info => 'rgba(59, 130, 246, 0.16)',
        };
    }
}
