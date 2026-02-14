<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use Github\Exception\RuntimeException;

final class GitHubRateLimitErrorInspector
{
    /**
     * IsRateLimitError.
     */
    public function isRateLimitError(RuntimeException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, 'rate limit')) {
            return true;
        }

        if (str_contains($message, 'abuse') || str_contains($message, 'secondary rate')) {
            return true;
        }

        if (str_contains($message, '403') && str_contains($message, 'limit')) {
            return true;
        }

        return str_contains($message, '429');
    }

    /**
     * ExtractResetTime.
     */
    public function extractResetTime(string $message): ?int
    {
        if (preg_match('/retry.?after[:\s]+(\d+)/i', $message, $matches) === 1) {
            return time() + (int) $matches[1];
        }

        if (preg_match('/reset[s]?.+?(\d{10,})/i', $message, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
