<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminAtRiskWorkspaces
{
    public function __construct(
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array<int, array{
     *     workspace_name: string,
     *     plan_tier: string,
     *     subscription_status: string,
     *     risk_reason: string,
     *     trial_ends_at: string|null,
     *     next_renewal_at: string|null
     * }>
     */
    public function handle(AdminDashboardFilters $filters, int $limit = 12): array
    {
        $cacheKey = $this->cacheKeyFactory->forAtRiskWorkspaces($filters, $limit);

        /** @var array<int, array{
         *     workspace_name: string,
         *     plan_tier: string,
         *     subscription_status: string,
         *     risk_reason: string,
         *     trial_ends_at: string|null,
         *     next_renewal_at: string|null
         * }> $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(90), function () use ($filters, $limit): array {
            $now = now();
            $windowEnd = $now->copy()->addDays(7);

            $renewalSubquery = Subscription::query()
                ->selectRaw('workspace_id, MAX(current_period_end) as next_renewal_at')
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trialing->value,
                    SubscriptionStatus::PastDue->value,
                ])
                ->groupBy('workspace_id');

            $rows = Workspace::query()
                ->selectRaw('workspaces.name as workspace_name')
                ->selectRaw('COALESCE(plans.tier, ?) as plan_tier', ['unknown'])
                ->selectRaw('workspaces.subscription_status as subscription_status')
                ->selectRaw('workspaces.trial_ends_at as trial_ends_at')
                ->selectRaw('renewals.next_renewal_at as next_renewal_at')
                ->selectRaw('CASE
                    WHEN workspaces.subscription_status = ? THEN ?
                    WHEN workspaces.trial_ends_at IS NOT NULL AND workspaces.trial_ends_at BETWEEN ? AND ? THEN ?
                    WHEN renewals.next_renewal_at IS NOT NULL AND renewals.next_renewal_at BETWEEN ? AND ? THEN ?
                    ELSE ?
                END as risk_reason', [
                    SubscriptionStatus::PastDue->value,
                    'Past due billing',
                    $now,
                    $windowEnd,
                    'Trial ending soon',
                    $now,
                    $windowEnd,
                    'Renewal due soon',
                    'Monitor',
                ])
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->leftJoinSub($renewalSubquery, 'renewals', function (JoinClause $join): void {
                    $join->on('renewals.workspace_id', '=', 'workspaces.id');
                })
                ->where(function (Builder $query) use ($now, $windowEnd): void {
                    $query
                        ->where('workspaces.subscription_status', SubscriptionStatus::PastDue->value)
                        ->orWhere(function (Builder $trialQuery) use ($now, $windowEnd): void {
                            $trialQuery->whereNotNull('workspaces.trial_ends_at')
                                ->whereBetween('workspaces.trial_ends_at', [$now, $windowEnd]);
                        })
                        ->orWhere(function (Builder $renewalQuery) use ($now, $windowEnd): void {
                            $renewalQuery->whereNotNull('renewals.next_renewal_at')
                                ->whereBetween('renewals.next_renewal_at', [$now, $windowEnd]);
                        });
                })
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('workspaces.id', $filters->workspaceId);
                })
                ->when($filters->planTier instanceof \App\Enums\Billing\PlanTier, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->orderByRaw('CASE WHEN workspaces.subscription_status = ? THEN 0 ELSE 1 END', [
                    SubscriptionStatus::PastDue->value,
                ])
                ->orderBy('renewals.next_renewal_at')
                ->orderBy('workspaces.trial_ends_at')
                ->limit($limit)
                ->get();

            return $this->mapRows($rows);
        });

        return $result;
    }

    /**
     * @param  Collection<int, Workspace>  $rows
     * @return array<int, array{
     *     workspace_name: string,
     *     plan_tier: string,
     *     subscription_status: string,
     *     risk_reason: string,
     *     trial_ends_at: string|null,
     *     next_renewal_at: string|null
     * }>
     */
    private function mapRows(Collection $rows): array
    {
        return $rows
            ->map(function (Workspace $row): array {
                $trialEndsAt = data_get($row, 'trial_ends_at');
                $nextRenewalAt = data_get($row, 'next_renewal_at');

                return [
                    'workspace_name' => (string) data_get($row, 'workspace_name', 'Unknown'),
                    'plan_tier' => str((string) data_get($row, 'plan_tier', 'unknown'))->title()->toString(),
                    'subscription_status' => $this->formatSubscriptionStatus(data_get($row, 'subscription_status')),
                    'risk_reason' => (string) data_get($row, 'risk_reason', 'Monitor'),
                    'trial_ends_at' => $this->formatDateValue($trialEndsAt),
                    'next_renewal_at' => $this->formatDateValue($nextRenewalAt),
                ];
            })
            ->values()
            ->all();
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        return is_string($value) ? $value : null;
    }

    private function formatSubscriptionStatus(mixed $value): string
    {
        if ($value instanceof SubscriptionStatus) {
            $value = $value->value;
        }

        return str((string) ($value ?? 'unknown'))
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}
