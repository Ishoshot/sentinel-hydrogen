<?php

declare(strict_types=1);

namespace App\Actions\Admin\Promotions;

use App\Models\Promotion;
use App\Services\Billing\PolarDiscountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delete a promotion with optional Polar sync.
 */
final readonly class DeletePromotion
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PolarDiscountService $polarDiscountService,
    ) {}

    /**
     * Delete a promotion.
     */
    public function handle(Promotion $promotion, bool $syncToPolar = false): void
    {
        DB::transaction(function () use ($promotion, $syncToPolar): void {
            if ($syncToPolar && $this->polarDiscountService->isConfigured()) {
                $this->syncToPolar($promotion);
            }

            $promotion->delete();
        });
    }

    /**
     * Sync the deletion to Polar.
     */
    private function syncToPolar(Promotion $promotion): void
    {
        try {
            $this->polarDiscountService->deleteDiscount($promotion);
        } catch (Throwable $throwable) {
            Log::error('Failed to sync promotion deletion to Polar', [
                'promotion_id' => $promotion->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
