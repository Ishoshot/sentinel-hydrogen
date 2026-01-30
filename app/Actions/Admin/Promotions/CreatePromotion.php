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
 * Create a new promotion with optional Polar sync.
 */
final readonly class CreatePromotion
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PolarDiscountService $polarDiscountService,
    ) {}

    /**
     * Create a new promotion.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, bool $syncToPolar = false): Promotion
    {
        return DB::transaction(function () use ($data, $syncToPolar): Promotion {
            $promotion = Promotion::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'code' => mb_strtoupper((string) $data['code']),
                'value_type' => $data['value_type'] instanceof PromotionValueType
                    ? $data['value_type']->value
                    : $data['value_type'],
                'value_amount' => $data['value_amount'],
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'max_uses' => $data['max_uses'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'times_used' => 0,
            ]);

            if ($syncToPolar && $this->polarDiscountService->isConfigured()) {
                $this->syncToPolar($promotion);
            }

            return $promotion;
        });
    }

    /**
     * Sync the promotion to Polar.
     */
    private function syncToPolar(Promotion $promotion): void
    {
        try {
            $response = $this->polarDiscountService->createDiscount($promotion);

            $promotion->forceFill([
                'polar_discount_id' => $response['id'] ?? null,
            ])->save();
        } catch (Throwable $throwable) {
            Log::error('Failed to sync promotion to Polar', [
                'promotion_id' => $promotion->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
