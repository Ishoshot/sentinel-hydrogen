<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminPipelineReliability;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PipelineReliabilityOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = '45s';

    protected ?string $heading = 'Pipeline Reliability';

    protected ?string $description = 'Success rates and latency profile across reviews, commands, and briefings.';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);
        $metrics = app(FetchAdminPipelineReliability::class)->handle($filters);

        return [
            Stat::make('Run Success', sprintf('%.1f%%', $metrics['run_success_rate']))
                ->description('Completed vs failed reviews')
                ->icon(Heroicon::ChartBar)
                ->color($this->rateColor($metrics['run_success_rate'])),
            Stat::make('Command Success', sprintf('%.1f%%', $metrics['command_success_rate']))
                ->description('Completed vs failed commands')
                ->icon(Heroicon::CommandLine)
                ->color($this->rateColor($metrics['command_success_rate'])),
            Stat::make('Briefing Success', sprintf('%.1f%%', $metrics['briefing_success_rate']))
                ->description('Completed vs failed briefings')
                ->icon(Heroicon::DocumentText)
                ->color($this->rateColor($metrics['briefing_success_rate'])),
            Stat::make('Avg Run Duration', sprintf('%ds', $metrics['avg_run_duration_seconds']))
                ->description('Mean review runtime')
                ->icon(Heroicon::Clock)
                ->color('info'),
            Stat::make('P95 Run Duration', sprintf('%ds', $metrics['p95_run_duration_seconds']))
                ->description('Tail latency of reviews')
                ->icon(Heroicon::Bolt)
                ->color('warning'),
            Stat::make('Pipeline Events', $metrics['total_pipeline_events'])
                ->description('Runs + commands + briefings')
                ->icon(Heroicon::CpuChip)
                ->color('primary'),
        ];
    }

    private function rateColor(float $rate): string
    {
        return match (true) {
            $rate >= 95 => 'success',
            $rate >= 80 => 'warning',
            default => 'danger',
        };
    }
}
