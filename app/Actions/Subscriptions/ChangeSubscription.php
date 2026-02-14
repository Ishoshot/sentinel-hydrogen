<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Subscriptions\Support\ChangeResponseFactory;
use App\Actions\Subscriptions\Support\PromotionHandler;
use App\Actions\Subscriptions\Support\ResolvedBillingContext;
use App\Actions\Subscriptions\Support\TransitionDirection;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\PlanTier;
use App\Enums\Workspace\ActivityType;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\Contracts\PolarBillingServiceContract;
use App\Support\PlanDefaults;

/**
 * Orchestrates all subscription changes: subscribe, upgrade, downgrade, and cancel.
 */
final readonly class ChangeSubscription
{
    public function __construct(
        private PolarBillingServiceContract $billingService,
        private ApplyWorkspacePlanChange $applyWorkspacePlanChange,
        private PromotionHandler $promotionHandler,
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
        $direction = TransitionDirection::resolve($currentTier, $targetTier);

        $targetPlan = Plan::query()->firstOrCreate(
            ['tier' => $targetTier->value],
            PlanDefaults::forTier($targetTier)
        );

        $promotion = $this->promotionHandler->validateIfApplicable($direction, $promoCode);

        return match ($direction) {
            TransitionDirection::Subscribe => $this->handleSubscribe($workspace, $targetPlan, $interval, $promotion, $actor),
            TransitionDirection::Upgrade => $this->handleUpgrade($workspace, $targetPlan, $interval, $promotion, $actor),
            TransitionDirection::Downgrade => $this->handleDowngrade($workspace, $targetPlan, $interval, $actor),
            TransitionDirection::Cancel => $this->handleCancel($workspace, $actor),
        };
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
            return $this->initiateCheckout($workspace, $plan, $interval, $promotion, $actor);
        }

        return $this->applyAndRespond('subscribe', $workspace, $plan, ActivityType::SubscriptionUpgraded, $interval, $promotion, $actor);
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
            $billing = ResolvedBillingContext::forWorkspace($workspace);

            if ($billing->polarSubscriptionId !== null) {
                $this->billingService->updateSubscription($workspace, $billing->polarSubscriptionId, $plan, $interval);

                return $this->applyAndRespond('upgrade', $workspace, $plan, ActivityType::SubscriptionUpgraded, $interval, $promotion, $actor);
            }

            return $this->initiateCheckout($workspace, $plan, $interval, $promotion, $actor);
        }

        return $this->applyAndRespond('upgrade', $workspace, $plan, ActivityType::SubscriptionUpgraded, $interval, $promotion, $actor);
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
            $billing = ResolvedBillingContext::forWorkspace($workspace);

            if ($billing->polarSubscriptionId !== null) {
                $this->billingService->updateSubscription($workspace, $billing->polarSubscriptionId, $plan, $interval);
            }
        }

        $subscription = $this->applyWorkspacePlanChange->applyActivePlan(
            $workspace,
            $plan,
            ActivityType::SubscriptionDowngraded,
            $actor,
        );

        return ChangeResponseFactory::subscription('downgrade', $subscription, $interval);
    }

    /**
     * @return array{action: string, subscription: Subscription, billing_interval: string}
     */
    private function handleCancel(Workspace $workspace, ?User $actor): array
    {
        $billing = ResolvedBillingContext::forWorkspace($workspace);

        if ($this->billingService->isConfigured() && $billing->polarSubscriptionId !== null) {
            $this->billingService->revokeSubscription($workspace, $billing->polarSubscriptionId);
        }

        $foundationPlan = Plan::query()->firstOrCreate(
            ['tier' => PlanTier::Foundation->value],
            PlanDefaults::forTier(PlanTier::Foundation)
        );

        $subscription = $this->applyWorkspacePlanChange->cancelToFoundation(
            $workspace,
            $foundationPlan,
            $billing->latestSubscription,
            $actor,
        );

        return ChangeResponseFactory::cancel($subscription);
    }

    /**
     * Initiate a Polar checkout session and record any pending promotion usage.
     *
     * @return array{action: string, checkout_url: string, promotion: array{code: string, discount: string}|null, billing_interval: string}
     */
    private function initiateCheckout(
        Workspace $workspace,
        Plan $plan,
        BillingInterval $interval,
        ?Promotion $promotion,
        ?User $actor,
    ): array {
        $checkoutUrl = $this->billingService->createCheckoutSession(
            $workspace,
            $plan,
            $interval,
            $promotion,
            $this->buildSuccessUrl(),
            $actor?->email,
        );

        $this->promotionHandler->recordPendingCheckout($workspace, $promotion, $checkoutUrl);

        return ChangeResponseFactory::checkout($checkoutUrl, $promotion, $interval);
    }

    /**
     * Apply a plan change directly and record any completed promotion usage.
     *
     * @return array{action: string, subscription: Subscription, billing_interval: string}
     */
    private function applyAndRespond(
        string $action,
        Workspace $workspace,
        Plan $plan,
        ActivityType $activityType,
        BillingInterval $interval,
        ?Promotion $promotion,
        ?User $actor,
    ): array {
        $subscription = $this->applyWorkspacePlanChange->applyActivePlan(
            $workspace,
            $plan,
            $activityType,
            $actor,
        );

        $this->promotionHandler->recordCompleted($workspace, $promotion, $subscription);

        return ChangeResponseFactory::subscription($action, $subscription, $interval);
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
