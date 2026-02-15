<?php

declare(strict_types=1);

namespace App\Actions\Billing\ValueObjects;

use App\Models\Subscription;

final readonly class PolarSubscriptionSyncContext
{
    /**
     * Create a new sync context.
     */
    public function __construct(
        public Subscription $subscription,
        public PolarSubscriptionSyncPayload $syncPayload,
    ) {}
}
