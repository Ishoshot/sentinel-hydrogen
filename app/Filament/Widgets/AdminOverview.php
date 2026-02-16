<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\AiOption;
use App\Models\Briefing;
use App\Models\Promotion;
use App\Models\Run;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AdminOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $now = CarbonImmutable::now();
        $todayStart = $now->startOfDay();
        $yesterdayStart = $todayStart->subDay();

        $runsToday = Run::query()->where('created_at', '>=', $todayStart)->count();
        $runsYesterday = Run::query()
            ->whereBetween('created_at', [$yesterdayStart, $todayStart])
            ->count();

        $runDelta = $runsToday - $runsYesterday;

        return [
            Stat::make('Workspaces', Workspace::query()->count())
                ->description('Total connected workspaces')
                ->icon(Heroicon::BuildingOffice2)
                ->color('primary'),
            Stat::make('Runs Today', $runsToday)
                ->description(sprintf('%+d vs yesterday', $runDelta))
                ->descriptionIcon($runDelta >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->icon(Heroicon::ChartBar)
                ->color($runDelta >= 0 ? 'success' : 'danger'),
            Stat::make('Active Promotions', Promotion::query()->where('is_active', true)->count())
                ->description('Codes currently enabled')
                ->icon(Heroicon::Gift)
                ->color('warning'),
            Stat::make('Active Briefings', Briefing::query()->where('is_active', true)->count())
                ->description('Templates available to users')
                ->icon(Heroicon::DocumentText)
                ->color('success'),
            Stat::make('Active AI Models', AiOption::query()->where('is_active', true)->count())
                ->description('Provider models available')
                ->icon(Heroicon::CpuChip)
                ->color('info'),
        ];
    }
}
