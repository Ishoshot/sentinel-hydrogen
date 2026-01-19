<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\PromotionValueType;
use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Context-aware cast for Promotion value_amount.
 *
 * For flat/money types:
 *   - Database stores cents (1000 = $10.00)
 *   - Application receives/sends dollars (10.00)
 *
 * For percentage types:
 *   - Database stores percentage as-is (20 = 20%)
 *   - Application receives/sends percentage as-is (20)
 */
final class PromotionValueCast implements CastsAttributes
{
    private const string DEFAULT_CURRENCY = 'USD';

    /**
     * Cast the given value from the database.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $valueType = $this->resolveValueType($attributes);

        if ($valueType === PromotionValueType::Flat) {
            /** @var int|string $intValue */
            $intValue = $value;

            return Money::ofMinor((int) $intValue, self::DEFAULT_CURRENCY)
                ->getAmount()
                ->toFloat();
        }

        /** @var int|string $intValue */
        $intValue = $value;

        return (int) $intValue;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Invalid value for %s: must be numeric.', $key));
        }

        $valueType = $this->resolveValueType($attributes);

        if ($valueType === PromotionValueType::Flat) {
            return Money::of($value, self::DEFAULT_CURRENCY)
                ->getMinorAmount()
                ->toInt();
        }

        return (int) $value;
    }

    /**
     * Resolve the value type from model attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveValueType(array $attributes): PromotionValueType
    {
        $type = $attributes['value_type'] ?? null;

        if ($type instanceof PromotionValueType) {
            return $type;
        }

        if (is_string($type)) {
            return PromotionValueType::tryFrom($type) ?? PromotionValueType::Percentage;
        }

        return PromotionValueType::Percentage;
    }
}
