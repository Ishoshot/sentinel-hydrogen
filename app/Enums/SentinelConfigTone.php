<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Review tone options for Sentinel configuration.
 */
enum SentinelConfigTone: string
{
    case Constructive = 'constructive';
    case Direct = 'direct';
    case Educational = 'educational';
    case Minimal = 'minimal';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
