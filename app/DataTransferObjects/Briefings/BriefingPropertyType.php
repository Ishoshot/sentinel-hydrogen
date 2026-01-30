<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Briefings;

/**
 * Defines the allowed property types for briefing parameter schemas.
 *
 * These types align with JSON Schema specification and are used to
 * enforce type-safe schema definitions for briefing parameters.
 */
enum BriefingPropertyType: string
{
    case String = 'string';
    case Number = 'number';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Array = 'array';
    case Object = 'object';

    /**
     * Get all available type values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
