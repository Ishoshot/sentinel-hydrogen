<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Severity levels for Sentinel configuration.
 */
enum SentinelConfigSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get numeric priority (higher = more severe).
     */
    public function priority(): int
    {
        return match ($this) {
            self::Critical => 5,
            self::High => 4,
            self::Medium => 3,
            self::Low => 2,
            self::Info => 1,
        };
    }

    /**
     * Check if this severity meets or exceeds a threshold.
     */
    public function meetsThreshold(self $threshold): bool
    {
        return $this->priority() >= $threshold->priority();
    }
}
