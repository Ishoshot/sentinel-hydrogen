<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PromotionValueType;
use App\Models\Promotion;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for managing discounts on Polar.
 */
final class PolarDiscountService
{
    /**
     * Check if Polar is configured for discount management.
     */
    public function isConfigured(): bool
    {
        $accessToken = config('services.polar.access_token');

        return is_string($accessToken) && $accessToken !== '';
    }

    /**
     * Create a discount on Polar.
     *
     * @return array<string, mixed>
     */
    public function createDiscount(Promotion $promotion): array
    {
        /** @var PromotionValueType $valueType */
        $valueType = $promotion->value_type;

        $response = $this->request('POST', '/v1/discounts', [
            'name' => $promotion->name,
            'code' => $promotion->code,
            'type' => $this->mapValueType($valueType),
            'amount' => $valueType === PromotionValueType::Percentage
                ? (int) $promotion->value_amount
                : null,
            'basis_points' => $valueType === PromotionValueType::Percentage
                ? (int) $promotion->value_amount * 100
                : null,
            'fixed_amount' => $valueType === PromotionValueType::Flat
                ? $promotion->getValueAmountInCents()
                : null,
            'duration' => 'once',
            'max_redemptions' => $promotion->max_uses,
            'starts_at' => $promotion->valid_from instanceof DateTimeInterface ? $promotion->valid_from->format('c') : null,
            'ends_at' => $promotion->valid_to instanceof DateTimeInterface ? $promotion->valid_to->format('c') : null,
        ]);

        Log::info('Polar discount created', [
            'promotion_id' => $promotion->id,
            'polar_discount_id' => $response['id'] ?? null,
        ]);

        return $response;
    }

    /**
     * Update a discount on Polar.
     *
     * @return array<string, mixed>
     */
    public function updateDiscount(Promotion $promotion): array
    {
        if ($promotion->polar_discount_id === null) {
            throw new RuntimeException('Promotion does not have a Polar discount ID.');
        }

        $response = $this->request('PATCH', '/v1/discounts/'.$promotion->polar_discount_id, [
            'name' => $promotion->name,
            'code' => $promotion->code,
            'max_redemptions' => $promotion->max_uses,
            'starts_at' => $promotion->valid_from instanceof DateTimeInterface ? $promotion->valid_from->format('c') : null,
            'ends_at' => $promotion->valid_to instanceof DateTimeInterface ? $promotion->valid_to->format('c') : null,
        ]);

        Log::info('Polar discount updated', [
            'promotion_id' => $promotion->id,
            'polar_discount_id' => $promotion->polar_discount_id,
        ]);

        return $response;
    }

    /**
     * Delete a discount on Polar.
     */
    public function deleteDiscount(Promotion $promotion): void
    {
        if ($promotion->polar_discount_id === null) {
            return;
        }

        $this->request('DELETE', '/v1/discounts/'.$promotion->polar_discount_id);

        Log::info('Polar discount deleted', [
            'promotion_id' => $promotion->id,
            'polar_discount_id' => $promotion->polar_discount_id,
        ]);
    }

    /**
     * Get a discount from Polar.
     *
     * @return array<string, mixed>
     */
    public function getDiscount(string $discountId): array
    {
        return $this->request('GET', '/v1/discounts/'.$discountId);
    }

    /**
     * List all discounts from Polar.
     *
     * @return array<string, mixed>
     */
    public function listDiscounts(): array
    {
        return $this->request('GET', '/v1/discounts');
    }

    /**
     * Make a request to the Polar API.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
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

    /**
     * Map internal value type to Polar discount type.
     */
    private function mapValueType(PromotionValueType $valueType): string
    {
        return match ($valueType) {
            PromotionValueType::Percentage => 'percentage',
            PromotionValueType::Flat => 'fixed',
        };
    }
}
