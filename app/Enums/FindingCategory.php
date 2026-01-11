<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Categories for code review findings.
 */
enum FindingCategory: string
{
    case Security = 'security';
    case Correctness = 'correctness';
    case Reliability = 'reliability';
    case Performance = 'performance';
    case Maintainability = 'maintainability';
    case Testing = 'testing';
    case Style = 'style';
    case Documentation = 'documentation';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
