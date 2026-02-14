<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Services\GitHub\Contracts\GitHubRateLimiterContract;
use App\Services\GitHub\Support\GitHubRateLimitBackoffCalculator;
use App\Services\GitHub\Support\GitHubRateLimitCooldownEnforcer;
use App\Services\GitHub\Support\GitHubRateLimitErrorInspector;
use App\Services\GitHub\Support\GitHubRateLimitRetryHandler;
use App\Services\GitHub\Support\GitHubRateLimitStateStore;
use Closure;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles GitHub API rate limiting with exponential backoff.
 *
 * GitHub rate limits:
 * - 5,000 requests per hour for authenticated requests (installation tokens)
 * - Secondary rate limits for abuse detection
 */
final readonly class GitHubRateLimiter implements GitHubRateLimiterContract
{
    /**
     * Maximum number of retry attempts.
     */
    private const int MAX_RETRIES = 3;

    /**
     * Create a new rate limiter instance.
     */
    public function __construct(
        private ?GitHubRateLimitStateStore $stateStore = null,
        private ?GitHubRateLimitErrorInspector $errorInspector = null,
        private ?GitHubRateLimitBackoffCalculator $backoffCalculator = null,
        private ?GitHubRateLimitCooldownEnforcer $cooldownEnforcer = null,
        private ?GitHubRateLimitRetryHandler $retryHandler = null,
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

            $this->cooldownEnforcer()->enforce($operation);

            try {
                $result = $callback();

                $this->resetBackoff();

                return $result;
            } catch (RuntimeException $e) {
                if (! $this->retryHandler()->isRateLimitError($e)) {
                    throw $e;
                }

                $this->retryHandler()->handle($e, $attempt, $operation);

                if ($attempt >= self::MAX_RETRIES) {
                    Log::error('GitHub rate limit exceeded, max retries reached', [
                        'operation' => $operation,
                        'attempts' => $attempt,
                    ]);

                    throw $e;
                }

                continue;
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
     * Reset the backoff state after a successful request.
     */
    private function resetBackoff(): void {}

    /**
     * StateStore.
     */
    private function stateStore(): GitHubRateLimitStateStore
    {
        return $this->stateStore ?? new GitHubRateLimitStateStore;
    }

    /**
     * ErrorInspector.
     */
    private function errorInspector(): GitHubRateLimitErrorInspector
    {
        return $this->errorInspector ?? new GitHubRateLimitErrorInspector;
    }

    /**
     * BackoffCalculator.
     */
    private function backoffCalculator(): GitHubRateLimitBackoffCalculator
    {
        return $this->backoffCalculator ?? new GitHubRateLimitBackoffCalculator;
    }

    /**
     * CooldownEnforcer.
     */
    private function cooldownEnforcer(): GitHubRateLimitCooldownEnforcer
    {
        return $this->cooldownEnforcer ?? new GitHubRateLimitCooldownEnforcer(
            $this->stateStore(),
            $this->backoffCalculator(),
        );
    }

    /**
     * RetryHandler.
     */
    private function retryHandler(): GitHubRateLimitRetryHandler
    {
        return $this->retryHandler ?? new GitHubRateLimitRetryHandler(
            $this->stateStore(),
            $this->errorInspector(),
            $this->backoffCalculator(),
        );
    }
}
