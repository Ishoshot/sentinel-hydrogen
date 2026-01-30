<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscriptions;

use App\Actions\Subscriptions\ChangeSubscription;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Http\Requests\Subscription\ChangeSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Handle subscription changes: subscribe, upgrade, downgrade, and cancel.
 */
final class ChangeSubscriptionController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ChangeSubscriptionRequest $request,
        Workspace $workspace,
        ChangeSubscription $changeSubscription,
    ): JsonResponse {
        Gate::authorize('manageSubscription', $workspace);

        /** @var array{plan_tier: string, billing_interval?: string, promo_code?: string|null} $validated */
        $validated = $request->validated();
        $tier = PlanTier::from($validated['plan_tier']);
        $interval = BillingInterval::tryFrom($validated['billing_interval'] ?? 'monthly') ?? BillingInterval::Monthly;
        $promoCode = $validated['promo_code'] ?? null;

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        $result = $changeSubscription->handle($workspace, $tier, $interval, $promoCode, $user);

        if ($result['action'] === 'checkout') {
            return response()->json([
                'data' => [
                    'checkout_url' => $result['checkout_url'] ?? null,
                    'billing_interval' => $result['billing_interval'] ?? null,
                    'promotion' => $result['promotion'] ?? null,
                ],
                'message' => 'Polar checkout session created.',
            ]);
        }

        return response()->json([
            'data' => new SubscriptionResource($workspace->refresh()->load('plan')),
            'message' => match ($result['action']) {
                'subscribe', 'upgrade' => 'Subscription upgraded successfully.',
                'downgrade' => 'Subscription downgraded successfully.',
                'cancel' => 'Subscription canceled and downgraded to Foundation plan.',
                default => 'Subscription changed successfully.',
            },
        ]);
    }
}
