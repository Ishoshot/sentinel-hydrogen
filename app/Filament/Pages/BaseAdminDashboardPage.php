<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Billing\PlanTier;
use App\Enums\Reviews\RunStatus;
use App\Models\Workspace;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use Override;

abstract class BaseAdminDashboardPage extends BaseDashboard
{
    use HasFiltersAction;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /**
     * @return array<class-string>
     */
    abstract protected function getDashboardWidgets(): array;

    #[Override]
    final public function getColumns(): array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    #[Override]
    final public function getSubNavigation(): array
    {
        return [
            NavigationItem::make('Overview')
                ->icon(Heroicon::Home)
                ->url(Dashboard::getUrl())
                ->isActiveWhen(fn (): bool => request()->routeIs(Dashboard::getRouteName())),
            NavigationItem::make('Reliability')
                ->icon(Heroicon::ChartBarSquare)
                ->url(DashboardReliability::getUrl())
                ->isActiveWhen(fn (): bool => request()->routeIs(DashboardReliability::getRouteName())),
        ];
    }

    /**
     * @return array<class-string>
     */
    #[Override]
    final public function getWidgets(): array
    {
        return $this->getDashboardWidgets();
    }

    /**
     * @return array<string>
     */
    #[Override]
    final public function getPageClasses(): array
    {
        return ['fi-page-admin-dashboard'];
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('Filter Dashboard')
                ->icon(Heroicon::Funnel)
                ->schema([
                    DatePicker::make('start_date')
                        ->label('From')
                        ->native(false)
                        ->maxDate(now())
                        ->default(now()->subDays(13)->toDateString())
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('To')
                        ->native(false)
                        ->maxDate(now())
                        ->default(now()->toDateString())
                        ->afterOrEqual('start_date')
                        ->required(),
                    Select::make('workspace_id')
                        ->label('Workspace')
                        ->options(fn (): array => Workspace::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->native(false),
                    Select::make('plan_tier')
                        ->label('Plan Tier')
                        ->options(
                            collect(PlanTier::cases())
                                ->mapWithKeys(fn (PlanTier $tier): array => [
                                    $tier->value => str($tier->value)->title()->toString(),
                                ])
                                ->all()
                        )
                        ->native(false),
                    Select::make('run_status')
                        ->label('Run Status')
                        ->options(
                            collect(RunStatus::cases())
                                ->mapWithKeys(fn (RunStatus $status): array => [
                                    $status->value => str($status->value)->replace('_', ' ')->title()->toString(),
                                ])
                                ->all()
                        )
                        ->native(false),
                ]),
        ];
    }
}
