<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use Closure;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Handles GitHub API rate limiting with exponential backoff.
 *
 * GitHub rate limits:
 * - 5,000 requests per hour for authenticated requests (installation tokens)
 * - Secondary rate limits for abuse detection
 */
final class GitHubRateLimiter
{
    /**
     * Maximum number of retry attempts.
     */
    private const int MAX_RETRIES = 3;

    /**
     * Base delay in seconds for exponential backoff.
     */
    private const int BASE_DELAY_SECONDS = 1;

    /**
     * Maximum delay in seconds.
     */
    private const int MAX_DELAY_SECONDS = 60;

    /**
     * Cache key prefix for rate limit tracking.
     */
    private const string CACHE_PREFIX = 'github_rate_limit:';

    /**
     * Execute a GitHub API call with rate limiting and retry logic.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  string  $operation  Description of the operation for logging
     * @return T
     *
     * @throws RuntimeException When rate limited after all retries
     * @throws Throwable When the callback throws a non-rate-limit exception
     */
    public function handle(Closure $callback, string $operation = 'GitHub API call'): mixed
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            // Check if we're in a cooldown period
            $cooldownKey = self::CACHE_PREFIX.'cooldown';
            $cooldownUntil = Cache::get($cooldownKey);

            if ($cooldownUntil !== null && $cooldownUntil > time()) {
                $waitTime = $cooldownUntil - time();
                Log::debug('GitHub rate limit cooldown active', [
                    'operation' => $operation,
                    'wait_seconds' => $waitTime,
                ]);

                if ($waitTime > 0 && $waitTime <= self::MAX_DELAY_SECONDS) {
                    Sleep::for($waitTime)->seconds();
                }
            }

            try {
                $result = $callback();

                // Reset backoff on success
                $this->resetBackoff();

                return $result;
            } catch (RuntimeException $e) {
                if ($this->isRateLimitError($e)) {
                    $this->handleRateLimitError($e, $attempt, $operation);

                    if ($attempt >= self::MAX_RETRIES) {
                        Log::error('GitHub rate limit exceeded, max retries reached', [
                            'operation' => $operation,
                            'attempts' => $attempt,
                        ]);

                        throw $e;
                    }

                    continue;
                }

                // Not a rate limit error, rethrow
                throw $e;
            }
        }

        // This shouldn't be reached, but just in case
        throw new RuntimeException('GitHub API call failed after max retries');
    }

    /**
     * Get the current rate limit hit count for the hour.
     */
    public function getRateLimitHitsThisHour(): int
    {
        $key = self::CACHE_PREFIX.'hits:'.date('Y-m-d:H');

        return (int) Cache::get($key, 0);
    }

    /**
     * Check if we're currently in a cooldown period.
     */
    public function isInCooldown(): bool
    {
        $cooldownUntil = Cache::get(self::CACHE_PREFIX.'cooldown');

        return $cooldownUntil !== null && $cooldownUntil > time();
    }

    /**
     * Get the remaining cooldown time in seconds.
     */
    public function getCooldownRemaining(): int
    {
        $cooldownUntil = Cache::get(self::CACHE_PREFIX.'cooldown');

        if ($cooldownUntil === null || $cooldownUntil <= time()) {
            return 0;
        }

        return $cooldownUntil - time();
    }

    /**
     * Check if an exception is a rate limit error.
     */
    private function isRateLimitError(RuntimeException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        // Primary rate limit
        if (str_contains($message, 'rate limit')) {
            return true;
        }

        // Secondary rate limit (abuse detection)
        if (str_contains($message, 'abuse') || str_contains($message, 'secondary rate')) {
            return true;
        }

        // HTTP 403 with rate limit indication
        if (str_contains($message, '403') && str_contains($message, 'limit')) {
            return true;
        }

        // HTTP 429 Too Many Requests
        if (str_contains($message, '429')) {
            return true;
        }

        return false;
    }

    /**
     * Handle a rate limit error with exponential backoff.
     */
    private function handleRateLimitError(RuntimeException $e, int $attempt, string $operation): void
    {
        // Calculate delay with exponential backoff and jitter
        $delay = min(
            self::BASE_DELAY_SECONDS * (2 ** ($attempt - 1)) + random_int(0, 1000) / 1000,
            self::MAX_DELAY_SECONDS
        );

        // Try to extract reset time from error message
        $resetTime = $this->extractResetTime($e->getMessage());

        if ($resetTime !== null && $resetTime > time()) {
            // Use GitHub's suggested reset time
            $delay = min($resetTime - time(), self::MAX_DELAY_SECONDS);

            // Set cooldown for other requests
            Cache::put(self::CACHE_PREFIX.'cooldown', $resetTime, $resetTime - time() + 10);
        }

        Log::warning('GitHub rate limit hit, backing off', [
            'operation' => $operation,
            'attempt' => $attempt,
            'delay_seconds' => $delay,
            'error' => $e->getMessage(),
        ]);

        // Increment rate limit counter for monitoring
        $this->incrementRateLimitCounter();

        Sleep::for((int) ceil($delay))->seconds();
    }

    /**
     * Try to extract the rate limit reset time from an error message.
     */
    private function extractResetTime(string $message): ?int
    {
        // GitHub often includes reset time in error responses
        // Pattern: "rate limit ... resets at TIMESTAMP" or "retry after N seconds"
        if (preg_match('/retry.?after[:\s]+(\d+)/i', $message, $matches)) {
            return time() + (int) $matches[1];
        }

        if (preg_match('/reset[s]?.+?(\d{10,})/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Reset the backoff state after a successful request.
     */
    private function resetBackoff(): void
    {
        // We don't clear the cooldown here as it should expire naturally
        // This prevents a single successful request from allowing a flood
    }

    /**
     * Increment the rate limit counter for monitoring.
     */
    private function incrementRateLimitCounter(): void
    {
        $key = self::CACHE_PREFIX.'hits:'.date('Y-m-d:H');
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, 3600);
    }
}
