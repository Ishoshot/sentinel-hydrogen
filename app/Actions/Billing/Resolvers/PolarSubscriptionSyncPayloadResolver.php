<?php

declare(strict_types=1);

namespace App\Actions\Billing\Resolvers;

use App\Actions\Billing\PolarWebhookSupport;
use App\Actions\Billing\Support\PolarSubscriptionSyncPayload;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use Carbon\CarbonImmutable;

/**
 * Extracts normalized local subscription updates from Polar webhook payloads.
 */
final readonly class PolarSubscriptionSyncPayloadResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(private PolarWebhookSupport $polarWebhookSupport) {}

    /**
     * @param  array<string, mixed>  $subscription
     */
    public function resolve(array $subscription, ?SubscriptionStatus $overrideStatus): PolarSubscriptionSyncPayload
    {
        $polarStatus = $subscription['status'] ?? null;
        $status = $overrideStatus ?? $this->polarWebhookSupport->mapPolarStatus($polarStatus);

        $plan = $this->resolvePlan($subscription);

        /** @var array<string, mixed> $subscriptionTyped */
        $subscriptionTyped = $subscription;
        $billingInterval = $this->polarWebhookSupport->extractBillingInterval($subscriptionTyped, null);

        $periodStart = $this->polarWebhookSupport->timestampToDateTime($subscription['current_period_start'] ?? null);
        $periodEnd = $this->polarWebhookSupport->timestampToDateTime($subscription['current_period_end'] ?? null);

        $updateAttributes = $this->buildUpdateAttributes($status, $plan, $billingInterval, $periodStart, $periodEnd);

        return new PolarSubscriptionSyncPayload(
            status: $status,
            plan: $plan,
            billingInterval: $billingInterval,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            updateAttributes: $updateAttributes,
        );
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function resolvePlan(array $subscription): ?Plan
    {
        $productData = $subscription['product'] ?? null;
        if (! is_array($productData)) {
            return null;
        }

        $productMetadata = $productData['metadata'] ?? [];
        $planTier = $productMetadata['plan_tier'] ?? null;

        if (! is_string($planTier)) {
            return null;
        }

        return $this->polarWebhookSupport->resolvePlan($planTier);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUpdateAttributes(
        SubscriptionStatus $status,
        ?Plan $plan,
        ?BillingInterval $billingInterval,
        ?CarbonImmutable $periodStart,
        ?CarbonImmutable $periodEnd,
    ): array {
        $updateAttributes = ['status' => $status];

        if ($plan instanceof Plan) {
            $updateAttributes['plan_id'] = $plan->id;
        }

        if ($billingInterval instanceof BillingInterval) {
            $updateAttributes['billing_interval'] = $billingInterval;
        }

        if ($periodStart instanceof CarbonImmutable) {
            $updateAttributes['current_period_start'] = $periodStart;
        }

        if ($periodEnd instanceof CarbonImmutable) {
            $updateAttributes['current_period_end'] = $periodEnd;
        }

        return $updateAttributes;
    }
}
