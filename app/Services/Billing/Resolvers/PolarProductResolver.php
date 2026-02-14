<?php

declare(strict_types=1);

namespace App\Services\Billing\Resolvers;

use App\Enums\Billing\BillingInterval;

final class PolarProductResolver
{
    /**
     * Check whether monthly or yearly product IDs are configured.
     */
    public function hasConfiguredProducts(): bool
    {
        $productIds = config('services.polar.product_ids', []);

        return is_array($productIds)
            && (
                $this->hasValidIds($productIds['monthly'] ?? [])
                || $this->hasValidIds($productIds['yearly'] ?? [])
            );
    }

    /**
     * Resolve the Polar product ID for the requested tier and interval.
     */
    public function resolveProductId(string $tier, BillingInterval $interval): ?string
    {
        $productIds = config('services.polar.product_ids', []);

        if (! is_array($productIds)) {
            return null;
        }

        $intervalIds = $productIds[$interval->value] ?? [];

        if (! is_array($intervalIds)) {
            return null;
        }

        $productId = $intervalIds[$tier] ?? null;

        return is_string($productId) && $productId !== '' ? $productId : null;
    }

    /**
     * Check if an array has at least one valid non-empty string ID.
     */
    private function hasValidIds(mixed $ids): bool
    {
        if (! is_array($ids)) {
            return false;
        }

        return array_filter($ids, fn (mixed $id): bool => is_string($id) && $id !== '') !== [];
    }
}
