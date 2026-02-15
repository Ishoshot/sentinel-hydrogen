<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Workspace;
use App\Services\Billing\Clients\PolarApiClient;
use App\Services\Billing\Clients\PolarWebhookVerificationClient;
use App\Services\Billing\Contracts\PolarBillingServiceContract;
use App\Services\Billing\Factories\CheckoutPayloadFactory;
use App\Services\Billing\Factories\CustomerPortalPayloadFactory;
use App\Services\Billing\Policies\PolarProductIdPolicy;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for integrating with Polar billing.
 */
final readonly class PolarBillingService implements PolarBillingServiceContract
{
    /**
     * Create a new Polar billing service instance.
     */
    public function __construct(
        private PolarApiClient $apiClient,
        private PolarWebhookVerificationClient $webhookVerifier,
        private PolarProductIdPolicy $productPolicy,
        private CheckoutPayloadFactory $checkoutPayloadBuilder,
        private CustomerPortalPayloadFactory $customerPortalPayloadBuilder,
    ) {}

    /**
     * Check if Polar billing is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->productPolicy->hasConfiguredProducts() && $this->apiClient->isAccessTokenConfigured();
    }

    /**
     * Create a Polar checkout session via API.
     *
     * This calls the Polar API to create a checkout session and returns the URL
     * where the customer should be redirected to complete payment.
     */
    public function createCheckoutSession(
        Workspace $workspace,
        Plan $plan,
        BillingInterval $interval = BillingInterval::Monthly,
        ?Promotion $promotion = null,
        ?string $successUrl = null,
        ?string $customerEmail = null,
    ): string {

        $productId = $this->productPolicy->resolve($workspace, $plan, $interval);

        $payload = $this->checkoutPayloadBuilder->build(
            $productId,
            $workspace,
            $plan,
            $interval,
            $promotion,
            $successUrl,
            $customerEmail,
        );

        /** @var array{url?: string} $data */
        $data = $this->apiClient->createCheckoutSession($workspace, $payload);
        $checkoutUrl = $this->resolveCheckoutUrl($workspace, $data);

        Log::info('Polar checkout session created', LogContext::merge(
            LogContext::fromWorkspace($workspace),
            ['plan_tier' => $plan->tier, 'interval' => $interval->value]
        ));

        return $checkoutUrl;
    }

    /**
     * Update an existing Polar subscription to a different product.
     *
     * Calls PATCH /v1/subscriptions/{id} with the new product ID.
     */
    public function updateSubscription(
        Workspace $workspace,
        string $polarSubscriptionId,
        Plan $plan,
        BillingInterval $interval = BillingInterval::Monthly,
    ): void {

        $productId = $this->productPolicy->resolve($workspace, $plan, $interval);

        $this->apiClient->updateSubscription($workspace, $polarSubscriptionId, [
            'product_id' => $productId,
            'proration_behavior' => 'invoice',
        ]);

        Log::info('Polar subscription updated', LogContext::merge(
            LogContext::fromWorkspace($workspace),
            ['plan_tier' => $plan->tier, 'interval' => $interval->value, 'polar_subscription_id' => $polarSubscriptionId]
        ));
    }

    /**
     * Revoke (cancel) an existing Polar subscription.
     *
     * Calls DELETE /v1/subscriptions/{id}.
     */
    public function revokeSubscription(Workspace $workspace, string $polarSubscriptionId): void
    {
        $this->apiClient->revokeSubscription($workspace, $polarSubscriptionId);

        Log::info('Polar subscription revoked', LogContext::merge(
            LogContext::fromWorkspace($workspace),
            ['polar_subscription_id' => $polarSubscriptionId]
        ));
    }

    /**
     * Create an authenticated Polar customer portal session.
     *
     * This calls the Polar API to generate a pre-authenticated portal URL
     * that allows the customer to manage their subscription.
     */
    public function createCustomerPortalSession(Workspace $workspace, ?string $returnUrl = null): string
    {
        $payload = $this->customerPortalPayloadBuilder->build($workspace, $returnUrl);

        /** @var array{customer_portal_url?: string} $data */
        $data = $this->apiClient->createCustomerPortalSession($workspace, $payload);
        $portalUrl = $this->resolveCustomerPortalUrl($workspace, $data);

        Log::info('Polar customer portal session created', LogContext::fromWorkspace($workspace));

        return $portalUrl;
    }

    /**
     * Verify a Polar webhook signature and parse the payload.
     *
     * Polar uses StandardWebhooks format which requires three headers:
     * - webhook-id: unique identifier for the webhook
     * - webhook-signature: the signature to verify
     * - webhook-timestamp: Unix timestamp when the webhook was sent
     *
     * @param  array{webhook-id: string, webhook-signature: string, webhook-timestamp: string}  $headers
     */
    public function verifyWebhook(string $payload, array $headers): VerifiedPolarWebhook
    {
        return $this->webhookVerifier->verify($payload, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCheckoutUrl(Workspace $workspace, array $data): string
    {
        $checkoutUrl = $data['url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            Log::error('Polar API returned no checkout URL', LogContext::fromWorkspace($workspace));

            throw new RuntimeException('Polar API did not return a checkout URL.');
        }

        return $checkoutUrl;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomerPortalUrl(Workspace $workspace, array $data): string
    {
        $portalUrl = $data['customer_portal_url'] ?? null;

        if (! is_string($portalUrl) || $portalUrl === '') {
            Log::error('Polar API returned no portal URL', LogContext::fromWorkspace($workspace));

            throw new RuntimeException('Polar API did not return a customer portal URL.');
        }

        return $portalUrl;
    }
}
