<?php

declare(strict_types=1);

namespace App\Services\Billing\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generic HTTP client for Polar discount API operations with auth and error handling.
 */
final readonly class PolarDiscountApiClient
{
    /**
     * Make an authenticated request to the Polar API.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public function request(string $method, string $endpoint, ?array $data = null): array
    {
        $accessToken = (string) config('services.polar.access_token');

        if ($accessToken === '') {
            throw new RuntimeException('Polar access token is not configured.');
        }

        $baseUrl = (string) config('services.polar.api_url', 'https://api.polar.sh');

        $request = Http::withToken($accessToken);

        $response = match ($method) {
            'GET' => $request->get($baseUrl.$endpoint),
            'POST' => $request->post($baseUrl.$endpoint, $data ?? []),
            'PATCH' => $request->patch($baseUrl.$endpoint, $data ?? []),
            'DELETE' => $request->delete($baseUrl.$endpoint),
            default => throw new RuntimeException('Unsupported HTTP method: '.$method),
        };

        if (! $response->successful()) {
            Log::error('Polar API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                sprintf('Polar API request failed: %s', $response->body())
            );
        }

        if ($method === 'DELETE') {
            return [];
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }
}
