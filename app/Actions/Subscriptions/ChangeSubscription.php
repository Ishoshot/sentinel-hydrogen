<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Activities\LogActivity;
use App\Actions\Promotions\ValidatePromotion;
use App\Enums\ActivityType;
use App\Enums\BillingInterval;
use App\Enums\PlanTier;
use App\Enums\PromotionUsageStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\PolarBillingService;
use App\Support\PlanDefaults;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Orchestrates all subscription changes: subscribe, upgrade, downgrade, and cancel.
 */
final readonly class ChangeSubscription
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PolarBillingService $billingService,
        private ValidatePromotion $validatePromotion,
        private LogActivity $logActivity,
    ) {}

    /**
     * Handle a subscription change request.
     *
     * @return array{action: string, checkout_url?: string, subscription?: Subscription, promotion?: array{code: string, discount: string}|null, billing_interval?: string}
     */
    public function handle(
        Workspace $workspace,
        PlanTier $targetTier,
        BillingInterval $interval = BillingInterval::Monthly,
        ?string $promoCode = null,
        ?User $actor = null,
    ): array {
        $currentTier = PlanTier::from($workspace->getCurrentTier());
        $direction = $this->determineDirection($currentTier, $targetTier);

        if ($direction === 'none') {
            throw new InvalidArgumentException('Workspace is already on the requested plan.');
        }

        $targetPlan = Plan::query()->firstOrCreate(
            ['tier' => $targetTier->value],
            PlanDefaults::forTier($targetTier)
        );

        $promotion = null;

        if (in_array($direction, ['subscribe', 'upgrade'], true) && is_string($promoCode) && $promoCode !== '') {
            $promoResult = $this->validatePromotion->handle($promoCode);

            if (! $promoResult['valid']) {
                throw new InvalidArgumentException($promoResult['message'] ?? 'Invalid promotion code.');
            }

            $promotion = $promoResult['promotion'];
        }

        return match ($direction) {
            'subscribe' => $this->handleSubscribe($workspace, $targetPlan, $interval, $promotion, $actor),
            'upgrade' => $this->handleUpgrade($workspace, $targetPlan, $interval, $promotion, $actor),
            'downgrade' => $this->handleDowngrade($workspace, $targetPlan, $interval, $actor),
            'cancel' => $this->handleCancel($workspace, $actor),
            default => throw new InvalidArgumentException('Unexpected subscription change direction.'),
        };
    }

    /**
     * Determine the direction of change between tiers.
     */
    private function determineDirection(PlanTier $current, PlanTier $target): string
    {
        if ($current === $target) {
            return 'none';
        }

        if ($current->isFree()) {
            return 'subscribe';
        }

        if ($target->isFree()) {
            return 'cancel';
        }

        return $target->rank() > $current->rank() ? 'upgrade' : 'downgrade';
    }

    /**
     * Handle new subscription from free tier to a paid tier.
     *
     * @return array{action: string, checkout_url?: string, subscription?: Subscription, promotion?: array{code: string, discount: string}|null, billing_interval: string}
     */
    private function handleSubscribe(
        Workspace $workspace,
        Plan $plan,
        BillingInterval $interval,
        ?Promotion $promotion,
        ?User $actor,
    ): array {
        if ($this->billingService->isConfigured()) {
            $successUrl = $this->buildSuccessUrl();

            $checkoutUrl = $this->billingService->createCheckoutSession(
                $workspace,
                $plan,
                $interval,
                $promotion,
                $successUrl,
                $actor?->email,
            );

            if ($promotion instanceof Promotion) {
                PromotionUsage::create([
                    'promotion_id' => $promotion->id,
                    'workspace_id' => $workspace->id,
                    'status' => PromotionUsageStatus::Pending,
                    'checkout_url' => $checkoutUrl,
                ]);
            }

            return [
                'action' => 'checkout',
                'checkout_url' => $checkoutUrl,
                'promotion' => $promotion instanceof Promotion ? [
                    'code' => $promotion->code,
                    'discount' => $promotion->discountDisplay(),
                ] : null,
                'billing_interval' => $interval->value,
            ];
        }

        $subscription = $this->applyLocally($workspace, $plan, SubscriptionStatus::Active, ActivityType::SubscriptionUpgraded, $actor);

        if ($promotion instanceof Promotion) {
            PromotionUsage::create([
                'promotion_id' => $promotion->id,
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
                'status' => PromotionUsageStatus::Completed,
                'confirmed_at' => now(),
            ]);
            $promotion->incrementUsage();
        }

        return [
            'action' => 'subscribe',
            'subscription' => $subscription,
            'billing_interval' => $interval->value,
        ];
    }

    /**
     * Handle upgrade from one paid tier to a higher paid tier.
     *
     * @return array{action: string, checkout_url?: string, subscription?: Subscription, promotion?: array{code: string, discount: string}|null, billing_interval: string}
     */
    private function handleUpgrade(
        Workspace $workspace,
        Plan $plan,
        BillingInterval $interval,
        ?Promotion $promotion,
        ?User $actor,
    ): array {
        if ($this->billingService->isConfigured()) {
            $latestSubscription = $workspace->subscriptions()->latest()->first();
            $polarSubscriptionId = $latestSubscription?->polar_subscription_id;

            if (is_string($polarSubscriptionId) && $polarSubscriptionId !== '') {
                $this->billingService->updateSubscription($workspace, $polarSubscriptionId, $plan, $interval);

                $subscription = $this->applyLocally($workspace, $plan, SubscriptionStatus::Active, ActivityType::SubscriptionUpgraded, $actor);

                if ($promotion instanceof Promotion) {
                    PromotionUsage::create([
                        'promotion_id' => $promotion->id,
                        'workspace_id' => $workspace->id,
                        'subscription_id' => $subscription->id,
                        'status' => PromotionUsageStatus::Completed,
                        'confirmed_at' => now(),
                    ]);
                    $promotion->incrementUsage();
                }

                return [
                    'action' => 'upgrade',
                    'subscription' => $subscription,
                    'billing_interval' => $interval->value,
                ];
            }

            $successUrl = $this->buildSuccessUrl();

            $checkoutUrl = $this->billingService->createCheckoutSession(
                $workspace,
                $plan,
                $interval,
                $promotion,
                $successUrl,
                $actor?->email,
            );

            if ($promotion instanceof Promotion) {
                PromotionUsage::create([
                    'promotion_id' => $promotion->id,
                    'workspace_id' => $workspace->id,
                    'status' => PromotionUsageStatus::Pending,
                    'checkout_url' => $checkoutUrl,
                ]);
            }

            return [
                'action' => 'checkout',
                'checkout_url' => $checkoutUrl,
                'promotion' => $promotion instanceof Promotion ? [
                    'code' => $promotion->code,
                    'discount' => $promotion->discountDisplay(),
                ] : null,
                'billing_interval' => $interval->value,
            ];
        }

        $subscription = $this->applyLocally($workspace, $plan, SubscriptionStatus::Active, ActivityType::SubscriptionUpgraded, $actor);

        if ($promotion instanceof Promotion) {
            PromotionUsage::create([
                'promotion_id' => $promotion->id,
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
                'status' => PromotionUsageStatus::Completed,
                'confirmed_at' => now(),
            ]);
            $promotion->incrementUsage();
        }

        return [
            'action' => 'upgrade',
            'subscription' => $subscription,
            'billing_interval' => $interval->value,
        ];
    }

    /**
     * Handle downgrade from one paid tier to a lower paid tier.
     *
     * @return array{action: string, subscription: Subscription, billing_interval: string}
     */
    private function handleDowngrade(
        Workspace $workspace,
        Plan $plan,
        BillingInterval $interval,
        ?User $actor,
    ): array {
        if ($this->billingService->isConfigured()) {
            $latestSubscription = $workspace->subscriptions()->latest()->first();
            $polarSubscriptionId = $latestSubscription?->polar_subscription_id;

            if (is_string($polarSubscriptionId) && $polarSubscriptionId !== '') {
                $this->billingService->updateSubscription($workspace, $polarSubscriptionId, $plan, $interval);
            }
        }

        $subscription = $this->applyLocally($workspace, $plan, SubscriptionStatus::Active, ActivityType::SubscriptionDowngraded, $actor);

        return [
            'action' => 'downgrade',
            'subscription' => $subscription,
            'billing_interval' => $interval->value,
        ];
    }

    /**
     * Handle cancellation (paid tier to Foundation/free).
     *
     * @return array{action: string, subscription: Subscription, billing_interval: string}
     */
    private function handleCancel(Workspace $workspace, ?User $actor): array
    {
        if ($this->billingService->isConfigured()) {
            $latestSubscription = $workspace->subscriptions()->latest()->first();
            $polarSubscriptionId = $latestSubscription?->polar_subscription_id;

            if (is_string($polarSubscriptionId) && $polarSubscriptionId !== '') {
                $this->billingService->revokeSubscription($workspace, $polarSubscriptionId);
            }
        }

        $foundationPlan = Plan::query()->firstOrCreate(
            ['tier' => PlanTier::Foundation->value],
            PlanDefaults::forTier(PlanTier::Foundation)
        );

        $subscription = DB::transaction(function () use ($workspace, $foundationPlan, $actor): Subscription {
            $workspace->forceFill([
                'plan_id' => $foundationPlan->id,
                'subscription_status' => SubscriptionStatus::Canceled,
            ])->save();

            $subscription = Subscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $foundationPlan->id,
                'status' => SubscriptionStatus::Canceled,
                'started_at' => now(),
                'ends_at' => now(),
            ]);

            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::SubscriptionCanceled,
                description: 'Subscription canceled and downgraded to Foundation plan',
                actor: $actor,
                subject: $subscription,
                metadata: ['plan_tier' => $foundationPlan->tier],
            );

            return $subscription;
        });

        return [
            'action' => 'cancel',
            'subscription' => $subscription,
            'billing_interval' => BillingInterval::Monthly->value,
        ];
    }

    /**
     * Apply subscription change locally (database only).
     */
    private function applyLocally(
        Workspace $workspace,
        Plan $plan,
        SubscriptionStatus $status,
        ActivityType $activityType,
        ?User $actor,
    ): Subscription {
        return DB::transaction(function () use ($workspace, $plan, $status, $activityType, $actor): Subscription {
            $workspace->forceFill([
                'plan_id' => $plan->id,
                'subscription_status' => $status,
            ])->save();

            // Set initial billing period (one month from now for local/dev)
            $periodStart = now();
            $periodEnd = now()->addMonth();

            $subscription = Subscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'started_at' => now(),
                'ends_at' => null,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
            ]);

            $this->logActivity->handle(
                workspace: $workspace,
                type: $activityType,
                description: sprintf('Subscription %s to %s plan', $activityType === ActivityType::SubscriptionDowngraded ? 'downgraded' : 'upgraded', ucfirst($plan->tier)),
                actor: $actor,
                subject: $subscription,
                metadata: ['plan_tier' => $plan->tier],
            );

            return $subscription;
        });
    }

    /**
     * Build the success URL for Polar checkout redirects.
     */
    private function buildSuccessUrl(): string
    {
        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return $frontendUrl.'/billing/success?checkout_id={CHECKOUT_ID}';
    }
}
