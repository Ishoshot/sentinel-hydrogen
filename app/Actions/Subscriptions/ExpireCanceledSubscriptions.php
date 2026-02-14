<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Activities\LogActivity;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Workspace\ActivityType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\PlanDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ExpireCanceledSubscriptions
{
    /**
     * Create a new action instance.
     */
    public function __construct(private LogActivity $logActivity) {}

    /**
     * Expire canceled subscriptions whose access period ended.
     *
     * @return array{
     *     count: int,
     *     expired: array<int, array{subscription_id: int, workspace_id: int}>
     * }
     */
    public function handle(): array
    {
        $foundationPlan = Plan::query()->firstOrCreate(
            ['tier' => PlanTier::Foundation->value],
            PlanDefaults::forTier(PlanTier::Foundation),
        );

        $expiredSubscriptions = [];

        Subscription::query()
            ->with('workspace.plan')
            ->where('status', SubscriptionStatus::Canceled)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->whereHas('workspace', function (Builder $query) use ($foundationPlan): void {
                $query->where('plan_id', '!=', $foundationPlan->id);
            })
            ->chunkById(100, function (Collection $subscriptions) use ($foundationPlan, &$expiredSubscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $workspace = $subscription->workspace;

                    if ($workspace === null) {
                        continue;
                    }

                    DB::transaction(function () use ($subscription, $workspace, $foundationPlan): void {
                        $subscription->update([
                            'status' => SubscriptionStatus::Revoked,
                        ]);

                        $workspace->update([
                            'plan_id' => $foundationPlan->id,
                            'subscription_status' => SubscriptionStatus::Revoked,
                        ]);

                        $this->logActivity->handle(
                            workspace: $workspace,
                            type: ActivityType::SubscriptionExpired,
                            description: 'Subscription expired and workspace downgraded to Foundation plan.',
                            subject: $subscription,
                        );
                    });

                    $expiredSubscriptions[] = [
                        'subscription_id' => $subscription->id,
                        'workspace_id' => $subscription->workspace_id,
                    ];
                }
            });

        return [
            'count' => count($expiredSubscriptions),
            'expired' => $expiredSubscriptions,
        ];
    }
}
