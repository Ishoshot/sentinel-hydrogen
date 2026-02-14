<?php

declare(strict_types=1);

namespace App\Services\Billing\Support;

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Resolves a Polar product ID or throws with structured logging.
 */
final readonly class PolarProductIdGuard
{
    /**
     * Create a new PolarProductIdGuard instance.
     */
    public function __construct(
        private PolarProductResolver $productResolver,
    ) {}

    /**
     * Resolve the product ID for the given plan and interval, or throw.
     *
     * @throws InvalidArgumentException if no product ID is configured
     */
    public function resolve(Workspace $workspace, Plan $plan, BillingInterval $interval): string
    {
        $productId = $this->productResolver->resolveProductId($plan->tier, $interval);

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
}
