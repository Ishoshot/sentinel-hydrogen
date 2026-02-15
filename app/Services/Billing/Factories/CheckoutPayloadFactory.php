<?php

declare(strict_types=1);

namespace App\Services\Billing\Factories;

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Workspace;

/**
 * Builds the payload for a Polar checkout session API call.
 */
final readonly class CheckoutPayloadFactory
{
    /**
     * Build the checkout session payload.
     *
     * @return array<string, mixed>
     */
    public function build(
        string $productId,
        Workspace $workspace,
        Plan $plan,
        BillingInterval $interval,
        ?Promotion $promotion,
        ?string $successUrl,
        ?string $customerEmail,
    ): array {
        $metadata = [
            'workspace_id' => (string) $workspace->id,
            'plan_tier' => $plan->tier,
            'billing_interval' => $interval->value,
        ];

        if ($promotion?->id !== null) {
            $metadata['promotion_id'] = (string) $promotion->id;
        }

        $payload = [
            'products' => [$productId],
            'metadata' => $metadata,
            'allow_discount_codes' => true,
        ];

        if ($successUrl !== null && $successUrl !== '') {
            $payload['success_url'] = $successUrl;
        }

        if ($promotion instanceof Promotion && $promotion->isValid() && $promotion->polar_discount_id !== null) {
            $payload['discount_id'] = $promotion->polar_discount_id;
        }

        if ($customerEmail !== null && $customerEmail !== '') {
            $payload['customer_email'] = $customerEmail;
        }

        return $payload;
    }
}
