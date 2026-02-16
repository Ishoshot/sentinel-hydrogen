<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dashboard;

use App\Actions\Admin\Dashboard\Factories\AdminDashboardCacheKeyFactory;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final readonly class FetchAdminSubscriptionHealth
{
    public function __construct(
        private AdminDashboardCacheKeyFactory $cacheKeyFactory = new AdminDashboardCacheKeyFactory,
    ) {}

    /**
     * @return array{
     *     active_or_trialing: int,
     *     past_due: int,
     *     canceled_or_revoked: int,
     *     renewals_due_next_7_days: int,
     *     trials_ending_next_7_days: int
     * }
     */
    public function handle(AdminDashboardFilters $filters): array
    {
        $cacheKey = $this->cacheKeyFactory->forSubscriptionHealth($filters);

        /** @var array{
         *     active_or_trialing: int,
         *     past_due: int,
         *     canceled_or_revoked: int,
         *     renewals_due_next_7_days: int,
         *     trials_ending_next_7_days: int
         * } $result
         */
        $result = Cache::remember($cacheKey, now()->addSeconds(90), function () use ($filters): array {
            $now = now();
            $windowEnd = $now->copy()->addDays(7);

            $workspaceScope = Workspace::query()
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('workspaces.id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                });

            $activeOrTrialing = (clone $workspaceScope)
                ->whereIn('workspaces.subscription_status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trialing->value,
                ])
                ->count();

            $pastDue = (clone $workspaceScope)
                ->where('workspaces.subscription_status', SubscriptionStatus::PastDue->value)
                ->count();

            $canceledOrRevoked = (clone $workspaceScope)
                ->whereIn('workspaces.subscription_status', [
                    SubscriptionStatus::Canceled->value,
                    SubscriptionStatus::Revoked->value,
                ])
                ->count();

            $trialsEndingSoon = (clone $workspaceScope)
                ->whereNotNull('workspaces.trial_ends_at')
                ->whereBetween('workspaces.trial_ends_at', [$now, $windowEnd])
                ->count();

            $renewalsDueSoon = Subscription::query()
                ->join('workspaces', 'workspaces.id', '=', 'subscriptions.workspace_id')
                ->leftJoin('plans', 'plans.id', '=', 'workspaces.plan_id')
                ->whereIn('subscriptions.status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::Trialing->value,
                ])
                ->whereBetween('subscriptions.current_period_end', [$now, $windowEnd])
                ->when($filters->workspaceId !== null, function (Builder $query) use ($filters): void {
                    $query->where('workspaces.id', $filters->workspaceId);
                })
                ->when($filters->planTier !== null, function (Builder $query) use ($filters): void {
                    $query->where('plans.tier', $filters->planTier?->value);
                })
                ->distinct()
                ->count('subscriptions.workspace_id');

            return [
                'active_or_trialing' => $activeOrTrialing,
                'past_due' => $pastDue,
                'canceled_or_revoked' => $canceledOrRevoked,
                'renewals_due_next_7_days' => $renewalsDueSoon,
                'trials_ending_next_7_days' => $trialsEndingSoon,
            ];
        });

        return $result;
    }
}
