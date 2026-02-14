<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Billing\Support\PolarSubscriptionLookup;
use App\Actions\Billing\Support\PolarSubscriptionStateUpdater;
use App\Actions\Billing\Support\PolarSubscriptionSyncPayloadResolver;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Illuminate\Support\Facades\Log;

final readonly class HandlePolarSubscriptionEvent
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PolarWebhookSupport $polarWebhookSupport,
        private PolarSubscriptionLookup $subscriptionLookup,
        private PolarSubscriptionStateUpdater $subscriptionStateUpdater,
        private PolarSubscriptionSyncPayloadResolver $syncPayloadResolver,
    ) {}

    /**
     * Handle subscription activation events.
     */
    public function active(VerifiedPolarWebhook $webhook): void
    {
        $this->updateSubscriptionFromWebhook($webhook, SubscriptionStatus::Active);
    }

    /**
     * Handle subscription cancellation events while preserving access until period end.
     */
    public function canceled(VerifiedPolarWebhook $webhook): void
    {
        $existingSubscription = $this->subscriptionLookup->fromLifecyclePayload($webhook->data, 'canceled');
        if (! $existingSubscription instanceof \App\Models\Subscription) {
            return;
        }

        $endsAt = $this->polarWebhookSupport->timestampToDateTime(
            $webhook->data['current_period_end'] ?? $webhook->data['ends_at'] ?? null
        );

        $this->subscriptionStateUpdater->markCanceled($existingSubscription, $endsAt);

        Log::info('Subscription canceled (access retained until period end)', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
            'ends_at' => $endsAt?->toIso8601String(),
        ]);
    }

    /**
     * Handle subscription uncancel events.
     */
    public function uncanceled(VerifiedPolarWebhook $webhook): void
    {
        $existingSubscription = $this->subscriptionLookup->fromLifecyclePayload($webhook->data, 'uncanceled');
        if (! $existingSubscription instanceof \App\Models\Subscription) {
            return;
        }

        $this->subscriptionStateUpdater->markUncanceled($existingSubscription);

        Log::info('Subscription uncanceled', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
        ]);
    }

    /**
     * Handle subscription revocation events.
     */
    public function revoked(VerifiedPolarWebhook $webhook): void
    {
        $existingSubscription = $this->subscriptionLookup->fromLifecyclePayload($webhook->data, 'revoked');
        if (! $existingSubscription instanceof \App\Models\Subscription) {
            return;
        }

        $foundationPlan = $this->polarWebhookSupport->resolvePlan(PlanTier::Foundation->value);
        $this->subscriptionStateUpdater->markRevoked($existingSubscription, $foundationPlan);

        Log::info('Subscription revoked - access removed', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
        ]);
    }

    /**
     * Handle subscription update events.
     */
    public function updated(VerifiedPolarWebhook $webhook): void
    {
        $this->updateSubscriptionFromWebhook($webhook, null);
    }

    /**
     * Handle subscription creation events.
     */
    public function created(VerifiedPolarWebhook $webhook): void
    {
        Log::debug('Subscription created (waiting for order.paid)', [
            'subscription_id' => $webhook->getSubscriptionId(),
        ]);
    }

    /**
     * Update local subscription and workspace state from a Polar subscription payload.
     */
    private function updateSubscriptionFromWebhook(VerifiedPolarWebhook $webhook, ?SubscriptionStatus $overrideStatus): void
    {
        $existingSubscription = $this->subscriptionLookup->fromWebhook($webhook);
        if (! $existingSubscription instanceof \App\Models\Subscription) {
            return;
        }

        $syncPayload = $this->syncPayloadResolver->resolve($webhook->data, $overrideStatus);
        $this->subscriptionStateUpdater->syncSubscription($existingSubscription, $syncPayload);

        Log::info('Subscription updated', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
            'status' => $syncPayload->status->value,
            'plan_tier' => $syncPayload->plan?->tier,
            'billing_interval' => $syncPayload->billingInterval?->value,
            'current_period_start' => $syncPayload->periodStart?->toIso8601String(),
            'current_period_end' => $syncPayload->periodEnd?->toIso8601String(),
        ]);
    }
}
