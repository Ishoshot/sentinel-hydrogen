<?php

declare(strict_types=1);

namespace App\Actions\Billing\Orchestrators;

use App\Actions\Billing\PolarWebhookSupport;
use App\Actions\Billing\Support\PolarSubscriptionLookup;
use App\Actions\Billing\Support\PolarSubscriptionStateUpdater;
use App\Enums\Billing\PlanTier;
use App\Models\Subscription;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Carbon\CarbonImmutable;

final readonly class PolarSubscriptionLifecycleOrchestrator
{
    /**
     * Create a new orchestrator instance.
     */
    public function __construct(
        private PolarWebhookSupport $polarWebhookSupport,
        private PolarSubscriptionLookup $subscriptionLookup,
        private PolarSubscriptionStateUpdater $subscriptionStateUpdater,
    ) {}

    /**
     * Apply cancellation state for a subscription lifecycle webhook.
     *
     * @return array{subscription: Subscription, ends_at: ?CarbonImmutable}|null
     */
    public function canceled(VerifiedPolarWebhook $webhook): ?array
    {
        $existingSubscription = $this->subscriptionLookup->fromLifecyclePayload($webhook->data, 'canceled');

        if (! $existingSubscription instanceof Subscription) {
            return null;
        }

        $endsAt = $this->polarWebhookSupport->timestampToDateTime(
            $webhook->data['current_period_end'] ?? $webhook->data['ends_at'] ?? null
        );

        $this->subscriptionStateUpdater->markCanceled($existingSubscription, $endsAt);

        return [
            'subscription' => $existingSubscription,
            'ends_at' => $endsAt,
        ];
    }

    /**
     * Apply uncancel state for a subscription lifecycle webhook.
     */
    public function uncanceled(VerifiedPolarWebhook $webhook): ?Subscription
    {
        $existingSubscription = $this->subscriptionLookup->fromLifecyclePayload($webhook->data, 'uncanceled');

        if (! $existingSubscription instanceof Subscription) {
            return null;
        }

        $this->subscriptionStateUpdater->markUncanceled($existingSubscription);

        return $existingSubscription;
    }

    /**
     * Apply revoked state for a subscription lifecycle webhook.
     */
    public function revoked(VerifiedPolarWebhook $webhook): ?Subscription
    {
        $existingSubscription = $this->subscriptionLookup->fromLifecyclePayload($webhook->data, 'revoked');

        if (! $existingSubscription instanceof Subscription) {
            return null;
        }

        $foundationPlan = $this->polarWebhookSupport->resolvePlan(PlanTier::Foundation->value);
        $this->subscriptionStateUpdater->markRevoked($existingSubscription, $foundationPlan);

        return $existingSubscription;
    }
}
