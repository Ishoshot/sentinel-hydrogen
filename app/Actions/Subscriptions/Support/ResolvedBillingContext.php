<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Models\Subscription;
use App\Models\Workspace;

/**
 * Captures a workspace's current billing state for routing subscription changes.
 */
final readonly class ResolvedBillingContext
{
    /**
     * Create a new ResolvedBillingContext instance.
     */
    public function __construct(
        public ?Subscription $latestSubscription,
        public ?string $polarSubscriptionId,
    ) {}

    /**
     * Resolve the billing context for a workspace.
     */
    public static function forWorkspace(Workspace $workspace): self
    {
        $subscription = $workspace->subscriptions()->latest()->first();
        $polarId = $subscription?->polar_subscription_id;

        return new self(
            latestSubscription: $subscription,
            polarSubscriptionId: is_string($polarId) && $polarId !== '' ? $polarId : null,
        );
    }
}
