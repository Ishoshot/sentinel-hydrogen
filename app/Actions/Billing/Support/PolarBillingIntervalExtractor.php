<?php

declare(strict_types=1);

namespace App\Actions\Billing\Support;

use App\Enums\Billing\BillingInterval;

final class PolarBillingIntervalExtractor
{
    /**
     * @param  array<string, mixed>|null  $subscriptionData
     * @param  array<string, mixed>|null  $orderData
     */
    public function extract(?array $subscriptionData, ?array $orderData): ?BillingInterval
    {
        return $this->fromPayload($subscriptionData) ?? $this->fromPayload($orderData);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function fromPayload(?array $payload): ?BillingInterval
    {
        if (! is_array($payload)) {
            return null;
        }

        $interval = $payload['recurring_interval'] ?? $payload['billing_interval'] ?? null;

        if (is_string($interval)) {
            return BillingInterval::tryFrom($interval);
        }

        $product = $payload['product'] ?? null;

        if (! is_array($product)) {
            return null;
        }

        $prices = $product['prices'] ?? [];

        if (! is_array($prices) || $prices === []) {
            return null;
        }

        $price = $prices[0];

        if (! is_array($price)) {
            return null;
        }

        $priceInterval = $price['recurring_interval'] ?? null;

        if (! is_string($priceInterval)) {
            return null;
        }

        return BillingInterval::tryFrom($priceInterval);
    }
}
