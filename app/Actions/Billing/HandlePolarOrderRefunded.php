<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class HandlePolarOrderRefunded
{
    /**
     * Create a new action instance.
     */
    public function __construct(private PolarWebhookSupport $polarWebhookSupport) {}

    /**
     * Handle a refunded order webhook event.
     */
    public function handle(VerifiedPolarWebhook $webhook): void
    {
        $order = $webhook->data;

        if ($order === []) {
            Log::warning('order.refunded webhook missing data payload');

            return;
        }

        $subscriptionData = $order['subscription'] ?? null;
        $subscriptionId = is_array($subscriptionData)
            ? ($subscriptionData['id'] ?? null)
            : ($order['subscription_id'] ?? null);

        if (! is_string($subscriptionId)) {
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

        Log::info('Order refunded - access revoked', [
            'subscription_id' => $existingSubscription->id,
            'workspace_id' => $existingSubscription->workspace_id,
            'order_id' => $order['id'] ?? null,
        ]);
    }
}
