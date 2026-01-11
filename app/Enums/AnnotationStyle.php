<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Annotation posting style options for Sentinel configuration.
 */
enum AnnotationStyle: string
{
    case Review = 'review';
    case Comment = 'comment';
    case Check = 'check';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
