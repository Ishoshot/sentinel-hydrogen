<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminRunDurationTrend;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Filament\Widgets\Concerns\HasAdminLineChartStyling;
use App\Filament\Widgets\Concerns\HasChartDataPresence;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

final class RunDurationTrendChart extends ChartWidget
{
    use HasAdminLineChartStyling;
    use HasChartDataPresence;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Run Duration Trend';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public function getDescription(): ?string
    {
        if (! $this->chartHasVisibleData()) {
            return 'No run duration data available for these filters.';
        }

        return 'Tracks average and slowest run duration per day.';
    }

    protected function getData(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

        return $this->collapseLineChartWhenEmpty(
            app(FetchAdminRunDurationTrend::class)->handle($filters)
        );
    }

    protected function getType(): string
    {
        return 'line';
    }
}
