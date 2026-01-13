<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types of promotion discount values.
 */
enum PromotionValueType: string
{
    case Flat = 'flat';
    case Percentage = 'percentage';

    /**
     * Get all promotion value type values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label for the value type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat Amount',
            self::Percentage => 'Percentage',
        };
    }
}
