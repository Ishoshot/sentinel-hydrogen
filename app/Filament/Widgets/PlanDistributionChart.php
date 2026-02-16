<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminPlanDistribution;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Filament\Widgets\Concerns\HasAdminDoughnutChartStyling;
use App\Filament\Widgets\Concerns\HasChartDataPresence;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Override;

final class PlanDistributionChart extends ChartWidget
{
    use HasAdminDoughnutChartStyling;
    use HasChartDataPresence;
    use InteractsWithPageFilters;

    protected ?string $heading = 'Plan Distribution';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    #[Override]
    public function getDescription(): string
    {
        if (! $this->chartHasVisibleData()) {
            return 'No workspaces match the current plan filters.';
        }

        return 'Workspace allocation across plan tiers.';
    }

    #[Override]
    protected function getData(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

        return app(FetchAdminPlanDistribution::class)->handle($filters);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
