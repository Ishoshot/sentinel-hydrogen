<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminRunsTrend;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Filament\Widgets\Concerns\HasAdminLineChartStyling;
use App\Filament\Widgets\Concerns\HasChartDataPresence;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Override;

final class RunsTrendChart extends ChartWidget
{
    use HasAdminLineChartStyling;
    use HasChartDataPresence;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Run Volume';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    #[Override]
    public function getDescription(): string
    {
        if (! $this->chartHasVisibleData()) {
            return 'No runs found for the selected filters.';
        }

        return 'Daily run count for the selected date range.';
    }

    #[Override]
    protected function getData(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

        return $this->collapseLineChartWhenEmpty(
            app(FetchAdminRunsTrend::class)->handle($filters)
        );
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function lineChartLegendVisible(): bool
    {
        return false;
    }
}
