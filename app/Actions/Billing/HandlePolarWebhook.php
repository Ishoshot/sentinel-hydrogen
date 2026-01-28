<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\PlanTier;
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
            return;
        }

        match ($type) {
            'checkout.completed' => $this->handleCheckoutCompleted($event),
            'subscription.created',
            'subscription.updated',
            'subscription.canceled',
            'subscription.deleted' => $this->handleSubscriptionEvent($event),
            'payment.failed' => $this->handlePaymentFailed($event),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleCheckoutCompleted(array $event): void
    {
        $checkout = $event['data']['object'] ?? null;

        if (! is_array($checkout)) {
            return;
        }

        $metadata = $checkout['metadata'] ?? [];
        $workspaceId = $metadata['workspace_id'] ?? null;
        $subscriptionId = $checkout['subscription_id'] ?? $checkout['subscription'] ?? null;
        $customerId = $checkout['customer_id'] ?? $checkout['customer'] ?? null;
        $planTier = $metadata['plan_tier'] ?? null;
        $promotionId = $metadata['promotion_id'] ?? null;

        if (! is_string($workspaceId) || ! is_string($subscriptionId) || ! is_string($customerId)) {
            return;
        }

        $workspace = Workspace::query()->find((int) $workspaceId);

        if ($workspace === null) {
            return;
        }

        $plan = $this->resolvePlan($planTier);

        if (! $plan instanceof Plan) {
            return;
        }

        $subscription = Subscription::updateOrCreate(
            ['polar_subscription_id' => $subscriptionId],
            [
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'started_at' => now(),
                'polar_customer_id' => $customerId,
                'polar_subscription_id' => $subscriptionId,
            ]
        );

        $workspace->forceFill([
            'plan_id' => $plan->id,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();

        $this->confirmPromotionUsage($workspace, $subscription, $promotionId);
    }

    /**
     * Confirm promotion usage after successful checkout.
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
     * @param  array<string, mixed>  $event
     */
    private function handleSubscriptionEvent(array $event): void
    {
        $subscription = $event['data']['object'] ?? null;

        if (! is_array($subscription)) {
            return;
        }

        $metadata = $subscription['metadata'] ?? [];
        $workspaceId = $metadata['workspace_id'] ?? $subscription['workspace_id'] ?? null;

        if (! is_string($workspaceId)) {
            Log::warning('Polar subscription missing workspace metadata', [
                'subscription_id' => $subscription['id'] ?? null,
            ]);

            return;
        }

        $workspace = Workspace::query()->find((int) $workspaceId);

        if ($workspace === null) {
            return;
        }

        $planTier = $metadata['plan_tier'] ?? $subscription['plan_tier'] ?? null;
        $plan = $this->resolvePlan($planTier);

        if (! $plan instanceof Plan) {
            return;
        }

        $status = $this->mapPolarStatus($subscription['status'] ?? null);
        $trialEnd = $this->timestampToDateTime($subscription['trial_end'] ?? null);
        $startedAt = $this->timestampToDateTime($subscription['started_at'] ?? null);
        $endsAt = $this->timestampToDateTime($subscription['ended_at'] ?? $subscription['current_period_end'] ?? null);
        $subscriptionId = $subscription['id'] ?? null;

        if (! is_string($subscriptionId)) {
            return;
        }

        Subscription::updateOrCreate(
            ['polar_subscription_id' => $subscriptionId],
            [
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'started_at' => $startedAt,
                'ends_at' => $endsAt,
                'polar_customer_id' => $subscription['customer_id'] ?? $subscription['customer'] ?? null,
                'polar_subscription_id' => $subscriptionId,
            ]
        );

        $workspace->forceFill([
            'plan_id' => $status === SubscriptionStatus::Canceled ? $this->resolvePlan(PlanTier::Foundation->value)?->id : $plan->id,
            'subscription_status' => $status,
            'trial_ends_at' => $trialEnd,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handlePaymentFailed(array $event): void
    {
        $invoice = $event['data']['object'] ?? null;

        if (! is_array($invoice)) {
            return;
        }

        $subscriptionId = $invoice['subscription_id'] ?? $invoice['subscription'] ?? null;

        if (! is_string($subscriptionId)) {
            return;
        }

        $subscription = Subscription::query()
            ->where('polar_subscription_id', $subscriptionId)
            ->first();

        if ($subscription === null) {
            return;
        }

        $subscription->forceFill([
            'status' => SubscriptionStatus::PastDue,
        ])->save();

        $workspace = $subscription->workspace;

        if ($workspace !== null) {
            $workspace->forceFill([
                'subscription_status' => SubscriptionStatus::PastDue,
            ])->save();
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
     * Map Polar subscription status to internal SubscriptionStatus enum.
     */
    private function mapPolarStatus(?string $status): SubscriptionStatus
    {
        return match ($status) {
            'trialing' => SubscriptionStatus::Trialing,
            'past_due', 'unpaid', 'incomplete', 'incomplete_expired' => SubscriptionStatus::PastDue,
            'canceled', 'cancelled' => SubscriptionStatus::Canceled,
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
