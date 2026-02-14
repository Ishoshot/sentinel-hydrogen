<?php

declare(strict_types=1);

namespace App\Services\Billing\Support;

use App\Models\Workspace;
use App\Services\Logging\LogContext;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

final class PolarApiClient
{
    /**
     * Check if the Polar access token is configured.
     */
    public function isAccessTokenConfigured(): bool
    {
        $accessToken = config('services.polar.access_token');

        return is_string($accessToken) && $accessToken !== '';
    }

    /**
     * Create a checkout session payload at Polar.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCheckoutSession(Workspace $workspace, array $payload): array
    {
        $response = Http::withToken($this->requireAccessToken(
            $workspace,
            'Polar access token not configured',
            'Polar access token is not configured.'
        ))->post($this->baseUrl().'/v1/checkouts', $payload);

        $this->assertSuccessful($response, $workspace, 'Polar checkout session creation failed', 'Failed to create Polar checkout session');

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    /**
     * Update a Polar subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateSubscription(Workspace $workspace, string $polarSubscriptionId, array $payload): void
    {
        $response = Http::withToken($this->requireAccessToken(
            $workspace,
            'Polar access token not configured',
            'Polar access token is not configured.'
        ))->patch($this->baseUrl().'/v1/subscriptions/'.$polarSubscriptionId, $payload);

        $this->assertSuccessful($response, $workspace, 'Polar subscription update failed', 'Failed to update Polar subscription');
    }

    /**
     * Revoke (cancel) a Polar subscription.
     */
    public function revokeSubscription(Workspace $workspace, string $polarSubscriptionId): void
    {
        $response = Http::withToken($this->requireAccessToken(
            $workspace,
            'Polar access token not configured',
            'Polar access token is not configured.'
        ))->delete($this->baseUrl().'/v1/subscriptions/'.$polarSubscriptionId);

        $this->assertSuccessful($response, $workspace, 'Polar subscription revocation failed', 'Failed to revoke Polar subscription');
    }

    /**
     * Create a customer portal session.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCustomerPortalSession(Workspace $workspace, array $payload): array
    {
        $response = Http::withToken($this->requireAccessToken(
            $workspace,
            'Polar access token not configured for portal session',
            'Polar access token is not configured.'
        ))->post($this->baseUrl().'/v1/customer-sessions', $payload);

        $this->assertSuccessful($response, $workspace, 'Polar customer session creation failed', 'Failed to create Polar customer session');

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    /**
     * Resolve and validate Polar access token.
     */
    private function requireAccessToken(Workspace $workspace, string $logMessage, string $exceptionMessage): string
    {
        $accessToken = (string) config('services.polar.access_token');

        if ($accessToken === '') {
            Log::error($logMessage, LogContext::fromWorkspace($workspace));

            throw new InvalidArgumentException($exceptionMessage);
        }

        return $accessToken;
    }

    /**
     * Resolve Polar base API URL.
     */
    private function baseUrl(): string
    {
        return (string) config('services.polar.api_url', 'https://api.polar.sh');
    }

    /**
     * Assert response success for Polar API calls.
     */
    private function assertSuccessful(Response $response, Workspace $workspace, string $logMessage, string $exceptionPrefix): void
    {
        if (! $response->successful()) {
            Log::error($logMessage, LogContext::merge(
                LogContext::fromWorkspace($workspace),
                ['response_status' => $response->status(), 'response_body' => $response->body()]
            ));

            throw new RuntimeException(
                sprintf('%s: %s', $exceptionPrefix, $response->body())
            );
        }
    }
}
