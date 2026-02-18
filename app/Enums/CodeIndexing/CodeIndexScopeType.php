<?php

declare(strict_types=1);

namespace App\Enums\CodeIndexing;

enum CodeIndexScopeType: string
{
    case Baseline = 'baseline';
    case PullRequest = 'pull_request';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
