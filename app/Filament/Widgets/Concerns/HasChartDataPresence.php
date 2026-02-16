<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

trait HasChartDataPresence
{
    protected function chartHasVisibleData(): bool
    {
        $chartData = $this->getCachedData();
        $datasets = data_get($chartData, 'datasets', []);

        if (! is_array($datasets)) {
            return false;
        }

        foreach ($datasets as $dataset) {
            if (! is_array($dataset)) {
                continue;
            }

            $values = data_get($dataset, 'data', []);

            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (is_numeric($value) && (float) $value > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array{
     *     datasets?: array<int, array{data?: array<int, int|float>}>,
     *     labels?: array<int, string>
     * }  $chartData
     * @return array{
     *     datasets: array<int, array{data?: array<int, int|float>}>,
     *     labels: array<int, string>
     * }
     */
    protected function collapseLineChartWhenEmpty(array $chartData): array
    {
        /** @var array<int, array{data?: array<int, int|float>}> $datasets */
        $datasets = $chartData['datasets'] ?? [];

        if ($datasets === []) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        foreach ($datasets as $dataset) {
            $values = $dataset['data'] ?? [];

            foreach ($values as $value) {
                if ((float) $value > 0) {
                    /** @var array<int, string> $labels */
                    $labels = $chartData['labels'] ?? [];

                    return [
                        'datasets' => $datasets,
                        'labels' => $labels,
                    ];
                }
            }
        }

        return [
            'datasets' => [],
            'labels' => [],
        ];
    }
}
