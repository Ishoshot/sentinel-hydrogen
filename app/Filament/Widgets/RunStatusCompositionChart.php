<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminRunStatusComposition;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Filament\Widgets\Concerns\HasAdminDoughnutChartStyling;
use App\Filament\Widgets\Concerns\HasChartDataPresence;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Override;

final class RunStatusCompositionChart extends ChartWidget
{
    use HasAdminDoughnutChartStyling;
    use HasChartDataPresence;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Run Status Composition';

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    #[Override]
    public function getDescription(): string
    {
        if (! $this->chartHasVisibleData()) {
            return 'No run status data available for the selected period.';
        }

        return 'Status mix for runs in the selected period.';
    }

    #[Override]
    protected function getData(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

        return app(FetchAdminRunStatusComposition::class)->handle($filters);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
