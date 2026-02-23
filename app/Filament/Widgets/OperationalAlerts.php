<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminOperationalAlerts;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Override;

final class OperationalAlerts extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading('Operational Alerts')
            ->records(function (): Collection {
                $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

                return collect(app(FetchAdminOperationalAlerts::class)->handle($filters));
            })
            ->columns([
                TextColumn::make('alert_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('summary')
                    ->label('Summary')
                    ->wrap(),
                TextColumn::make('workspace_name')
                    ->label('Workspace')
                    ->weight('medium'),
                TextColumn::make('repository_name')
                    ->label('Repository')
                    ->placeholder('N/A'),
                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
