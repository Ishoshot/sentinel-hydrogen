<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\BillingInterval;
use App\Enums\PlanTier;
use App\Enums\PolarWebhookEvent;
use App\Enums\PromotionUsageStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PromotionUsage;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\PolarBillingService;
use App\Support\PlanDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class HandlePolarWebhook
{
    /**
     * Create a new action instance.
     */
    public function __construct(private PolarBillingService $billingService) {}

    /**
     * Handle incoming Polar webhook events.
     *
     * @param  array{webhook-id: string, webhook-signature: string, webhook-timestamp: string}  $headers
     */
    public function handle(string $payload, array $headers): void
    {
        $event = $this->billingService->verifyWebhook($payload, $headers);

        $type = $event['type'] ?? null;

        if (! is_string($type)) {
            Log::warning('Polar webhook missing event type');

            return;
        }

        $webhookEvent = PolarWebhookEvent::tryFrom($type);

        if ($webhookEvent === null) {
            Log::debug('Unhandled Polar webhook event type', ['type' => $type]);

            return;
        }

        match ($webhookEvent) {
            // Primary event for granting/maintaining access
            PolarWebhookEvent::OrderPaid => $this->handleOrderPaid($event),

            // Revoke access on refund
            PolarWebhookEvent::OrderRefunded => $this->handleOrderRefunded($event),

            // Subscription lifecycle events for status changes
            PolarWebhookEvent::SubscriptionActive => $this->handleSubscriptionActive($event),
            PolarWebhookEvent::SubscriptionCanceled => $this->handleSubscriptionCanceled($event),
            PolarWebhookEvent::SubscriptionUncanceled => $this->handleSubscriptionUncanceled($event),
            PolarWebhookEvent::SubscriptionRevoked => $this->handleSubscriptionRevoked($event),
            PolarWebhookEvent::SubscriptionUpdated => $this->handleSubscriptionUpdated($event),

            // Informational - subscription created but not yet active
            PolarWebhookEvent::SubscriptionCreated => $this->handleSubscriptionCreated($event),

            default => null, // Ignore other events
        };
    }

    /**
     * Handle order.paid - the recommended event for provisioning access.
     *
     * This fires for both new subscriptions and renewals.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleOrderPaid(array $event): void
    {
        $order = $event['data'] ?? null;

        if (! is_array($order)) {
            Log::warning('order.paid webhook missing data payload');

            return;
        }

        // Extract subscription info if this is a subscription order
        $subscriptionData = $order['subscription'] ?? null;
        $subscriptionId = is_array($subscriptionData)
            ? ($subscriptionData['id'] ?? null)
            : ($order['subscription_id'] ?? null);

        // Extract customer info
        $customerData = $order['customer'] ?? null;
        $customerId = is_array($customerData)
            ? ($customerData['id'] ?? null)
            : ($order['customer_id'] ?? null);

        // Get metadata - can be on order or subscription
        $metadata = $order['metadata'] ?? [];
        if (empty($metadata) && is_array($subscriptionData)) {
            $metadata = $subscriptionData['metadata'] ?? [];
        }

        $workspaceId = $metadata['workspace_id'] ?? null;
        $planTier = $metadata['plan_tier'] ?? null;
        $promotionId = $metadata['promotion_id'] ?? null;

        // Extract billing interval from subscription or order
        /** @var array<string, mixed>|null $subscriptionDataTyped */
        $subscriptionDataTyped = is_array($subscriptionData) ? $subscriptionData : null;
        /** @var array<string, mixed> $orderTyped */
        $orderTyped = $order;
        $billingInterval = $this->extractBillingInterval($subscriptionDataTyped, $orderTyped);

        // Try to find workspace from existing subscription if not in metadata
        if (! is_string($workspaceId) && is_string($subscriptionId)) {
            $existingSubscription = Subscription::query()
                ->where('polar_subscription_id', $subscriptionId)
                ->first();
            if ($existingSubscription !== null) {
                $workspaceId = (string) $existingSubscription->workspace_id;
                // Also get plan tier from existing subscription if not in metadata
                if (! is_string($planTier) && $existingSubscription->plan !== null) {
                    $planTier = $existingSubscription->plan->tier;
                }
            }
        }

        if (! is_string($workspaceId)) {
            Log::warning('Order paid but missing workspace_id', [
                'order_id' => $order['id'] ?? null,
                'subscription_id' => $subscriptionId,
            ]);

            return;
        }

        $workspace = Workspace::query()->find((int) $workspaceId);

        if ($workspace === null) {
            Log::warning('Workspace not found for paid order', ['workspace_id' => $workspaceId]);

            return;
        }

        // Resolve plan - try metadata, then product metadata
        $plan = $this->resolvePlan($planTier);

        if (! $plan instanceof Plan) {
            // Try to get from product
            $productData = $order['product'] ?? null;
            if (is_array($productData)) {
                $productMetadata = $productData['metadata'] ?? [];
                $planTier = $productMetadata['plan_tier'] ?? null;
                $plan = $this->resolvePlan($planTier);
            }
        }

        if (! $plan instanceof Plan) {
            // Fall back to workspace's current plan for renewals
            $plan = $workspace->plan;
        }

        if (! $plan instanceof Plan) {
            Log::warning('Could not resolve plan for paid order', [
                'workspace_id' => $workspace->id,
                'order_id' => $order['id'] ?? null,
            ]);

            return;
        }

        // Create or update subscription if this is a subscription order
        if (is_string($subscriptionId) && is_string($customerId)) {
            $subscriptionAttributes = [
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'started_at' => $this->timestampToDateTime($order['created_at'] ?? null) ?? now(),
                'polar_customer_id' => $customerId,
            ];

            if ($billingInterval !== null) {
                $subscriptionAttributes['billing_interval'] = $billingInterval;
            }

            $subscription = Subscription::updateOrCreate(
                ['polar_subscription_id' => $subscriptionId],
                $subscriptionAttributes
            );

            // Confirm promotion usage for new subscriptions
            $this->confirmPromotionUsage($workspace, $subscription, $promotionId);
        }

        // Update workspace to active status
        $workspace->forceFill([
            'plan_id' => $plan->id,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();

        Log::info('Order paid - access granted', [
            'workspace_id' => $workspace->id,
            'plan_tier' => $plan->tier,
            'billing_interval' => $billingInterval?->value,
            'order_id' => $order['id'] ?? null,
        ]);
    }

    /**
     * Handle order.refunded - revoke access when a refund is issued.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleOrderRefunded(array $event): void
    {
        $order = $event['data'] ?? null;

        if (! is_array($order)) {
            Log::warning('order.refunded webhook missing data payload');

            return;
        }

        // Find subscription associated with this order
        $subscriptionData = $order['subscription'] ?? null;
        $subscriptionId = is_array($subscriptionData)
            ? ($subscriptionData['id'] ?? null)
            : ($order['subscription_id'] ?? null);

        if (! is_string($subscriptionId)) {
            // One-time purchase refund, not subscription-related
            Log::info('Order refunded (non-subscription)', [
                'order_id' => $order['id'] ?? null,
            ]);

            return;
        }

        $existingSubscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($existingSubscription === null) {
            Log::warning('order.refunded subscription not found in database', [
                'polar_subscription_id' => $subscriptionId,
                'order_id' => $order['id'] ?? null,
            ]);

            return;
        }

        // Mark subscription as revoked
        $existingSubscription->forceFill([
            'status' => SubscriptionStatus::Revoked,
            'ends_at' => now(),
        ])->save();

        // Downgrade workspace to Foundation
        $workspace = $existingSubscription->workspace;

        if ($workspace !== null) {
            $foundationPlan = $this->resolvePlan(PlanTier::Foundation->value);

            $workspace->forceFill([
                'plan_id' => $foundationPlan?->id ?? $workspace->plan_id,
                'subscription_status' => SubscriptionStatus::Revoked,
            ])->save();
        }

        Log::info('Order refunded - access revoked', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
            'order_id' => $order['id'] ?? null,
        ]);
    }

    /**
     * Handle subscription.active - subscription is now active.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionActive(array $event): void
    {
        $this->updateSubscriptionFromEvent($event, SubscriptionStatus::Active);
    }

    /**
     * Handle subscription.canceled - customer canceled their subscription.
     *
     * The subscription remains active until the end of the billing period.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionCanceled(array $event): void
    {
        $subscription = $event['data'] ?? null;

        if (! is_array($subscription)) {
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

        // Update subscription status to canceled
        $endsAt = $this->timestampToDateTime(
            $subscription['current_period_end'] ?? $subscription['ends_at'] ?? null
        );

        $existingSubscription->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'ends_at' => $endsAt,
        ])->save();

        // Update workspace - downgrade to Foundation when subscription ends
        // For now, we mark as canceled but keep current plan until ends_at
        $workspace = $existingSubscription->workspace;

        if ($workspace !== null) {
            $workspace->forceFill([
                'subscription_status' => SubscriptionStatus::Canceled,
            ])->save();
        }

        Log::info('Subscription canceled', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
            'ends_at' => $endsAt?->toIso8601String(),
        ]);
    }

    /**
     * Handle subscription.uncanceled - customer reactivated before end date.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionUncanceled(array $event): void
    {
        $subscription = $event['data'] ?? null;

        if (! is_array($subscription)) {
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

        // Reactivate subscription
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

        Log::info('Subscription uncanceled', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
        ]);
    }

    /**
     * Handle subscription.revoked - subscription terminated immediately.
     *
     * This happens for non-payment or policy violations.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionRevoked(array $event): void
    {
        $subscription = $event['data'] ?? null;

        if (! is_array($subscription)) {
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

        // Mark subscription as revoked
        $existingSubscription->forceFill([
            'status' => SubscriptionStatus::Revoked,
            'ends_at' => now(),
        ])->save();

        // Immediately downgrade workspace to Foundation
        $workspace = $existingSubscription->workspace;

        if ($workspace !== null) {
            $foundationPlan = $this->resolvePlan(PlanTier::Foundation->value);

            $workspace->forceFill([
                'plan_id' => $foundationPlan?->id ?? $workspace->plan_id,
                'subscription_status' => SubscriptionStatus::Revoked,
            ])->save();
        }

        Log::info('Subscription revoked - access removed', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
        ]);
    }

    /**
     * Handle subscription.updated - subscription details changed.
     *
     * This syncs plan, billing interval, and status changes.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionUpdated(array $event): void
    {
        $this->updateSubscriptionFromEvent($event, null);
    }

    /**
     * Handle subscription.created - subscription created but may not be active yet.
     *
     * We don't grant access here - wait for order.paid.
     *
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionCreated(array $event): void
    {
        // Log for debugging but don't take action
        // Access is granted on order.paid
        $subscription = $event['data'] ?? null;

        if (! is_array($subscription)) {
            Log::warning('subscription.created webhook missing data payload');

            return;
        }

        Log::debug('Subscription created (waiting for order.paid)', [
            'subscription_id' => $subscription['id'] ?? null,
        ]);
    }

    /**
     * Update subscription from webhook event data.
     *
     * Syncs plan, billing interval, and status.
     *
     * @param  array<string, mixed>  $event
     */
    private function updateSubscriptionFromEvent(array $event, ?SubscriptionStatus $overrideStatus): void
    {
        $subscription = $event['data'] ?? null;
        $eventType = $event['type'] ?? 'unknown';

        if (! is_array($subscription)) {
            Log::warning('Subscription webhook missing data payload', [
                'event_type' => $eventType,
            ]);

            return;
        }

        $subscriptionId = $subscription['id'] ?? null;

        if (! is_string($subscriptionId)) {
            Log::warning('Subscription webhook missing subscription id', [
                'event_type' => $eventType,
            ]);

            return;
        }

        $existingSubscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($existingSubscription === null) {
            Log::warning('Subscription not found in database', [
                'event_type' => $eventType,
                'polar_subscription_id' => $subscriptionId,
            ]);

            return;
        }

        // Determine status
        $polarStatus = $subscription['status'] ?? null;
        $status = $overrideStatus ?? $this->mapPolarStatus($polarStatus);

        // Extract plan from product metadata
        $plan = null;
        $productData = $subscription['product'] ?? null;
        if (is_array($productData)) {
            $productMetadata = $productData['metadata'] ?? [];
            $planTier = $productMetadata['plan_tier'] ?? null;
            if (is_string($planTier)) {
                $plan = $this->resolvePlan($planTier);
            }
        }

        // Extract billing interval
        /** @var array<string, mixed> $subscriptionTyped */
        $subscriptionTyped = $subscription;
        $billingInterval = $this->extractBillingInterval($subscriptionTyped, null);

        // Build update attributes
        $updateAttributes = ['status' => $status];

        if ($plan instanceof Plan) {
            $updateAttributes['plan_id'] = $plan->id;
        }

        if ($billingInterval !== null) {
            $updateAttributes['billing_interval'] = $billingInterval;
        }

        $existingSubscription->forceFill($updateAttributes)->save();

        // Update workspace
        $workspace = $existingSubscription->workspace;

        if ($workspace !== null) {
            $workspaceUpdate = ['subscription_status' => $status];

            // Only update plan if we resolved one
            if ($plan instanceof Plan) {
                $workspaceUpdate['plan_id'] = $plan->id;
            }

            $workspace->forceFill($workspaceUpdate)->save();
        }

        Log::info('Subscription updated', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
            'status' => $status->value,
            'plan_tier' => $plan?->tier,
            'billing_interval' => $billingInterval?->value,
        ]);
    }

    /**
     * Extract billing interval from subscription or order data.
     *
     * @param  array<string, mixed>|null  $subscriptionData
     * @param  array<string, mixed>|null  $orderData
     */
    private function extractBillingInterval(?array $subscriptionData, ?array $orderData): ?BillingInterval
    {
        // Try subscription recurring_interval first
        if (is_array($subscriptionData)) {
            $interval = $subscriptionData['recurring_interval'] ?? $subscriptionData['billing_interval'] ?? null;
            if (is_string($interval)) {
                return BillingInterval::tryFrom($interval);
            }

            // Try product price interval
            $product = $subscriptionData['product'] ?? null;
            if (is_array($product)) {
                $prices = $product['prices'] ?? [];
                if (is_array($prices) && count($prices) > 0) {
                    $price = $prices[0];
                    if (is_array($price)) {
                        $interval = $price['recurring_interval'] ?? null;
                        if (is_string($interval)) {
                            return BillingInterval::tryFrom($interval);
                        }
                    }
                }
            }
        }

        // Try order billing_interval
        if (is_array($orderData)) {
            $interval = $orderData['billing_interval'] ?? null;
            if (is_string($interval)) {
                return BillingInterval::tryFrom($interval);
            }

            // Try product in order
            $product = $orderData['product'] ?? null;
            if (is_array($product)) {
                $prices = $product['prices'] ?? [];
                if (is_array($prices) && count($prices) > 0) {
                    $price = $prices[0];
                    if (is_array($price)) {
                        $interval = $price['recurring_interval'] ?? null;
                        if (is_string($interval)) {
                            return BillingInterval::tryFrom($interval);
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Confirm promotion usage after successful payment.
     */
    private function confirmPromotionUsage(Workspace $workspace, Subscription $subscription, mixed $promotionId): void
    {
        if (! is_string($promotionId) || $promotionId === '') {
            return;
        }

        if (! ctype_digit($promotionId)) {
            Log::warning('Invalid promotion_id format in webhook metadata', [
                'promotion_id' => $promotionId,
                'workspace_id' => $workspace->id,
            ]);

            return;
        }

        $usage = PromotionUsage::query()
            ->where('workspace_id', $workspace->id)
            ->where('promotion_id', (int) $promotionId)
            ->where('status', PromotionUsageStatus::Pending)
            ->first();

        if ($usage instanceof PromotionUsage) {
            $usage->confirm($subscription);

            Log::info('Promotion usage confirmed', [
                'promotion_id' => $promotionId,
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Resolve the Plan model from a tier string.
     */
    private function resolvePlan(?string $tier): ?Plan
    {
        if (! is_string($tier)) {
            return null;
        }

        $planTier = PlanTier::tryFrom($tier);

        if ($planTier === null) {
            return null;
        }

        return Plan::query()->firstOrCreate(
            ['tier' => $planTier->value],
            PlanDefaults::forTier($planTier)
        );
    }

    /**
     * Map Polar status string to SubscriptionStatus enum.
     */
    private function mapPolarStatus(?string $polarStatus): SubscriptionStatus
    {
        return match ($polarStatus) {
            'trialing' => SubscriptionStatus::Trialing,
            'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => SubscriptionStatus::PastDue,
            'canceled', 'cancelled' => SubscriptionStatus::Canceled,
            'revoked' => SubscriptionStatus::Revoked,
            default => SubscriptionStatus::Active,
        };
    }

    /**
     * Convert a timestamp value to CarbonImmutable.
     */
    private function timestampToDateTime(mixed $timestamp): ?CarbonImmutable
    {
        if (is_int($timestamp)) {
            return CarbonImmutable::createFromTimestampUTC($timestamp);
        }

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp);
        } catch (Throwable) {
            return null;
        }
    }
}
