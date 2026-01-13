<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscriptions;

use App\Actions\Promotions\ValidatePromotion;
use App\Actions\Subscriptions\UpgradeSubscription;
use App\Enums\BillingInterval;
use App\Enums\PlanTier;
use App\Http\Requests\Subscription\UpgradeSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Workspace;
use App\Services\Billing\PolarBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Upgrade a workspace subscription to a higher plan tier.
 */
final class UpgradeSubscriptionController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpgradeSubscriptionRequest $request,
        Workspace $workspace,
        PolarBillingService $billingService,
        UpgradeSubscription $upgradeSubscription,
        ValidatePromotion $validatePromotion,
    ): JsonResponse {
        Gate::authorize('manageSubscription', $workspace);

        /** @var array{plan_tier: string, billing_interval?: string, promo_code?: string|null} $validated */
        $validated = $request->validated();
        $tier = PlanTier::from($validated['plan_tier']);
        $interval = BillingInterval::tryFrom($validated['billing_interval'] ?? 'monthly') ?? BillingInterval::Monthly;
        $promoCode = $validated['promo_code'] ?? null;

        if ($tier->isFree()) {
            abort(422, 'Use the cancel endpoint to downgrade to Foundation.');
        }

        $plan = Plan::query()->where('tier', $tier->value)->first();

        if ($plan === null) {
            abort(422, 'Requested plan is not available.');
        }

        $promotion = null;

        if (is_string($promoCode) && $promoCode !== '') {
            $promoResult = $validatePromotion->handle($promoCode);

            if (! $promoResult['valid']) {
                return response()->json([
                    'message' => $promoResult['message'],
                    'errors' => ['promo_code' => [$promoResult['message']]],
                ], 422);
            }

            $promotion = $promoResult['promotion'];
        }

        if ($billingService->isConfigured()) {
            try {
                $checkoutUrl = $billingService->createCheckoutSession($workspace, $plan, $interval, $promotion);
            } catch (InvalidArgumentException $invalidArgumentException) {
                return response()->json([
                    'message' => $invalidArgumentException->getMessage(),
                ], 422);
            }

            if ($promotion instanceof Promotion) {
                $promotion->incrementUsage();
            }

            return response()->json([
                'data' => [
                    'checkout_url' => $checkoutUrl,
                    'billing_interval' => $interval->value,
                    'promotion' => $promotion !== null ? [
                        'code' => $promotion->code,
                        'discount' => $promotion->discountDisplay(),
                    ] : null,
                ],
                'message' => 'Polar checkout session created.',
            ]);
        }

        $subscription = $upgradeSubscription->handle($workspace, $tier, $request->user());

        if ($promotion instanceof Promotion) {
            $promotion->incrementUsage();
        }

        return response()->json([
            'data' => new SubscriptionResource($workspace->refresh()->load('plan')),
            'message' => 'Subscription upgraded successfully.',
            'subscription_id' => $subscription->id,
        ]);
    }
}
