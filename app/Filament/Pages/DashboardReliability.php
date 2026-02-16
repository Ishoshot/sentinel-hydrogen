<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\PipelineReliabilityOverview;
use App\Filament\Widgets\PipelineThroughputTrendChart;
use App\Filament\Widgets\RepositoryReliability;
use App\Filament\Widgets\RunDurationTrendChart;
use App\Filament\Widgets\RunStatusCompositionChart;
use App\Filament\Widgets\SeverityTrendChart;

final class DashboardReliability extends BaseAdminDashboardPage
{
    protected static string $routePath = '/reliability';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Dashboard Reliability';

    /**
     * @return array<class-string>
     */
    protected function getDashboardWidgets(): array
    {
        return [
            PipelineReliabilityOverview::class,
            PipelineThroughputTrendChart::class,
            RunDurationTrendChart::class,
            RunStatusCompositionChart::class,
            SeverityTrendChart::class,
            RepositoryReliability::class,
        ];
    }
}
