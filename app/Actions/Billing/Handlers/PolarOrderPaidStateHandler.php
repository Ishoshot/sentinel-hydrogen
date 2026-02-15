<?php

declare(strict_types=1);

namespace App\Actions\Billing\Handlers;

use App\Actions\Billing\PolarWebhookSupport;
use App\Actions\Billing\ValueObjects\PolarOrderPaidPayload;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class PolarOrderPaidStateHandler
{
    /**
     * Create a new state updater instance.
     */
    public function __construct(private PolarWebhookSupport $polarWebhookSupport) {}

    /**
     * Sync subscription and workspace state from an order.paid payload.
     */
    public function apply(Workspace $workspace, Plan $plan, PolarOrderPaidPayload $payload): void
    {
        DB::transaction(function () use ($workspace, $plan, $payload): void {
            if ($payload->subscriptionId !== null && $payload->customerId !== null) {
                $subscriptionAttributes = [
                    'workspace_id' => $workspace->id,
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active,
                    'started_at' => $this->polarWebhookSupport->timestampToDateTime($payload->order['created_at'] ?? null) ?? now(),
                    'polar_customer_id' => $payload->customerId,
                ];

                if ($payload->billingInterval instanceof \App\Enums\Billing\BillingInterval) {
                    $subscriptionAttributes['billing_interval'] = $payload->billingInterval;
                }

                $this->applyPeriodBounds($subscriptionAttributes, $payload->subscriptionData);

                $subscription = Subscription::query()->updateOrCreate(
                    ['polar_subscription_id' => $payload->subscriptionId],
                    $subscriptionAttributes
                );

                $this->polarWebhookSupport->confirmPromotionUsage($workspace, $subscription, $payload->promotionId);
            }

            $workspace->forceFill([
                'plan_id' => $plan->id,
                'subscription_status' => SubscriptionStatus::Active,
            ])->save();
        });
    }

    /**
     * Apply subscription period start/end attributes when available.
     *
     * @param  array<string, mixed>  $subscriptionAttributes
     * @param  array<string, mixed>|null  $subscriptionData
     */
    private function applyPeriodBounds(array &$subscriptionAttributes, ?array $subscriptionData): void
    {
        if (! is_array($subscriptionData)) {
            return;
        }

        $periodStart = $this->polarWebhookSupport->timestampToDateTime($subscriptionData['current_period_start'] ?? null);
        $periodEnd = $this->polarWebhookSupport->timestampToDateTime($subscriptionData['current_period_end'] ?? null);

        if ($periodStart instanceof CarbonImmutable) {
            $subscriptionAttributes['current_period_start'] = $periodStart;
        }

        if ($periodEnd instanceof CarbonImmutable) {
            $subscriptionAttributes['current_period_end'] = $periodEnd;
        }
    }
}
