<?php

declare(strict_types=1);

namespace App\Actions\Billing\Handlers;

use App\Actions\Billing\Resolvers\PolarSubscriptionLookupResolver;
use App\Actions\Billing\Resolvers\PolarSubscriptionSyncPayloadResolver;
use App\Actions\Billing\ValueObjects\PolarSubscriptionSyncContext;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;

final readonly class PolarSubscriptionSyncHandler
{
    /**
     * Create a new orchestrator instance.
     */
    public function __construct(
        private PolarSubscriptionLookupResolver $subscriptionLookup,
        private PolarSubscriptionStateHandler $subscriptionStateUpdater,
        private PolarSubscriptionSyncPayloadResolver $syncPayloadResolver,
    ) {}

    /**
     * Sync local subscription and workspace state from a Polar webhook payload.
     */
    public function sync(VerifiedPolarWebhook $webhook, ?SubscriptionStatus $overrideStatus): ?PolarSubscriptionSyncContext
    {
        $existingSubscription = $this->subscriptionLookup->fromWebhook($webhook);

        if (! $existingSubscription instanceof Subscription) {
            return null;
        }

        $syncPayload = $this->syncPayloadResolver->resolve($webhook->data, $overrideStatus);
        $this->subscriptionStateUpdater->syncSubscription($existingSubscription, $syncPayload);

        return new PolarSubscriptionSyncContext(
            subscription: $existingSubscription,
            syncPayload: $syncPayload,
        );
    }
}
