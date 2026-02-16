<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminOverview;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class AdminOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = '30s';

    protected ?string $heading = 'Platform Overview';

    protected ?string $description = 'Top-level workspace activity and configuration footprint.';

    #[Override]
    protected function getStats(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);
        $overview = app(FetchAdminOverview::class)->handle($filters);

        $runDelta = $overview['run_delta'];

        return [
            Stat::make('Workspaces in Scope', $overview['workspace_count'])
                ->description('Filtered by workspace and tier')
                ->icon(Heroicon::BuildingOffice2)
                ->color('primary'),
            Stat::make('Runs in Range', $overview['runs_current_period'])
                ->description(sprintf('%+d vs previous %d days', $runDelta, $filters->totalDays()))
                ->descriptionIcon($runDelta >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->icon(Heroicon::ChartBar)
                ->color($runDelta >= 0 ? 'success' : 'danger'),
            Stat::make('Active Promotions', $overview['active_promotions'])
                ->description('Codes currently enabled')
                ->icon(Heroicon::Gift)
                ->color('warning'),
            Stat::make('Active Briefings', $overview['active_briefings'])
                ->description('Templates available to users')
                ->icon(Heroicon::DocumentText)
                ->color('success'),
            Stat::make('Active AI Models', $overview['active_ai_models'])
                ->description('Provider models available')
                ->icon(Heroicon::CpuChip)
                ->color('info'),
        ];
    }
}
