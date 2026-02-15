<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Billing\Handlers\PolarSubscriptionLifecycleHandler;
use App\Actions\Billing\Handlers\PolarSubscriptionSyncHandler;
use App\Actions\Billing\ValueObjects\PolarSubscriptionSyncContext;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Illuminate\Support\Facades\Log;

final readonly class HandlePolarSubscriptionEvent
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PolarSubscriptionLifecycleHandler $lifecycleOrchestrator,
        private PolarSubscriptionSyncHandler $syncOrchestrator,
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
        $lifecycle = $this->lifecycleOrchestrator->canceled($webhook);

        if (! is_array($lifecycle)) {
            return;
        }

        $subscription = $lifecycle['subscription'];
        $endsAt = $lifecycle['ends_at'];

        Log::info('Subscription canceled (access retained until period end)', [
            'subscription_id' => $subscription->id,
            'workspace_id' => $subscription->workspace_id,
            'ends_at' => $endsAt?->toIso8601String(),
        ]);
    }

    /**
     * Handle subscription uncancel events.
     */
    public function uncanceled(VerifiedPolarWebhook $webhook): void
    {
        $subscription = $this->lifecycleOrchestrator->uncanceled($webhook);

        if (! $subscription instanceof Subscription) {
            return;
        }

        Log::info('Subscription uncanceled', [
            'subscription_id' => $subscription->id,
            'workspace_id' => $subscription->workspace_id,
        ]);
    }

    /**
     * Handle subscription revocation events.
     */
    public function revoked(VerifiedPolarWebhook $webhook): void
    {
        $subscription = $this->lifecycleOrchestrator->revoked($webhook);

        if (! $subscription instanceof Subscription) {
            return;
        }

        Log::info('Subscription revoked - access removed', [
            'subscription_id' => $subscription->id,
            'workspace_id' => $subscription->workspace_id,
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
        $syncContext = $this->syncOrchestrator->sync($webhook, $overrideStatus);

        if (! $syncContext instanceof PolarSubscriptionSyncContext) {
            return;
        }

        $subscription = $syncContext->subscription;
        $syncPayload = $syncContext->syncPayload;

        Log::info('Subscription updated', [
            'subscription_id' => $subscription->id,
            'workspace_id' => $subscription->workspace_id,
            'status' => $syncPayload->status->value,
            'plan_tier' => $syncPayload->plan?->tier,
            'billing_interval' => $syncPayload->billingInterval?->value,
            'current_period_start' => $syncPayload->periodStart?->toIso8601String(),
            'current_period_end' => $syncPayload->periodEnd?->toIso8601String(),
        ]);
    }
}
