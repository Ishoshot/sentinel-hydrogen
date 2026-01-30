<?php

declare(strict_types=1);

namespace App\Actions\Admin\Promotions;

use App\Enums\Promotions\PromotionValueType;
use App\Models\Promotion;
use App\Services\Billing\PolarDiscountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Update an existing promotion with optional Polar sync.
 */
final readonly class UpdatePromotion
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PolarDiscountService $polarDiscountService,
    ) {}

    /**
     * Update a promotion.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Promotion $promotion, array $data, bool $syncToPolar = false): Promotion
    {
        return DB::transaction(function () use ($promotion, $data, $syncToPolar): Promotion {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (array_key_exists('description', $data)) {
                $updateData['description'] = $data['description'];
            }

            if (isset($data['code'])) {
                $updateData['code'] = mb_strtoupper((string) $data['code']);
            }

            if (isset($data['value_type'])) {
                $updateData['value_type'] = $data['value_type'] instanceof PromotionValueType
                    ? $data['value_type']->value
                    : $data['value_type'];
            }

            if (isset($data['value_amount'])) {
                $updateData['value_amount'] = $data['value_amount'];
            }

            if (array_key_exists('valid_from', $data)) {
                $updateData['valid_from'] = $data['valid_from'];
            }

            if (array_key_exists('valid_to', $data)) {
                $updateData['valid_to'] = $data['valid_to'];
            }

            if (array_key_exists('max_uses', $data)) {
                $updateData['max_uses'] = $data['max_uses'];
            }

            if (isset($data['is_active'])) {
                $updateData['is_active'] = $data['is_active'];
            }

            $promotion->update($updateData);

            if ($syncToPolar && $this->polarDiscountService->isConfigured()) {
                $this->syncToPolar($promotion);
            }

            return $promotion->refresh();
        });
    }

    /**
     * Sync the promotion update to Polar.
     */
    private function syncToPolar(Promotion $promotion): void
    {
        try {
            if ($promotion->polar_discount_id !== null) {
                $this->polarDiscountService->updateDiscount($promotion);
            }
        } catch (Throwable $throwable) {
            Log::error('Failed to sync promotion update to Polar', [
                'promotion_id' => $promotion->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
