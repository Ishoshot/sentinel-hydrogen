<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminPipelineThroughputTrend;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Filament\Widgets\Concerns\HasAdminLineChartStyling;
use App\Filament\Widgets\Concerns\HasChartDataPresence;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

final class PipelineThroughputTrendChart extends ChartWidget
{
    use HasAdminLineChartStyling;
    use HasChartDataPresence;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Pipeline Throughput';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public function getDescription(): ?string
    {
        if (! $this->chartHasVisibleData()) {
            return 'No pipeline activity found for the selected period.';
        }

        return 'Daily throughput across runs, commands, and briefings.';
    }

    protected function getData(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

        return $this->collapseLineChartWhenEmpty(
            app(FetchAdminPipelineThroughputTrend::class)->handle($filters)
        );
    }

    protected function getType(): string
    {
        return 'line';
    }
}
