<?php

declare(strict_types=1);

namespace App\Services\Promotions;

use App\Models\Promotion;
use App\Services\Promotions\Contracts\PromotionValidatorContract;
use App\Services\Promotions\ValueObjects\PromotionValidationResult;

/**
 * Validates promotion codes and returns validation results.
 */
final readonly class PromotionValidator implements PromotionValidatorContract
{
    /**
     * Validate a promotion code.
     */
    public function validate(string $code): PromotionValidationResult
    {
        $promotion = Promotion::query()
            ->where('code', mb_strtoupper(mb_trim($code)))
            ->first();

        if ($promotion === null) {
            return PromotionValidationResult::failure('Invalid promotion code.');
        }

        if (! $promotion->isValid()) {
            return $this->getInvalidReason($promotion);
        }

        if ($promotion->polar_discount_id === null) {
            return PromotionValidationResult::failure('This promotion code is not activated.');
        }

        return PromotionValidationResult::success($promotion);
    }

    /**
     * Determine the specific reason a promotion is invalid.
     */
    private function getInvalidReason(Promotion $promotion): PromotionValidationResult
    {
        if (! $promotion->is_active) {
            return PromotionValidationResult::failure('This promotion is no longer active.');
        }

        if ($promotion->valid_to !== null && now()->isAfter($promotion->valid_to)) {
            return PromotionValidationResult::failure('This promotion has expired.');
        }

        if ($promotion->valid_from !== null && now()->isBefore($promotion->valid_from)) {
            return PromotionValidationResult::failure('This promotion is not yet active.');
        }

        if ($promotion->max_uses !== null && $promotion->times_used >= $promotion->max_uses) {
            return PromotionValidationResult::failure('This promotion has reached its usage limit.');
        }

        return PromotionValidationResult::failure('This promotion is not valid.');
    }
}
