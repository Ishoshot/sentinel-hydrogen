<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Billing\Contracts\PolarBillingServiceContract;

final readonly class SubscriptionBillingCoordinator
{
    /**
     * Create a new coordinator instance.
     */
    public function __construct(
        private PolarBillingServiceContract $billingService,
    ) {}

    /**
     * Resolve current billing context for a workspace.
     */
    public function resolveContext(Workspace $workspace): ResolvedBillingContext
    {
        return ResolvedBillingContext::forWorkspace($workspace);
    }

    /**
     * Update an existing Polar subscription when available.
     */
    public function updateWhenAvailable(
        Workspace $workspace,
        ?string $polarSubscriptionId,
        Plan $plan,
        BillingInterval $interval,
    ): bool {
        if (! $this->billingService->isConfigured() || $polarSubscriptionId === null) {
            return false;
        }

        $this->billingService->updateSubscription($workspace, $polarSubscriptionId, $plan, $interval);

        return true;
    }

    /**
     * Revoke an existing Polar subscription when available.
     */
    public function revokeWhenAvailable(Workspace $workspace, ?string $polarSubscriptionId): void
    {
        if (! $this->billingService->isConfigured() || $polarSubscriptionId === null) {
            return;
        }

        $this->billingService->revokeSubscription($workspace, $polarSubscriptionId);
    }
}
