<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Promotions\PromotionUsageStatus;
use App\Models\Plan;
use App\Models\PromotionUsage;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Support\PlanDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PolarWebhookSupport
{
    /**
     * @param  array<string, mixed>|null  $subscriptionData
     * @param  array<string, mixed>|null  $orderData
     */
    public function extractBillingInterval(?array $subscriptionData, ?array $orderData): ?BillingInterval
    {
        if (is_array($subscriptionData)) {
            $interval = $subscriptionData['recurring_interval'] ?? $subscriptionData['billing_interval'] ?? null;
            if (is_string($interval)) {
                return BillingInterval::tryFrom($interval);
            }

            $product = $subscriptionData['product'] ?? null;
            if (is_array($product)) {
                $prices = $product['prices'] ?? [];
                if (is_array($prices) && $prices !== []) {
                    $price = $prices[0];
                    if (is_array($price)) {
                        $interval = $price['recurring_interval'] ?? null;
                        if (is_string($interval)) {
                            return BillingInterval::tryFrom($interval);
                        }
                    }
                }
            }
        }

        if (is_array($orderData)) {
            $interval = $orderData['billing_interval'] ?? null;
            if (is_string($interval)) {
                return BillingInterval::tryFrom($interval);
            }

            $product = $orderData['product'] ?? null;
            if (is_array($product)) {
                $prices = $product['prices'] ?? [];
                if (is_array($prices) && $prices !== []) {
                    $price = $prices[0];
                    if (is_array($price)) {
                        $interval = $price['recurring_interval'] ?? null;
                        if (is_string($interval)) {
                            return BillingInterval::tryFrom($interval);
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Confirm pending promotion usage once a subscription is created.
     */
    public function confirmPromotionUsage(Workspace $workspace, Subscription $subscription, mixed $promotionId): void
    {
        if (! is_string($promotionId) || $promotionId === '') {
            return;
        }

        if (! ctype_digit($promotionId)) {
            Log::warning('Invalid promotion_id format in webhook metadata', [
                'promotion_id' => $promotionId,
                'workspace_id' => $workspace->id,
            ]);

            return;
        }

        $usage = PromotionUsage::query()
            ->where('workspace_id', $workspace->id)
            ->where('promotion_id', (int) $promotionId)
            ->where('status', PromotionUsageStatus::Pending)
            ->first();

        if ($usage instanceof PromotionUsage) {
            $usage->confirm($subscription);

            Log::info('Promotion usage confirmed', [
                'promotion_id' => $promotionId,
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Resolve a plan model for the given tier value.
     */
    public function resolvePlan(?string $tier): ?Plan
    {
        if (! is_string($tier)) {
            return null;
        }

        $planTier = PlanTier::tryFrom($tier);

        if ($planTier === null) {
            return null;
        }

        return Plan::query()->firstOrCreate(
            ['tier' => $planTier->value],
            PlanDefaults::forTier($planTier)
        );
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
        if (is_int($timestamp)) {
            return CarbonImmutable::createFromTimestampUTC($timestamp);
        }

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp);
        } catch (Throwable) {
            return null;
        }
    }
}
