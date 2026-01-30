<?php

declare(strict_types=1);

namespace App\Services\GitHub\Contracts;

use Closure;

/**
 * Contract for GitHub API rate limiting with exponential backoff.
 */
interface GitHubRateLimiterContract
{
    /**
     * Execute a GitHub API call with rate limiting and retry logic.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  string  $operation  Description of the operation for logging
     * @return T
     */
    public function handle(Closure $callback, string $operation = 'GitHub API call'): mixed;

    /**
     * Get the current rate limit hit count for the hour.
     */
    public function getRateLimitHitsThisHour(): int;

    /**
     * Check if we're currently in a cooldown period.
     */
    public function isInCooldown(): bool;

    /**
     * Get the remaining cooldown time in seconds.
     */
    public function getCooldownRemaining(): int;
}
