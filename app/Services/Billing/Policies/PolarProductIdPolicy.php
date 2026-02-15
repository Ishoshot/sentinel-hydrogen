<?php

declare(strict_types=1);

namespace App\Services\Billing\Policies;

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Resolves a Polar product ID or throws with structured logging.
 */
final readonly class PolarProductIdPolicy
{
    /**
     * Resolve the product ID for the given plan and interval, or throw.
     *
     * @throws InvalidArgumentException if no product ID is configured
     */
    public function resolve(Workspace $workspace, Plan $plan, BillingInterval $interval): string
    {
        $productId = $this->resolveProductId($plan->tier, $interval);

        if ($productId === null) {
            Log::error('Polar product ID not configured', LogContext::merge(
                LogContext::fromWorkspace($workspace),
                ['plan_tier' => $plan->tier, 'interval' => $interval->value]
            ));

            throw new InvalidArgumentException(
                sprintf('Polar product ID is not configured for %s %s plan.', $interval->value, $plan->tier)
            );
        }

        return $productId;
    }

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
    private function resolveProductId(string $tier, BillingInterval $interval): ?string
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
