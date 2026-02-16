<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Admin\Dashboard\FetchAdminSubscriptionHealth;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class SubscriptionHealthOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = '45s';

    protected ?string $heading = 'Subscription Health';

    protected ?string $description = 'Billing posture and renewal risk across workspaces in scope.';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = AdminDashboardFilters::fromArray($this->pageFilters ?? []);
        $health = app(FetchAdminSubscriptionHealth::class)->handle($filters);

        return [
            Stat::make('Active / Trialing', $health['active_or_trialing'])
                ->description('Workspaces currently healthy')
                ->icon(Heroicon::CheckCircle)
                ->color('success'),
            Stat::make('Past Due', $health['past_due'])
                ->description('Immediate billing attention')
                ->icon(Heroicon::ExclamationTriangle)
                ->color('danger'),
            Stat::make('Canceled / Revoked', $health['canceled_or_revoked'])
                ->description('Churned workspaces in scope')
                ->icon(Heroicon::XCircle)
                ->color('gray'),
            Stat::make('Renewals (7d)', $health['renewals_due_next_7_days'])
                ->description('Upcoming paid subscription renewals')
                ->icon(Heroicon::ArrowPath)
                ->color('warning'),
            Stat::make('Trials Ending (7d)', $health['trials_ending_next_7_days'])
                ->description('Potential conversion opportunities')
                ->icon(Heroicon::Clock)
                ->color('info'),
        ];
    }
}
