<?php

declare(strict_types=1);

namespace App\Services\GitHub\Strategies;

final class GitHubRateLimitBackoffStrategy
{
    private const int BASE_DELAY_SECONDS = 1;

    private const int MAX_DELAY_SECONDS = 60;

    /**
     * Calculate the backoff delay for the given attempt.
     */
    public function calculate(int $attempt): float
    {
        return min(
            self::BASE_DELAY_SECONDS * (2 ** ($attempt - 1)) + random_int(0, 1000) / 1000,
            self::MAX_DELAY_SECONDS
        );
    }

    /**
     * Get the maximum allowed delay in seconds.
     */
    public function maxDelaySeconds(): int
    {
        return self::MAX_DELAY_SECONDS;
    }
}
