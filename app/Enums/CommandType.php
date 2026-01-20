<?php

declare(strict_types=1);

namespace App\Enums;

enum CommandType: string
{
    case Explain = 'explain';
    case Analyze = 'analyze';
    case Review = 'review';
    case Summarize = 'summarize';
    case Find = 'find';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable description of the command type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Explain => 'Explain code, concept, or column',
            self::Analyze => 'Deep analysis of code section',
            self::Review => 'Review specific file or changes',
            self::Summarize => 'Summarize PR or changes',
            self::Find => 'Find usages or references',
        };
    }
}
