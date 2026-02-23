<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminAtRiskWorkspaces;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Override;

final class AtRiskWorkspaces extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading('At-Risk Workspaces')
            ->records(function (): Collection {
                $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);

                return collect(app(FetchAdminAtRiskWorkspaces::class)->handle($filters));
            })
            ->columns([
                TextColumn::make('workspace_name')
                    ->label('Workspace')
                    ->weight('medium'),
                TextColumn::make('plan_tier')
                    ->label('Plan')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('subscription_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Past Due' => 'danger',
                        'Trialing', 'Active' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('risk_reason')
                    ->label('Risk')
                    ->badge()
                    ->color(fn (string $state): string => str($state)->contains('Past due') ? 'danger' : 'warning'),
                TextColumn::make('next_renewal_at')
                    ->label('Renewal')
                    ->placeholder('N/A')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? 'N/A' : (string) str($state)->before('.')),
                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->placeholder('N/A')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? 'N/A' : (string) str($state)->before('.')),
            ])
            ->paginated(false);
    }
}
