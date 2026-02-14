<?php

declare(strict_types=1);

namespace App\Actions\Billing\Support;

use App\Models\Subscription;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Illuminate\Support\Facades\Log;

/**
 * Resolves and validates subscription records for Polar subscription webhooks.
 */
final class PolarSubscriptionLookup
{
    /**
     * Resolve an existing subscription for subscription lifecycle events.
     *
     * @param  array<string, mixed>  $subscriptionPayload
     */
    public function fromLifecyclePayload(array $subscriptionPayload, string $event): ?Subscription
    {
        if ($subscriptionPayload === []) {
            Log::warning("subscription.{$event} webhook missing data payload");

            return null;
        }

        $subscriptionId = $subscriptionPayload['id'] ?? null;

        if (! is_string($subscriptionId)) {
            Log::warning("subscription.{$event} webhook missing subscription id");

            return null;
        }

        return $this->findExistingForLifecycleEvent($subscriptionId, $event);
    }

    /**
     * Resolve an existing subscription for generic subscription webhooks.
     */
    public function fromWebhook(VerifiedPolarWebhook $webhook): ?Subscription
    {
        if ($webhook->data === []) {
            Log::warning('Subscription webhook missing data payload', [
                'event_type' => $webhook->type->value,
            ]);

            return null;
        }

        $subscriptionId = $webhook->getSubscriptionId();

        if ($subscriptionId === null) {
            Log::warning('Subscription webhook missing subscription id', [
                'event_type' => $webhook->type->value,
            ]);

            return null;
        }

        $existingSubscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($existingSubscription !== null) {
            return $existingSubscription;
        }

        Log::warning('Subscription not found in database', [
            'event_type' => $webhook->type->value,
            'polar_subscription_id' => $subscriptionId,
        ]);

        return null;
    }

    /**
     * Resolve an existing subscription for lifecycle event handlers.
     */
    private function findExistingForLifecycleEvent(string $subscriptionId, string $event): ?Subscription
    {
        $existingSubscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($existingSubscription !== null) {
            return $existingSubscription;
        }

        Log::warning("subscription.{$event} subscription not found in database", [
            'polar_subscription_id' => $subscriptionId,
        ]);

        return null;
    }
}
