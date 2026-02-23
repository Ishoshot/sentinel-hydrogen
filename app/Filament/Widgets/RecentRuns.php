<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\Builders\AdminDashboardRunQueryBuilder;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Reviews\RunStatus;
use App\Models\Run;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Override;

final class RecentRuns extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(new HtmlString('<span class="fi-ta-header-heading-recent-runs">Recent Runs</span>'))
            ->query(function (): Builder {
                $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

                $query = Run::query()
                    ->with(['workspace:id,name', 'repository:id,full_name'])
                    ->latest('created_at');

                app(AdminDashboardRunQueryBuilder::class)->applyTo($query, $filters);

                return $query;
            })
            ->columns([
                TextColumn::make('workspace.name')
                    ->label('Workspace')
                    ->searchable(),
                TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->searchable()
                    ->placeholder('N/A'),
                TextColumn::make('pr_number')
                    ->label('Pull Request')
                    ->formatStateUsing(function (?int $state, Run $record): string {
                        $prNumber = $record->getEffectivePrNumber();

                        if ($prNumber === null) {
                            return 'N/A';
                        }

                        return sprintf('PR #%d', $prNumber);
                    })
                    ->description(fn (Run $record): ?string => $record->getEffectivePrTitle())
                    ->placeholder('N/A')
                    ->wrap(),
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
            ])
            ->defaultPaginationPageOption(25);
    }
}
