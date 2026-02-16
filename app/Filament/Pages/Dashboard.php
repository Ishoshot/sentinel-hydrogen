<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminOverview;
use App\Filament\Widgets\AtRiskWorkspaces;
use App\Filament\Widgets\ExecutionHealthOverview;
use App\Filament\Widgets\OperationalAlerts;
use App\Filament\Widgets\PlanDistributionChart;
use App\Filament\Widgets\RecentRuns;
use App\Filament\Widgets\RunsTrendChart;
use App\Filament\Widgets\SubscriptionHealthOverview;
use App\Filament\Widgets\WorkspaceLeaderboard;

final class Dashboard extends BaseAdminDashboardPage
{
    protected static ?string $title = 'Dashboard';

    /**
     * @return array<class-string>
     */
    protected function getDashboardWidgets(): array
    {
        return [
            AdminOverview::class,
            SubscriptionHealthOverview::class,
            ExecutionHealthOverview::class,
            RunsTrendChart::class,
            PlanDistributionChart::class,
            WorkspaceLeaderboard::class,
            RecentRuns::class,
            AtRiskWorkspaces::class,
            OperationalAlerts::class,
        ];
    }
}
