<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Run;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

final class RunsTrendChart extends ChartWidget
{
    protected ?string $heading = 'Run Volume (14 Days)';

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $start = CarbonImmutable::now()->subDays(13)->startOfDay();

        $runsByDay = Run::query()
            ->selectRaw('DATE(created_at) as run_date, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('run_date')
            ->orderBy('run_date')
            ->pluck('total', 'run_date');

        $labels = [];
        $points = [];

        for ($index = 0; $index < 14; $index++) {
            $day = $start->addDays($index);
            $key = $day->toDateString();

            $labels[] = $day->format('M j');
            $points[] = (int) ($runsByDay[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Runs',
                    'data' => $points,
                    'backgroundColor' => 'rgba(20, 184, 166, 0.16)',
                    'borderColor' => 'rgba(20, 184, 166, 1)',
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
