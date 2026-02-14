<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Workspace;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;

interface PolarBillingServiceContract
{
    /**
     * Determine whether Polar billing integration is fully configured.
     */
    public function isConfigured(): bool;

    /**
     * Create a checkout session URL for a workspace plan purchase.
     */
    public function createCheckoutSession(
        Workspace $workspace,
        Plan $plan,
        BillingInterval $interval = BillingInterval::Monthly,
        ?Promotion $promotion = null,
        ?string $successUrl = null,
        ?string $customerEmail = null,
    ): string;

    /**
     * Update an existing Polar subscription to a new plan/interval.
     */
    public function updateSubscription(
        Workspace $workspace,
        string $polarSubscriptionId,
        Plan $plan,
        BillingInterval $interval = BillingInterval::Monthly,
    ): void;

    /**
     * Revoke a Polar subscription for a workspace.
     */
    public function revokeSubscription(Workspace $workspace, string $polarSubscriptionId): void;

    /**
     * Create a customer portal session URL for subscription management.
     */
    public function createCustomerPortalSession(Workspace $workspace, ?string $returnUrl = null): string;

    /**
     * @param  array{webhook-id: string, webhook-signature: string, webhook-timestamp: string}  $headers
     */
    public function verifyWebhook(string $payload, array $headers): VerifiedPolarWebhook;
}
