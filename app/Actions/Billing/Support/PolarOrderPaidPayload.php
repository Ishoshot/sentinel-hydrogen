<?php

declare(strict_types=1);

namespace App\Actions\Billing\Support;

use App\Enums\Billing\BillingInterval;

final readonly class PolarOrderPaidPayload
{
    /**
     * Create a new order paid payload value object.
     *
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>|null  $subscriptionData
     */
    public function __construct(
        public array $order,
        public ?array $subscriptionData,
        public ?string $subscriptionId,
        public ?string $customerId,
        public mixed $workspaceId,
        public mixed $planTier,
        public mixed $promotionId,
        public ?BillingInterval $billingInterval,
    ) {}
}
