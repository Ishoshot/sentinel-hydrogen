<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Enums\Billing\BillingInterval;
use App\Models\Promotion;
use App\Models\Subscription;

/**
 * Builds standardized response arrays for subscription change outcomes.
 */
final class ChangeResponseFactory
{
    /**
     * Build a checkout redirect response.
     *
     * @return array{action: string, checkout_url: string, promotion: array{code: string, discount: string}|null, billing_interval: string}
     */
    public static function checkout(string $checkoutUrl, ?Promotion $promotion, BillingInterval $interval): array
    {
        return [
            'action' => 'checkout',
            'checkout_url' => $checkoutUrl,
            'promotion' => self::promotionArray($promotion),
            'billing_interval' => $interval->value,
        ];
    }

    /**
     * Build a subscription-applied response.
     *
     * @return array{action: string, subscription: Subscription, billing_interval: string}
     */
    public static function subscription(string $action, Subscription $subscription, BillingInterval $interval): array
    {
        return [
            'action' => $action,
            'subscription' => $subscription,
            'billing_interval' => $interval->value,
        ];
    }

    /**
     * Build a cancellation response.
     *
     * @return array{action: string, subscription: Subscription, billing_interval: string}
     */
    public static function cancel(Subscription $subscription): array
    {
        return [
            'action' => 'cancel',
            'subscription' => $subscription,
            'billing_interval' => BillingInterval::Monthly->value,
        ];
    }

    /**
     * Format a promotion for inclusion in a response.
     *
     * @return array{code: string, discount: string}|null
     */
    private static function promotionArray(?Promotion $promotion): ?array
    {
        if (! $promotion instanceof Promotion) {
            return null;
        }

        return ['code' => $promotion->code, 'discount' => $promotion->discountDisplay()];
    }
}
