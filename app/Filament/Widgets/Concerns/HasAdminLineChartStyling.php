<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

trait HasAdminLineChartStyling
{
    protected function lineChartLegendVisible(): bool
    {
        return true;
    }

    protected function getPollingInterval(): ?string
    {
        return '120s';
    }

    protected function getMaxHeight(): ?string
    {
        return '500px';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => $this->lineChartLegendVisible(),
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'pointStyleWidth' => 8,
                        'boxWidth' => 8,
                        'boxHeight' => 8,
                        'padding' => 14,
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'padding' => 10,
                ],
            ],
            'elements' => [
                'line' => [
                    'borderWidth' => 1,
                    'fill' => false,
                ],
                'point' => [
                    'radius' => 0,
                    'borderWidth' => 1,
                    'hitRadius' => 16,
                    'hoverRadius' => 5,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'autoSkip' => true,
                        'maxRotation' => 0,
                        'maxTicksLimit' => 7,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(113, 113, 122, 0.14)',
                    ],
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
