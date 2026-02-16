<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminSeverityTrend;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Filament\Widgets\Concerns\HasAdminLineChartStyling;
use App\Filament\Widgets\Concerns\HasChartDataPresence;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

final class SeverityTrendChart extends ChartWidget
{
    use HasAdminLineChartStyling;
    use HasChartDataPresence;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Finding Severity Trend';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public function getDescription(): ?string
    {
        if (! $this->chartHasVisibleData()) {
            return 'No findings were recorded for the selected period.';
        }

        return 'Daily distribution of findings by severity level.';
    }

    protected function getData(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

        return $this->collapseLineChartWhenEmpty(
            app(FetchAdminSeverityTrend::class)->handle($filters)
        );
    }

    protected function getType(): string
    {
        return 'line';
    }
}
