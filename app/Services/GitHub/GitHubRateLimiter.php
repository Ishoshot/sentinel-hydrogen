<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Services\GitHub\Contracts\GitHubRateLimiterContract;
use App\Services\GitHub\Support\GitHubRateLimitBackoffCalculator;
use App\Services\GitHub\Support\GitHubRateLimitErrorInspector;
use App\Services\GitHub\Support\GitHubRateLimitStateStore;
use Closure;
use Github\Exception\RuntimeException;
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
final class GitHubRateLimiter implements GitHubRateLimiterContract
{
    /**
     * Maximum number of retry attempts.
     */
    private const int MAX_RETRIES = 3;

    /**
     * Create a new rate limiter instance.
     */
    public function __construct(
        private readonly ?GitHubRateLimitStateStore $stateStore = null,
        private readonly ?GitHubRateLimitErrorInspector $errorInspector = null,
        private readonly ?GitHubRateLimitBackoffCalculator $backoffCalculator = null,
    ) {}

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

            $cooldownUntil = $this->stateStore()->getCooldownUntil();

            if (is_int($cooldownUntil) && $cooldownUntil > time()) {
                $waitTime = $cooldownUntil - time();
                Log::debug('GitHub rate limit cooldown active', [
                    'operation' => $operation,
                    'wait_seconds' => $waitTime,
                ]);

                if ($waitTime > 0 && $waitTime <= $this->backoffCalculator()->maxDelaySeconds()) {
                    Sleep::for($waitTime)->seconds();
                }
            }

            try {
                $result = $callback();

                $this->resetBackoff();

                return $result;
            } catch (RuntimeException $e) {
                if ($this->errorInspector()->isRateLimitError($e)) {
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
        return $this->stateStore()->getRateLimitHitsThisHour();
    }

    /**
     * Check if we're currently in a cooldown period.
     */
    public function isInCooldown(): bool
    {
        return $this->stateStore()->isInCooldown();
    }

    /**
     * Get the remaining cooldown time in seconds.
     */
    public function getCooldownRemaining(): int
    {
        return $this->stateStore()->getCooldownRemaining();
    }

    /**
     * Handle a rate limit error with exponential backoff.
     */
    private function handleRateLimitError(RuntimeException $e, int $attempt, string $operation): void
    {
        $delay = $this->backoffCalculator()->calculate($attempt);
        $resetTime = $this->errorInspector()->extractResetTime($e->getMessage());

        if ($resetTime !== null && $resetTime > time()) {
            $delay = min($resetTime - time(), $this->backoffCalculator()->maxDelaySeconds());
            $this->stateStore()->setCooldownUntil($resetTime);
        }

        Log::warning('GitHub rate limit hit, backing off', [
            'operation' => $operation,
            'attempt' => $attempt,
            'delay_seconds' => $delay,
            'error' => $e->getMessage(),
        ]);

        $this->incrementRateLimitCounter();

        Sleep::for((int) ceil($delay))->seconds();
    }

    /**
     * Reset the backoff state after a successful request.
     */
    private function resetBackoff(): void {}

    /**
     * Increment the rate limit counter for monitoring.
     */
    private function incrementRateLimitCounter(): void
    {
        $this->stateStore()->incrementRateLimitHitsThisHour();
    }

    private function stateStore(): GitHubRateLimitStateStore
    {
        return $this->stateStore ?? new GitHubRateLimitStateStore;
    }

    private function errorInspector(): GitHubRateLimitErrorInspector
    {
        return $this->errorInspector ?? new GitHubRateLimitErrorInspector;
    }

    private function backoffCalculator(): GitHubRateLimitBackoffCalculator
    {
        return $this->backoffCalculator ?? new GitHubRateLimitBackoffCalculator;
    }
}
