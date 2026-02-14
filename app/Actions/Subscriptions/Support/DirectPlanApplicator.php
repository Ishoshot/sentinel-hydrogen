<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Actions\Subscriptions\ApplyWorkspacePlanChange;
use App\Actions\Subscriptions\Factories\ChangeResponseFactory;
use App\Actions\Subscriptions\Handlers\PromotionHandler;
use App\Enums\Billing\BillingInterval;
use App\Enums\Workspace\ActivityType;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Workspace;

/**
 * Applies a plan change directly and records any completed promotion usage.
 */
final readonly class DirectPlanApplicator
{
    /**
     * Create a new DirectPlanApplicator instance.
     */
    public function __construct(
        private ApplyWorkspacePlanChange $applyWorkspacePlanChange,
        private PromotionHandler $promotionHandler,
    ) {}

    /**
     * Apply the plan change and build the response.
     *
     * @return array{action: string, subscription: \App\Models\Subscription, billing_interval: string}
     */
    public function apply(
        string $action,
        Workspace $workspace,
        Plan $plan,
        ActivityType $activityType,
        BillingInterval $interval,
        ?Promotion $promotion,
        ?User $actor,
    ): array {
        $subscription = $this->applyWorkspacePlanChange->applyActivePlan(
            $workspace,
            $plan,
            $activityType,
            $actor,
        );

        $this->promotionHandler->recordCompleted($workspace, $promotion, $subscription);

        return ChangeResponseFactory::subscription($action, $subscription, $interval);
    }
}
