<?php

declare(strict_types=1);

namespace App\Actions\Billing\Handlers;

use App\Actions\Billing\ValueObjects\PolarSubscriptionSyncPayload;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies local subscription/workspace state transitions for Polar events.
 */
final class PolarSubscriptionStateHandler
{
    /**
     * Mark a subscription canceled while retaining workspace access.
     */
    public function markCanceled(Subscription $subscription, ?CarbonImmutable $endsAt): void
    {
        $subscription->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'ends_at' => $endsAt,
        ])->save();
    }

    /**
     * Mark a subscription active again and clear period end.
     */
    public function markUncanceled(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'ends_at' => null,
            ])->save();

            $workspace = $subscription->workspace;

            if ($workspace !== null) {
                $workspace->forceFill([
                    'subscription_status' => SubscriptionStatus::Active,
                ])->save();
            }
        });
    }

    /**
     * Revoke subscription access and downgrade workspace to a fallback plan.
     */
    public function markRevoked(Subscription $subscription, ?Plan $fallbackPlan): void
    {
        DB::transaction(function () use ($subscription, $fallbackPlan): void {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Revoked,
                'ends_at' => now(),
            ])->save();

            $workspace = $subscription->workspace;

            if ($workspace !== null) {
                $workspace->forceFill([
                    'plan_id' => $fallbackPlan?->id ?? $workspace->plan_id,
                    'subscription_status' => SubscriptionStatus::Revoked,
                ])->save();
            }
        });
    }

    /**
     * Persist subscription/workspace sync updates from Polar payload data.
     */
    public function syncSubscription(Subscription $subscription, PolarSubscriptionSyncPayload $syncPayload): void
    {
        DB::transaction(function () use ($subscription, $syncPayload): void {
            $subscription->forceFill($syncPayload->updateAttributes)->save();

            $workspace = $subscription->workspace;

            if ($workspace !== null) {
                $workspaceUpdate = ['subscription_status' => $syncPayload->status];

                if ($syncPayload->plan instanceof Plan) {
                    $workspaceUpdate['plan_id'] = $syncPayload->plan->id;
                }

                $workspace->forceFill($workspaceUpdate)->save();
            }
        });
    }
}
