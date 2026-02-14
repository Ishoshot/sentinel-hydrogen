<?php

declare(strict_types=1);

namespace App\Services\Plans\Checkers;

use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Workspace;
use App\Services\Plans\Loggers\PlanLimitEventLogger;
use App\Services\Plans\Resolvers\PlanResolver;
use App\Services\Plans\ValueObjects\PlanLimitResult;
use DateTimeInterface;

final readonly class PlanSubscriptionEligibilityChecker
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private PlanResolver $planResolver,
        private PlanLimitEventLogger $eventLogger,
    ) {}

    /**
     * Ensure the workspace has an active, billable subscription state.
     */
    public function ensureActiveSubscription(Workspace $workspace): PlanLimitResult
    {
        $status = $workspace->subscription_status;

        if ($status instanceof SubscriptionStatus && $status->isActive()) {
            return $this->validateSubscriptionRecord($workspace);
        }

        if ($status === SubscriptionStatus::Canceled) {
            $latestSubscription = $workspace->subscriptions()->latest()->first();

            if ($latestSubscription?->ends_at instanceof DateTimeInterface
                && $latestSubscription->ends_at->getTimestamp() > time()) {
                return $this->validateSubscriptionRecord($workspace);
            }
        }

        $message = 'Your subscription is inactive. Upgrade to restore review access.';
        $this->eventLogger->log($workspace, 'subscription_inactive', $message);

        return PlanLimitResult::deny($message, 'subscription_inactive');
    }

    /**
     * Validate subscription records for paid-tier workspaces.
     */
    private function validateSubscriptionRecord(Workspace $workspace): PlanLimitResult
    {
        $plan = $this->planResolver->resolve($workspace);
        $tier = PlanTier::tryFrom($plan->tier) ?? PlanTier::Foundation;

        if ($tier->isFree()) {
            return PlanLimitResult::allow();
        }

        $subscription = $workspace->subscriptions()->latest()->first();

        if ($subscription === null) {
            $message = 'No subscription record found. Please contact support or re-subscribe.';
            $this->eventLogger->log($workspace, 'subscription_missing', $message);

            return PlanLimitResult::deny($message, 'subscription_missing');
        }

        $periodEnd = $subscription->current_period_end;

        if ($periodEnd instanceof DateTimeInterface && $periodEnd->getTimestamp() < time()) {
            $message = 'Your subscription period has expired. Please renew to continue.';
            $this->eventLogger->log($workspace, 'subscription_expired', $message);

            return PlanLimitResult::deny($message, 'subscription_expired');
        }

        return PlanLimitResult::allow();
    }
}
