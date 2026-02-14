<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class HandlePolarSubscriptionEvent
{
    /**
     * Create a new action instance.
     */
    public function __construct(private PolarWebhookSupport $polarWebhookSupport) {}

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
        $subscription = $webhook->data;

        if ($subscription === []) {
            Log::warning('subscription.canceled webhook missing data payload');

            return;
        }

        $subscriptionId = $subscription['id'] ?? null;

        if (! is_string($subscriptionId)) {
            Log::warning('subscription.canceled webhook missing subscription id');

            return;
        }

        $existingSubscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($existingSubscription === null) {
            Log::warning('subscription.canceled subscription not found in database', [
                'polar_subscription_id' => $subscriptionId,
            ]);

            return;
        }

        $endsAt = $this->polarWebhookSupport->timestampToDateTime(
            $subscription['current_period_end'] ?? $subscription['ends_at'] ?? null
        );

        $existingSubscription->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'ends_at' => $endsAt,
        ])->save();

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
        $subscription = $webhook->data;

        if ($subscription === []) {
            Log::warning('subscription.uncanceled webhook missing data payload');

            return;
        }

        $subscriptionId = $subscription['id'] ?? null;

        if (! is_string($subscriptionId)) {
            Log::warning('subscription.uncanceled webhook missing subscription id');

            return;
        }

        $existingSubscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($existingSubscription === null) {
            Log::warning('subscription.uncanceled subscription not found in database', [
                'polar_subscription_id' => $subscriptionId,
            ]);

            return;
        }

        DB::transaction(function () use ($existingSubscription): void {
            $existingSubscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'ends_at' => null,
            ])->save();

            $workspace = $existingSubscription->workspace;

            if ($workspace !== null) {
                $workspace->forceFill([
                    'subscription_status' => SubscriptionStatus::Active,
                ])->save();
            }
        });

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
        $subscription = $webhook->data;

        if ($subscription === []) {
            Log::warning('subscription.revoked webhook missing data payload');

            return;
        }

        $subscriptionId = $subscription['id'] ?? null;

        if (! is_string($subscriptionId)) {
            Log::warning('subscription.revoked webhook missing subscription id');

            return;
        }

        $existingSubscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($existingSubscription === null) {
            Log::warning('subscription.revoked subscription not found in database', [
                'polar_subscription_id' => $subscriptionId,
            ]);

            return;
        }

        DB::transaction(function () use ($existingSubscription): void {
            $existingSubscription->forceFill([
                'status' => SubscriptionStatus::Revoked,
                'ends_at' => now(),
            ])->save();

            $workspace = $existingSubscription->workspace;

            if ($workspace !== null) {
                $foundationPlan = $this->polarWebhookSupport->resolvePlan(PlanTier::Foundation->value);

                $workspace->forceFill([
                    'plan_id' => $foundationPlan?->id ?? $workspace->plan_id,
                    'subscription_status' => SubscriptionStatus::Revoked,
                ])->save();
            }
        });

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
        $subscription = $webhook->data;

        if ($subscription === []) {
            Log::warning('Subscription webhook missing data payload', [
                'event_type' => $webhook->type->value,
            ]);

            return;
        }

        $subscriptionId = $webhook->getSubscriptionId();

        if ($subscriptionId === null) {
            Log::warning('Subscription webhook missing subscription id', [
                'event_type' => $webhook->type->value,
            ]);

            return;
        }

        $existingSubscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($existingSubscription === null) {
            Log::warning('Subscription not found in database', [
                'event_type' => $webhook->type->value,
                'polar_subscription_id' => $subscriptionId,
            ]);

            return;
        }

        $polarStatus = $subscription['status'] ?? null;
        $status = $overrideStatus ?? $this->polarWebhookSupport->mapPolarStatus($polarStatus);

        $plan = null;
        $productData = $subscription['product'] ?? null;
        if (is_array($productData)) {
            $productMetadata = $productData['metadata'] ?? [];
            $planTier = $productMetadata['plan_tier'] ?? null;
            if (is_string($planTier)) {
                $plan = $this->polarWebhookSupport->resolvePlan($planTier);
            }
        }

        /** @var array<string, mixed> $subscriptionTyped */
        $subscriptionTyped = $subscription;
        $billingInterval = $this->polarWebhookSupport->extractBillingInterval($subscriptionTyped, null);

        $periodStart = $this->polarWebhookSupport->timestampToDateTime($subscription['current_period_start'] ?? null);
        $periodEnd = $this->polarWebhookSupport->timestampToDateTime($subscription['current_period_end'] ?? null);

        $updateAttributes = ['status' => $status];

        if ($plan instanceof Plan) {
            $updateAttributes['plan_id'] = $plan->id;
        }

        if ($billingInterval instanceof \App\Enums\Billing\BillingInterval) {
            $updateAttributes['billing_interval'] = $billingInterval;
        }

        if ($periodStart instanceof CarbonImmutable) {
            $updateAttributes['current_period_start'] = $periodStart;
        }

        if ($periodEnd instanceof CarbonImmutable) {
            $updateAttributes['current_period_end'] = $periodEnd;
        }

        DB::transaction(function () use ($existingSubscription, $updateAttributes, $status, $plan): void {
            $existingSubscription->forceFill($updateAttributes)->save();

            $workspace = $existingSubscription->workspace;

            if ($workspace !== null) {
                $workspaceUpdate = ['subscription_status' => $status];

                if ($plan instanceof Plan) {
                    $workspaceUpdate['plan_id'] = $plan->id;
                }

                $workspace->forceFill($workspaceUpdate)->save();
            }
        });

        Log::info('Subscription updated', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
            'status' => $status->value,
            'plan_tier' => $plan?->tier,
            'billing_interval' => $billingInterval?->value,
            'current_period_start' => $periodStart?->toIso8601String(),
            'current_period_end' => $periodEnd?->toIso8601String(),
        ]);
    }
}
