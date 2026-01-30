<?php

declare(strict_types=1);

namespace App\Services\Commands\Tools;

/**
 * Shared formatting helpers for command tool outputs.
 */
final readonly class ToolResultFormatter
{
    /**
     * Truncate a string for display.
     */
    public function truncate(string $content, int $limit): string
    {
        if (mb_strlen($content) <= $limit) {
            return $content;
        }

        return mb_substr($content, 0, max(0, $limit - 3)).'...';
    }
}
