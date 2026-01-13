<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

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
    ): string {
        $accessToken = (string) config('services.polar.access_token');

        if ($accessToken === '') {
            throw new InvalidArgumentException('Polar access token is not configured.');
        }

        $productId = $this->getProductId($plan->tier, $interval);

        if ($productId === null) {
            throw new InvalidArgumentException(
                sprintf('Polar product ID is not configured for %s %s plan.', $interval->value, $plan->tier)
            );
        }

        $baseUrl = (string) config('services.polar.api_url', 'https://api.polar.sh');

        $payload = [
            'products' => [$productId],
            'metadata' => [
                'workspace_id' => (string) $workspace->id,
                'plan_tier' => $plan->tier,
                'billing_interval' => $interval->value,
            ],
            'allow_discount_codes' => true,
        ];

        if ($successUrl !== null && $successUrl !== '') {
            $payload['success_url'] = $successUrl;
        }

        if ($promotion instanceof Promotion && $promotion->isValid()) {
            $payload['discount_id'] = $promotion->polar_discount_id ?? null;
        }

        $response = Http::withToken($accessToken)
            ->post($baseUrl.'/v1/checkouts', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('Failed to create Polar checkout session: %s', $response->body())
            );
        }

        /** @var array{url?: string} $data */
        $data = $response->json();
        $checkoutUrl = $data['url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            throw new RuntimeException('Polar API did not return a checkout URL.');
        }

        return $checkoutUrl;
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
            throw new InvalidArgumentException('Polar access token is not configured.');
        }

        $subscription = $workspace->subscriptions()->latest()->first();
        $customerId = $subscription?->polar_customer_id;

        if ($customerId === null || $customerId === '') {
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
            throw new RuntimeException(
                sprintf('Failed to create Polar customer session: %s', $response->body())
            );
        }

        /** @var array{customer_portal_url?: string} $data */
        $data = $response->json();
        $portalUrl = $data['customer_portal_url'] ?? null;

        if (! is_string($portalUrl) || $portalUrl === '') {
            throw new RuntimeException('Polar API did not return a customer portal URL.');
        }

        return $portalUrl;
    }

    /**
     * Verify a Polar webhook signature and parse the payload.
     *
     * @return array<string, mixed>
     */
    public function verifyWebhook(string $payload, string $signature): array
    {
        $secret = (string) config('services.polar.webhook_secret');

        if ($secret === '') {
            throw new RuntimeException('Polar webhook secret is not configured.');
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid Polar webhook signature.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Invalid Polar webhook payload.');
        }

        return $event;
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
