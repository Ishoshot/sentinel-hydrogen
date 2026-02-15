<?php

declare(strict_types=1);

namespace App\Services\Billing\Factories;

use App\Enums\Promotions\PromotionValueType;
use App\Models\Promotion;
use DateTimeInterface;

/**
 * Builds payloads for Polar discount create and update API calls.
 */
final readonly class PolarDiscountPayloadFactory
{
    /**
     * Build the payload for creating a discount on Polar.
     *
     * @return array<string, mixed>
     */
    public function buildCreatePayload(Promotion $promotion): array
    {
        /** @var PromotionValueType $valueType */
        $valueType = $promotion->value_type;

        return [
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
        ];
    }

    /**
     * Build the payload for updating a discount on Polar.
     *
     * @return array<string, mixed>
     */
    public function buildUpdatePayload(Promotion $promotion): array
    {
        return [
            'name' => $promotion->name,
            'code' => $promotion->code,
            'max_redemptions' => $promotion->max_uses,
            'starts_at' => $promotion->valid_from instanceof DateTimeInterface ? $promotion->valid_from->format('c') : null,
            'ends_at' => $promotion->valid_to instanceof DateTimeInterface ? $promotion->valid_to->format('c') : null,
        ];
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
