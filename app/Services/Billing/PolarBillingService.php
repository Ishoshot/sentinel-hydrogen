<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Workspace;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use StandardWebhooks\Exception\WebhookVerificationException;
use StandardWebhooks\Webhook;

/**
 * Service for integrating with Polar billing.
 */
final class PolarBillingService
{
    /**
     * Check if Polar billing is properly configured.
     */
    public function isConfigured(): bool
    {
        $productIds = config('services.polar.product_ids', []);
        $accessToken = config('services.polar.access_token');

        $hasProductIds = is_array($productIds)
            && (
                $this->hasValidIds($productIds['monthly'] ?? [])
                || $this->hasValidIds($productIds['yearly'] ?? [])
            );

        return $hasProductIds && is_string($accessToken) && $accessToken !== '';
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

        $accessToken = (string) config('services.polar.access_token');

        if ($accessToken === '') {
            Log::error('Polar access token not configured', LogContext::fromWorkspace($workspace));

            throw new InvalidArgumentException('Polar access token is not configured.');
        }

        $productId = $this->getProductId($plan->tier, $interval);

        if ($productId === null) {
            Log::error('Polar product ID not configured', LogContext::merge(
                LogContext::fromWorkspace($workspace),
                ['plan_tier' => $plan->tier, 'interval' => $interval->value]
            ));

            throw new InvalidArgumentException(
                sprintf('Polar product ID is not configured for %s %s plan.', $interval->value, $plan->tier)
            );
        }

        $baseUrl = (string) config('services.polar.api_url', 'https://api.polar.sh');

        $metadata = [
            'workspace_id' => (string) $workspace->id,
            'plan_tier' => $plan->tier,
            'billing_interval' => $interval->value,
        ];

        if ($promotion?->id !== null) {
            $metadata['promotion_id'] = (string) $promotion->id;
        }

        $payload = [
            'products' => [$productId],
            'metadata' => $metadata,
            'allow_discount_codes' => true,
        ];

        if ($successUrl !== null && $successUrl !== '') {
            $payload['success_url'] = $successUrl;
        }

        if ($promotion instanceof Promotion && $promotion->isValid() && $promotion->polar_discount_id !== null) {
            $payload['discount_id'] = $promotion->polar_discount_id;
        }

        if ($customerEmail !== null && $customerEmail !== '') {
            $payload['customer_email'] = $customerEmail;
        }

        $response = Http::withToken($accessToken)
            ->post($baseUrl.'/v1/checkouts', $payload);

        if (! $response->successful()) {
            Log::error('Polar checkout session creation failed', LogContext::merge(
                LogContext::fromWorkspace($workspace),
                ['response_status' => $response->status(), 'response_body' => $response->body()]
            ));

            throw new RuntimeException(
                sprintf('Failed to create Polar checkout session: %s', $response->body())
            );
        }

        /** @var array{url?: string} $data */
        $data = $response->json();
        $checkoutUrl = $data['url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            Log::error('Polar API returned no checkout URL', LogContext::fromWorkspace($workspace));

            throw new RuntimeException('Polar API did not return a checkout URL.');
        }

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

        $accessToken = (string) config('services.polar.access_token');

        if ($accessToken === '') {
            Log::error('Polar access token not configured', LogContext::fromWorkspace($workspace));

            throw new InvalidArgumentException('Polar access token is not configured.');
        }

        $productId = $this->getProductId($plan->tier, $interval);

        if ($productId === null) {
            Log::error('Polar product ID not configured', LogContext::merge(
                LogContext::fromWorkspace($workspace),
                ['plan_tier' => $plan->tier, 'interval' => $interval->value]
            ));

            throw new InvalidArgumentException(
                sprintf('Polar product ID is not configured for %s %s plan.', $interval->value, $plan->tier)
            );
        }

        $baseUrl = (string) config('services.polar.api_url', 'https://api.polar.sh');

        $response = Http::withToken($accessToken)
            ->patch($baseUrl.'/v1/subscriptions/'.$polarSubscriptionId, [
                'product_id' => $productId,
                'proration_behavior' => 'invoice',
            ]);

        if (! $response->successful()) {
            Log::error('Polar subscription update failed', LogContext::merge(
                LogContext::fromWorkspace($workspace),
                ['response_status' => $response->status(), 'response_body' => $response->body()]
            ));

            throw new RuntimeException(
                sprintf('Failed to update Polar subscription: %s', $response->body())
            );
        }

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
        $accessToken = (string) config('services.polar.access_token');

        if ($accessToken === '') {
            Log::error('Polar access token not configured', LogContext::fromWorkspace($workspace));

            throw new InvalidArgumentException('Polar access token is not configured.');
        }

        $baseUrl = (string) config('services.polar.api_url', 'https://api.polar.sh');

        $response = Http::withToken($accessToken)
            ->delete($baseUrl.'/v1/subscriptions/'.$polarSubscriptionId);

        if (! $response->successful()) {
            Log::error('Polar subscription revocation failed', LogContext::merge(
                LogContext::fromWorkspace($workspace),
                ['response_status' => $response->status(), 'response_body' => $response->body()]
            ));

            throw new RuntimeException(
                sprintf('Failed to revoke Polar subscription: %s', $response->body())
            );
        }

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
        $accessToken = (string) config('services.polar.access_token');

        if ($accessToken === '') {
            Log::error('Polar access token not configured for portal session', LogContext::fromWorkspace($workspace));

            throw new InvalidArgumentException('Polar access token is not configured.');
        }

        $subscription = $workspace->subscriptions()->latest()->first();
        $customerId = $subscription?->polar_customer_id;

        if ($customerId === null || $customerId === '') {
            Log::warning('Workspace missing Polar customer ID', LogContext::fromWorkspace($workspace));

            throw new InvalidArgumentException('Workspace does not have a Polar customer ID.');
        }

        $baseUrl = (string) config('services.polar.api_url', 'https://api.polar.sh');
        $payload = ['customer_id' => $customerId];

        if ($returnUrl !== null && $returnUrl !== '') {
            $payload['return_url'] = $returnUrl;
        }

        $response = Http::withToken($accessToken)
            ->post($baseUrl.'/v1/customer-sessions', $payload);

        if (! $response->successful()) {
            Log::error('Polar customer session creation failed', LogContext::merge(
                LogContext::fromWorkspace($workspace),
                ['response_status' => $response->status(), 'response_body' => $response->body()]
            ));

            throw new RuntimeException(
                sprintf('Failed to create Polar customer session: %s', $response->body())
            );
        }

        /** @var array{customer_portal_url?: string} $data */
        $data = $response->json();
        $portalUrl = $data['customer_portal_url'] ?? null;

        if (! is_string($portalUrl) || $portalUrl === '') {
            Log::error('Polar API returned no portal URL', LogContext::fromWorkspace($workspace));

            throw new RuntimeException('Polar API did not return a customer portal URL.');
        }

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
        $secret = (string) config('services.polar.webhook_secret');

        if ($secret === '') {
            Log::error('Polar webhook secret not configured');

            throw new RuntimeException('Polar webhook secret is not configured.');
        }

        // StandardWebhooks requires the secret to be base64-encoded with "whsec_" prefix
        // If the secret doesn't have the prefix, add it (Polar provides raw secrets)
        $signingSecret = str_starts_with($secret, 'whsec_')
            ? $secret
            : 'whsec_'.base64_encode($secret);

        try {
            $webhook = new Webhook($signingSecret);
            $webhook->verify($payload, $headers);
        } catch (WebhookVerificationException $webhookVerificationException) {
            Log::warning('Invalid Polar webhook signature received', [
                'error' => $webhookVerificationException->getMessage(),
            ]);

            throw new RuntimeException('Invalid Polar webhook signature.', $webhookVerificationException->getCode(), $webhookVerificationException);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            Log::warning('Invalid Polar webhook payload format');

            throw new RuntimeException('Invalid Polar webhook payload.');
        }

        /** @var array<string, mixed> $event */
        return VerifiedPolarWebhook::fromArray($event);
    }

    /**
     * Get the Polar product ID for a given tier and billing interval.
     */
    private function getProductId(string $tier, BillingInterval $interval): ?string
    {
        $productIds = config('services.polar.product_ids', []);

        if (! is_array($productIds)) {
            return null;
        }

        $intervalIds = $productIds[$interval->value] ?? [];

        if (! is_array($intervalIds)) {
            return null;
        }

        $productId = $intervalIds[$tier] ?? null;

        return is_string($productId) && $productId !== '' ? $productId : null;
    }

    /**
     * Check if an array has at least one valid string ID.
     */
    private function hasValidIds(mixed $ids): bool
    {
        if (! is_array($ids)) {
            return false;
        }

        return array_filter($ids, fn (mixed $id): bool => is_string($id) && $id !== '') !== [];
    }
}
