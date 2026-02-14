<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class HandlePolarOrderPaid
{
    /**
     * Create a new action instance.
     */
    public function __construct(private PolarWebhookSupport $polarWebhookSupport) {}

    /**
     * Handle a paid order webhook event.
     */
    public function handle(VerifiedPolarWebhook $webhook): void
    {
        $order = $webhook->data;

        if ($order === []) {
            Log::warning('order.paid webhook missing data payload');

            return;
        }

        $subscriptionData = $order['subscription'] ?? null;
        $subscriptionId = is_array($subscriptionData)
            ? ($subscriptionData['id'] ?? null)
            : ($order['subscription_id'] ?? null);

        $customerData = $order['customer'] ?? null;
        $customerId = is_array($customerData)
            ? ($customerData['id'] ?? null)
            : ($order['customer_id'] ?? null);

        $metadata = $order['metadata'] ?? [];
        if (empty($metadata) && is_array($subscriptionData)) {
            $metadata = $subscriptionData['metadata'] ?? [];
        }

        $workspaceId = $metadata['workspace_id'] ?? null;
        $planTier = $metadata['plan_tier'] ?? null;
        $promotionId = $metadata['promotion_id'] ?? null;

        /** @var array<string, mixed>|null $subscriptionDataTyped */
        $subscriptionDataTyped = is_array($subscriptionData) ? $subscriptionData : null;

        /** @var array<string, mixed> $orderTyped */
        $orderTyped = $order;

        $billingInterval = $this->polarWebhookSupport->extractBillingInterval($subscriptionDataTyped, $orderTyped);

        if (! is_string($workspaceId) && is_string($subscriptionId)) {
            $existingSubscription = Subscription::query()
                ->where('polar_subscription_id', $subscriptionId)
                ->first();

            if ($existingSubscription !== null) {
                $workspaceId = (string) $existingSubscription->workspace_id;

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

        $plan = $this->resolvePlanFromOrder($workspace, $order, $planTier);

        if (! $plan instanceof Plan) {
            Log::warning('Could not resolve plan for paid order', [
                'workspace_id' => $workspace->id,
                'order_id' => $order['id'] ?? null,
            ]);

            return;
        }

        DB::transaction(function () use ($workspace, $plan, $subscriptionId, $customerId, $subscriptionData, $billingInterval, $order, $promotionId): void {
            if (is_string($subscriptionId) && is_string($customerId)) {
                $subscriptionAttributes = [
                    'workspace_id' => $workspace->id,
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active,
                    'started_at' => $this->polarWebhookSupport->timestampToDateTime($order['created_at'] ?? null) ?? now(),
                    'polar_customer_id' => $customerId,
                ];

                if ($billingInterval instanceof BillingInterval) {
                    $subscriptionAttributes['billing_interval'] = $billingInterval;
                }

                if (is_array($subscriptionData)) {
                    $periodStart = $this->polarWebhookSupport->timestampToDateTime($subscriptionData['current_period_start'] ?? null);
                    $periodEnd = $this->polarWebhookSupport->timestampToDateTime($subscriptionData['current_period_end'] ?? null);

                    if ($periodStart instanceof CarbonImmutable) {
                        $subscriptionAttributes['current_period_start'] = $periodStart;
                    }

                    if ($periodEnd instanceof CarbonImmutable) {
                        $subscriptionAttributes['current_period_end'] = $periodEnd;
                    }
                }

                $subscription = Subscription::query()->updateOrCreate(
                    ['polar_subscription_id' => $subscriptionId],
                    $subscriptionAttributes
                );

                $this->polarWebhookSupport->confirmPromotionUsage($workspace, $subscription, $promotionId);
            }

            $workspace->forceFill([
                'plan_id' => $plan->id,
                'subscription_status' => SubscriptionStatus::Active,
            ])->save();
        });

        Log::info('Order paid - access granted', [
            'workspace_id' => $workspace->id,
            'plan_tier' => $plan->tier,
            'billing_interval' => $billingInterval?->value,
            'order_id' => $order['id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function resolvePlanFromOrder(Workspace $workspace, array $order, mixed $planTier): ?Plan
    {
        $plan = $this->polarWebhookSupport->resolvePlan(is_string($planTier) ? $planTier : null);

        if ($plan instanceof Plan) {
            return $plan;
        }

        $productData = $order['product'] ?? null;
        if (is_array($productData)) {
            $productMetadata = $productData['metadata'] ?? [];
            $productPlanTier = $productMetadata['plan_tier'] ?? null;
            $plan = $this->polarWebhookSupport->resolvePlan(is_string($productPlanTier) ? $productPlanTier : null);
        }

        if ($plan instanceof Plan) {
            return $plan;
        }

        return $workspace->plan;
    }
}
