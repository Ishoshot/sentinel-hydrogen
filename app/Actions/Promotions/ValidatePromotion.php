<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Models\Promotion;

/**
 * Validates a promotion code and returns the promotion if valid.
 */
final readonly class ValidatePromotion
{
    /**
     * Validate a promotion code.
     *
     * @return array{valid: bool, promotion: Promotion|null, message: string|null}
     */
    public function handle(string $code): array
    {
        $promotion = Promotion::query()
            ->where('code', mb_strtoupper(mb_trim($code)))
            ->first();

        if ($promotion === null) {
            return [
                'valid' => false,
                'promotion' => null,
                'message' => 'Invalid promotion code.',
            ];
        }

        if (! $promotion->isValid()) {
            if (! $promotion->is_active) {
                return [
                    'valid' => false,
                    'promotion' => null,
                    'message' => 'This promotion is no longer active.',
                ];
            }

            if ($promotion->valid_to !== null && now()->isAfter($promotion->valid_to)) {
                return [
                    'valid' => false,
                    'promotion' => null,
                    'message' => 'This promotion has expired.',
                ];
            }

            if ($promotion->valid_from !== null && now()->isBefore($promotion->valid_from)) {
                return [
                    'valid' => false,
                    'promotion' => null,
                    'message' => 'This promotion is not yet active.',
                ];
            }

            if ($promotion->max_uses !== null && $promotion->times_used >= $promotion->max_uses) {
                return [
                    'valid' => false,
                    'promotion' => null,
                    'message' => 'This promotion has reached its usage limit.',
                ];
            }

            return [
                'valid' => false,
                'promotion' => null,
                'message' => 'This promotion is not valid.',
            ];
        }

        if ($promotion->polar_discount_id === null) {
            return [
                'valid' => false,
                'promotion' => null,
                'message' => 'This promotion code is not activated.',
            ];
        }

        return [
            'valid' => true,
            'promotion' => $promotion,
            'message' => null,
        ];
    }
}
