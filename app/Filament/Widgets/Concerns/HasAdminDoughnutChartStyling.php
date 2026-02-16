<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

trait HasAdminDoughnutChartStyling
{
    protected function getPollingInterval(): ?string
    {
        return '180s';
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
            'cutout' => '62%',
            'layout' => [
                'padding' => 6,
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'boxWidth' => 9,
                        'boxHeight' => 9,
                        'padding' => 14,
                    ],
                ],
                'tooltip' => [
                    'padding' => 10,
                    'displayColors' => true,
                ],
            ],
        ];
    }
}
