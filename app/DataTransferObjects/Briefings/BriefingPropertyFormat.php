<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Briefings;

/**
 * Defines the allowed format values for string properties in briefing schemas.
 *
 * These formats provide additional validation hints for string properties,
 * aligning with JSON Schema format specifications.
 */
enum BriefingPropertyFormat: string
{
    case Date = 'date';
    case DateTime = 'date-time';
    case Email = 'email';
    case Uri = 'uri';
    case Url = 'url';

    /**
     * Get all available format values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
