<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Support;

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\Contracts\PolarBillingServiceContract;

/**
 * Initiates a Polar checkout session and records any pending promotion usage.
 */
final readonly class CheckoutInitiator
{
    public function __construct(
        private PolarBillingServiceContract $billingService,
        private PromotionHandler $promotionHandler,
    ) {}

    /**
     * Create a checkout session and record pending promotion.
     *
     * @return array{action: string, checkout_url: string, promotion: array{code: string, discount: string}|null, billing_interval: string}
     */
    public function initiate(
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
     * Build the checkout success redirect URL template.
     */
    private function buildSuccessUrl(): string
    {
        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return $frontendUrl.'/billing/success?checkout_id={CHECKOUT_ID}';
    }
}
