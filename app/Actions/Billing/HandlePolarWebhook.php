<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Promotions\PromotionUsageStatus;
use App\Enums\Webhooks\PolarWebhookEvent;
use App\Models\Plan;
use App\Models\PromotionUsage;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\PolarBillingService;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
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
        $webhook = $this->billingService->verifyWebhook($payload, $headers);

        if ($webhook->type === PolarWebhookEvent::Unknown) {
            Log::debug('Unhandled Polar webhook event type', ['type' => $webhook->type->value]);

            return;
        }

        match ($webhook->type) {
            // Primary event for granting/maintaining access
            PolarWebhookEvent::OrderPaid => $this->handleOrderPaid($webhook),

            // Revoke access on refund
            PolarWebhookEvent::OrderRefunded => $this->handleOrderRefunded($webhook),

            // Subscription lifecycle events for status changes
            PolarWebhookEvent::SubscriptionActive => $this->handleSubscriptionActive($webhook),
            PolarWebhookEvent::SubscriptionCanceled => $this->handleSubscriptionCanceled($webhook),
            PolarWebhookEvent::SubscriptionUncanceled => $this->handleSubscriptionUncanceled($webhook),
            PolarWebhookEvent::SubscriptionRevoked => $this->handleSubscriptionRevoked($webhook),
            PolarWebhookEvent::SubscriptionUpdated => $this->handleSubscriptionUpdated($webhook),

            // Informational - subscription created but not yet active
            PolarWebhookEvent::SubscriptionCreated => $this->handleSubscriptionCreated($webhook),

            default => null, // Ignore other events
        };
    }

    /**
     * Handle order.paid - the recommended event for provisioning access.
     *
     * This fires for both new subscriptions and renewals.
     */
    private function handleOrderPaid(VerifiedPolarWebhook $webhook): void
    {
        $order = $webhook->data;

        if ($order === []) {
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

            if ($billingInterval instanceof BillingInterval) {
                $subscriptionAttributes['billing_interval'] = $billingInterval;
            }

            // Extract billing period from subscription data
            if (is_array($subscriptionData)) {
                $periodStart = $this->timestampToDateTime($subscriptionData['current_period_start'] ?? null);
                $periodEnd = $this->timestampToDateTime($subscriptionData['current_period_end'] ?? null);

                if ($periodStart instanceof CarbonImmutable) {
                    $subscriptionAttributes['current_period_start'] = $periodStart;
                }

                if ($periodEnd instanceof CarbonImmutable) {
                    $subscriptionAttributes['current_period_end'] = $periodEnd;
                }
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
     */
    private function handleOrderRefunded(VerifiedPolarWebhook $webhook): void
    {
        $order = $webhook->data;

        if ($order === []) {
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
     */
    private function handleSubscriptionActive(VerifiedPolarWebhook $webhook): void
    {
        $this->updateSubscriptionFromWebhook($webhook, SubscriptionStatus::Active);
    }

    /**
     * Handle subscription.canceled - customer canceled their subscription.
     *
     * The subscription remains active until the end of the billing period.
     */
    private function handleSubscriptionCanceled(VerifiedPolarWebhook $webhook): void
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
     */
    private function handleSubscriptionUncanceled(VerifiedPolarWebhook $webhook): void
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
     */
    private function handleSubscriptionRevoked(VerifiedPolarWebhook $webhook): void
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
     */
    private function handleSubscriptionUpdated(VerifiedPolarWebhook $webhook): void
    {
        $this->updateSubscriptionFromWebhook($webhook, null);
    }

    /**
     * Handle subscription.created - subscription created but may not be active yet.
     *
     * We don't grant access here - wait for order.paid.
     */
    private function handleSubscriptionCreated(VerifiedPolarWebhook $webhook): void
    {
        // Log for debugging but don't take action
        // Access is granted on order.paid
        Log::debug('Subscription created (waiting for order.paid)', [
            'subscription_id' => $webhook->getSubscriptionId(),
        ]);
    }

    /**
     * Update subscription from webhook event data.
     *
     * Syncs plan, billing interval, and status.
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

        // Extract billing period
        $periodStart = $this->timestampToDateTime($subscription['current_period_start'] ?? null);
        $periodEnd = $this->timestampToDateTime($subscription['current_period_end'] ?? null);

        // Build update attributes
        $updateAttributes = ['status' => $status];

        if ($plan instanceof Plan) {
            $updateAttributes['plan_id'] = $plan->id;
        }

        if ($billingInterval instanceof BillingInterval) {
            $updateAttributes['billing_interval'] = $billingInterval;
        }

        if ($periodStart instanceof CarbonImmutable) {
            $updateAttributes['current_period_start'] = $periodStart;
        }

        if ($periodEnd instanceof CarbonImmutable) {
            $updateAttributes['current_period_end'] = $periodEnd;
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
            'current_period_start' => $periodStart?->toIso8601String(),
            'current_period_end' => $periodEnd?->toIso8601String(),
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
                if (is_array($prices) && $prices !== []) {
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
                if (is_array($prices) && $prices !== []) {
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
