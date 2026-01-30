<?php

declare(strict_types=1);

namespace App\Enums\Reviews;

/**
 * Verdict options for code reviews.
 */
enum ReviewVerdict: string
{
    case Approve = 'approve';
    case RequestChanges = 'request_changes';
    case Comment = 'comment';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
