<?php

declare(strict_types=1);

namespace App\Actions\Billing\Support;

use App\Actions\Billing\PolarWebhookSupport;
use App\Models\Plan;
use App\Models\Workspace;

final readonly class PolarOrderPaidPlanResolver
{
    /**
     * Create a new plan resolver instance.
     */
    public function __construct(private PolarWebhookSupport $polarWebhookSupport) {}

    /**
     * Resolve the plan for an order.paid event.
     */
    public function resolve(Workspace $workspace, PolarOrderPaidPayload $payload, ?string $resolvedPlanTier): ?Plan
    {
        $plan = $this->polarWebhookSupport->resolvePlan($resolvedPlanTier);

        if ($plan instanceof Plan) {
            return $plan;
        }

        $productData = $payload->order['product'] ?? null;
        if (is_array($productData)) {
            $productMetadata = $productData['metadata'] ?? [];
            $productPlanTier = is_array($productMetadata) ? ($productMetadata['plan_tier'] ?? null) : null;
            $plan = $this->polarWebhookSupport->resolvePlan(is_string($productPlanTier) ? $productPlanTier : null);
        }

        if ($plan instanceof Plan) {
            return $plan;
        }

        return $workspace->plan;
    }
}
