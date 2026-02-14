<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Enums\Workspace\ActivityType;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\Contracts\PolarBillingServiceContract;
use App\Services\Promotions\Contracts\PromotionValidatorContract;
use App\Support\PlanDefaults;
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
        private PolarBillingServiceContract $billingService,
        private PromotionValidatorContract $promotionValidator,
        private ApplyWorkspacePlanChange $applyWorkspacePlanChange,
        private RecordPromotionUsage $recordPromotionUsage,
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
            $promoResult = $this->promotionValidator->validate($promoCode);

            if ($promoResult->failed()) {
                throw new InvalidArgumentException($promoResult->message ?? 'Invalid promotion code.');
            }

            $promotion = $promoResult->promotion;
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
            $checkoutUrl = $this->billingService->createCheckoutSession(
                $workspace,
                $plan,
                $interval,
                $promotion,
                $this->buildSuccessUrl(),
                $actor?->email,
            );

            if ($promotion instanceof Promotion) {
                $this->recordPromotionUsage->pendingCheckout($workspace, $promotion, $checkoutUrl);
            }

            return [
                'action' => 'checkout',
                'checkout_url' => $checkoutUrl,
                'promotion' => $promotion instanceof Promotion
                    ? ['code' => $promotion->code, 'discount' => $promotion->discountDisplay()]
                    : null,
                'billing_interval' => $interval->value,
            ];
        }

        $subscription = $this->applyWorkspacePlanChange->applyActivePlan(
            $workspace,
            $plan,
            ActivityType::SubscriptionUpgraded,
            $actor,
        );

        if ($promotion instanceof Promotion) {
            $this->recordPromotionUsage->completed($workspace, $promotion, $subscription);
        }

        return [
            'action' => 'subscribe',
            'subscription' => $subscription,
            'billing_interval' => $interval->value,
        ];
    }

    /**
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

                $subscription = $this->applyWorkspacePlanChange->applyActivePlan(
                    $workspace,
                    $plan,
                    ActivityType::SubscriptionUpgraded,
                    $actor,
                );

                if ($promotion instanceof Promotion) {
                    $this->recordPromotionUsage->completed($workspace, $promotion, $subscription);
                }

                return [
                    'action' => 'upgrade',
                    'subscription' => $subscription,
                    'billing_interval' => $interval->value,
                ];
            }

            $checkoutUrl = $this->billingService->createCheckoutSession(
                $workspace,
                $plan,
                $interval,
                $promotion,
                $this->buildSuccessUrl(),
                $actor?->email,
            );

            if ($promotion instanceof Promotion) {
                $this->recordPromotionUsage->pendingCheckout($workspace, $promotion, $checkoutUrl);
            }

            return [
                'action' => 'checkout',
                'checkout_url' => $checkoutUrl,
                'promotion' => $promotion instanceof Promotion
                    ? ['code' => $promotion->code, 'discount' => $promotion->discountDisplay()]
                    : null,
                'billing_interval' => $interval->value,
            ];
        }

        $subscription = $this->applyWorkspacePlanChange->applyActivePlan(
            $workspace,
            $plan,
            ActivityType::SubscriptionUpgraded,
            $actor,
        );

        if ($promotion instanceof Promotion) {
            $this->recordPromotionUsage->completed($workspace, $promotion, $subscription);
        }

        return [
            'action' => 'upgrade',
            'subscription' => $subscription,
            'billing_interval' => $interval->value,
        ];
    }

    /**
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

        $subscription = $this->applyWorkspacePlanChange->applyActivePlan(
            $workspace,
            $plan,
            ActivityType::SubscriptionDowngraded,
            $actor,
        );

        return [
            'action' => 'downgrade',
            'subscription' => $subscription,
            'billing_interval' => $interval->value,
        ];
    }

    /**
     * @return array{action: string, subscription: Subscription, billing_interval: string}
     */
    private function handleCancel(Workspace $workspace, ?User $actor): array
    {
        $latestSubscription = $workspace->subscriptions()->latest()->first();

        if ($this->billingService->isConfigured() && $latestSubscription !== null) {
            $polarSubscriptionId = $latestSubscription->polar_subscription_id;

            if (is_string($polarSubscriptionId) && $polarSubscriptionId !== '') {
                $this->billingService->revokeSubscription($workspace, $polarSubscriptionId);
            }
        }

        $foundationPlan = Plan::query()->firstOrCreate(
            ['tier' => PlanTier::Foundation->value],
            PlanDefaults::forTier(PlanTier::Foundation)
        );

        $subscription = $this->applyWorkspacePlanChange->cancelToFoundation(
            $workspace,
            $foundationPlan,
            $latestSubscription,
            $actor,
        );

        return [
            'action' => 'cancel',
            'subscription' => $subscription,
            'billing_interval' => BillingInterval::Monthly->value,
        ];
    }

    /**
     * Build the checkout success redirect URL template.
     */
    private function buildSuccessUrl(): string
    {
        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return $frontendUrl.'/billing/success?checkout_id={CHECKOUT_ID}';
    }
}
