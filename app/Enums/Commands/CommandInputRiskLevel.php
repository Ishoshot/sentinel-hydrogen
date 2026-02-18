<?php

declare(strict_types=1);

namespace App\Enums\Commands;

enum CommandInputRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Pick the higher risk level between two values.
     */
    public static function max(self $left, self $right): self
    {
        return $left->score() >= $right->score() ? $left : $right;
    }

    /**
     * Resolve an ordinal score for merge operations.
     */
    public function score(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
