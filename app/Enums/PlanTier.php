<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanTier: string
{
    case Foundation = 'foundation';
    case Illuminate = 'illuminate';
    case Orchestrate = 'orchestrate';
    case Sanctum = 'sanctum';

    /**
     * Get all plan tier values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the numeric rank of this tier for comparison.
     *
     * Higher rank means higher tier.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Foundation => 1,
            self::Illuminate => 2,
            self::Orchestrate => 3,
            self::Sanctum => 4,
        };
    }

    /**
     * Check if this is a free tier.
     */
    public function isFree(): bool
    {
        return $this === self::Foundation;
    }
}
