<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Promotion;
use App\Services\Billing\Builders\PolarDiscountPayloadBuilder;
use App\Services\Billing\Support\PolarDiscountApiClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for managing discounts on Polar.
 */
final readonly class PolarDiscountService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private PolarDiscountApiClient $apiClient = new PolarDiscountApiClient,
        private PolarDiscountPayloadBuilder $payloadBuilder = new PolarDiscountPayloadBuilder,
    ) {}

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
        $response = $this->apiClient->request(
            'POST',
            '/v1/discounts',
            $this->payloadBuilder->buildCreatePayload($promotion),
        );

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

        $response = $this->apiClient->request(
            'PATCH',
            '/v1/discounts/'.$promotion->polar_discount_id,
            $this->payloadBuilder->buildUpdatePayload($promotion),
        );

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

        $this->apiClient->request('DELETE', '/v1/discounts/'.$promotion->polar_discount_id);

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
        return $this->apiClient->request('GET', '/v1/discounts/'.$discountId);
    }

    /**
     * List all discounts from Polar.
     *
     * @return array<string, mixed>
     */
    public function listDiscounts(): array
    {
        return $this->apiClient->request('GET', '/v1/discounts');
    }
}
