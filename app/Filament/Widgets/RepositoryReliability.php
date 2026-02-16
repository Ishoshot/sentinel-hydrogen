<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminRepositoryReliability;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

final class RepositoryReliability extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Repository Reliability')
            ->records(function (): Collection {
                $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

                return collect(app(FetchAdminRepositoryReliability::class)->handle($filters));
            })
            ->columns([
                TextColumn::make('repository_name')
                    ->label('Repository')
                    ->weight('medium')
                    ->limit(38)
                    ->tooltip(fn (string $state): string => $state),
                TextColumn::make('total_runs')
                    ->label('Runs')
                    ->badge()
                    ->alignCenter(),
                TextColumn::make('reliability_score')
                    ->label('Reliability')
                    ->formatStateUsing(fn (float $state): string => sprintf('%.1f%%', $state))
                    ->badge()
                    ->color(fn (float $state): string => $state >= 90 ? 'success' : ($state >= 70 ? 'warning' : 'danger')),
                TextColumn::make('failed_runs')
                    ->label('Failures')
                    ->alignCenter(),
                TextColumn::make('avg_duration_seconds')
                    ->label('Avg Duration')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'N/A' : sprintf('%ds', $state)),
            ])
            ->paginated(false);
    }
}
