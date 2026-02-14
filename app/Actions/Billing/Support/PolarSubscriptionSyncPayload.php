<?php

declare(strict_types=1);

namespace App\Actions\Billing\Support;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use Carbon\CarbonImmutable;

/**
 * Normalized subscription sync attributes extracted from Polar payloads.
 */
final readonly class PolarSubscriptionSyncPayload
{
    /**
     * Create a new sync payload value object.
     *
     * @param  array<string, mixed>  $updateAttributes
     */
    public function __construct(
        public SubscriptionStatus $status,
        public ?Plan $plan,
        public ?BillingInterval $billingInterval,
        public ?CarbonImmutable $periodStart,
        public ?CarbonImmutable $periodEnd,
        public array $updateAttributes,
    ) {}
}
