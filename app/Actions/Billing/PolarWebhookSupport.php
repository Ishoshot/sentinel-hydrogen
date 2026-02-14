<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Billing\Support\PolarBillingIntervalExtractor;
use App\Actions\Billing\Support\PolarPromotionUsageConfirmer;
use App\Actions\Billing\Support\PolarWebhookPlanResolver;
use App\Actions\Billing\Support\PolarWebhookTimestampParser;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

final class PolarWebhookSupport
{
    /**
     * Create a new helper instance.
     */
    public function __construct(
        private ?PolarBillingIntervalExtractor $billingIntervalExtractor = null,
        private ?PolarPromotionUsageConfirmer $promotionUsageConfirmer = null,
        private ?PolarWebhookPlanResolver $planResolver = null,
        private ?PolarWebhookTimestampParser $timestampParser = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $subscriptionData
     * @param  array<string, mixed>|null  $orderData
     */
    public function extractBillingInterval(?array $subscriptionData, ?array $orderData): ?BillingInterval
    {
        return $this->billingIntervalExtractor()->extract($subscriptionData, $orderData);
    }

    /**
     * Confirm pending promotion usage once a subscription is created.
     */
    public function confirmPromotionUsage(Workspace $workspace, Subscription $subscription, mixed $promotionId): void
    {
        $this->promotionUsageConfirmer()->confirm($workspace, $subscription, $promotionId);
    }

    /**
     * Resolve a plan model for the given tier value.
     */
    public function resolvePlan(?string $tier): ?Plan
    {
        return $this->planResolver()->resolve($tier);
    }

    /**
     * Map a Polar status value into the local subscription status enum.
     */
    public function mapPolarStatus(?string $polarStatus): SubscriptionStatus
    {
        return match ($polarStatus) {
            'trialing' => SubscriptionStatus::Trialing,
            'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => SubscriptionStatus::PastDue,
            'canceled', 'cancelled' => SubscriptionStatus::Canceled,
            'revoked' => SubscriptionStatus::Revoked,
            default => SubscriptionStatus::Active,
        };
    }

    /**
     * Convert a timestamp payload value to an immutable date instance.
     */
    public function timestampToDateTime(mixed $timestamp): ?CarbonImmutable
    {
        return $this->timestampParser()->parse($timestamp);
    }

    /**
     * BillingIntervalExtractor.
     */
    private function billingIntervalExtractor(): PolarBillingIntervalExtractor
    {
        return $this->billingIntervalExtractor ?? new PolarBillingIntervalExtractor;
    }

    /**
     * PromotionUsageConfirmer.
     */
    private function promotionUsageConfirmer(): PolarPromotionUsageConfirmer
    {
        return $this->promotionUsageConfirmer ?? new PolarPromotionUsageConfirmer;
    }

    /**
     * PlanResolver.
     */
    private function planResolver(): PolarWebhookPlanResolver
    {
        return $this->planResolver ?? new PolarWebhookPlanResolver;
    }

    /**
     * TimestampParser.
     */
    private function timestampParser(): PolarWebhookTimestampParser
    {
        return $this->timestampParser ?? new PolarWebhookTimestampParser;
    }
}
