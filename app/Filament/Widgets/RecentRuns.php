<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Reviews\RunStatus;
use App\Models\Run;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

final class RecentRuns extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Runs')
            ->query(fn (): Builder => Run::query()
                ->with(['workspace:id,name', 'repository:id,full_name'])
                ->latest('created_at'))
            ->columns([
                TextColumn::make('workspace.name')
                    ->label('Workspace')
                    ->searchable(),
                TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->searchable()
                    ->placeholder('N/A'),
                TextColumn::make('pr_number')
                    ->label('PR')
                    ->placeholder('N/A'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (RunStatus $state): string => match ($state) {
                        RunStatus::Completed => 'success',
                        RunStatus::InProgress, RunStatus::Queued => 'warning',
                        RunStatus::Failed => 'danger',
                        RunStatus::Skipped => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'N/A' : sprintf('%ds', $state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(
                        collect(RunStatus::cases())
                            ->mapWithKeys(fn (RunStatus $status): array => [
                                $status->value => str($status->value)->replace('_', ' ')->title()->toString(),
                            ])
                            ->all(),
                    ),
            ]);
    }
}
