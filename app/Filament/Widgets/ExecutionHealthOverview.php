<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminExecutionHealth;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class ExecutionHealthOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = '30s';

    protected ?string $heading = 'Execution Health';

    protected ?string $description = 'Queue pressure and failure hotspots requiring operational attention.';

    protected int|string|array $columnSpan = 'full';

    #[Override]
    protected function getStats(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);
        $health = app(FetchAdminExecutionHealth::class)->handle($filters);

        return [
            Stat::make('Stuck Queue', $health['stuck_queued_runs'])
                ->description('Queued over 15 minutes')
                ->icon(Heroicon::Clock)
                ->color($health['stuck_queued_runs'] > 0 ? 'warning' : 'success'),
            Stat::make('Long Running', $health['long_running_runs'])
                ->description('In-progress over 20 minutes')
                ->icon(Heroicon::ArrowPath)
                ->color($health['long_running_runs'] > 0 ? 'warning' : 'success'),
            Stat::make('Failed Commands', $health['failed_command_runs'])
                ->description('Command executions failed')
                ->icon(Heroicon::CommandLine)
                ->color($health['failed_command_runs'] > 0 ? 'danger' : 'success'),
            Stat::make('Failed Briefings', $health['failed_briefing_generations'])
                ->description('Briefing generations failed')
                ->icon(Heroicon::DocumentText)
                ->color($health['failed_briefing_generations'] > 0 ? 'danger' : 'success'),
            Stat::make('Briefings Processing', $health['processing_briefings'])
                ->description('Currently generating')
                ->icon(Heroicon::CpuChip)
                ->color('info'),
        ];
    }
}
