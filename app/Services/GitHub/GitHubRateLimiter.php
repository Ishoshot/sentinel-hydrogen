<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Services\GitHub\Contracts\GitHubRateLimiterContract;
use App\Services\GitHub\Handlers\GitHubRateLimitRetryHandler;
use App\Services\GitHub\Policies\GitHubRateLimitErrorPolicy;
use App\Services\GitHub\Repositories\GitHubRateLimitStateRepository;
use App\Services\GitHub\Strategies\GitHubRateLimitBackoffStrategy;
use App\Services\GitHub\Strategies\GitHubRateLimitCooldownStrategy;
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
        private ?GitHubRateLimitStateRepository $stateRepository = null,
        private ?GitHubRateLimitErrorPolicy $errorPolicy = null,
        private ?GitHubRateLimitBackoffStrategy $backoffStrategy = null,
        private ?GitHubRateLimitCooldownStrategy $cooldownStrategy = null,
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

            $this->cooldownStrategy()->enforce($operation);

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
        return $this->stateRepository()->getRateLimitHitsThisHour();
    }

    /**
     * Check if we're currently in a cooldown period.
     */
    public function isInCooldown(): bool
    {
        return $this->stateRepository()->isInCooldown();
    }

    /**
     * Get the remaining cooldown time in seconds.
     */
    public function getCooldownRemaining(): int
    {
        return $this->stateRepository()->getCooldownRemaining();
    }

    /**
     * Reset the backoff state after a successful request.
     */
    private function resetBackoff(): void {}

    /**
     * Resolve the state repository instance.
     */
    private function stateRepository(): GitHubRateLimitStateRepository
    {
        return $this->stateRepository ?? new GitHubRateLimitStateRepository;
    }

    /**
     * Resolve the error policy instance.
     */
    private function errorPolicy(): GitHubRateLimitErrorPolicy
    {
        return $this->errorPolicy ?? new GitHubRateLimitErrorPolicy;
    }

    /**
     * Resolve the backoff strategy instance.
     */
    private function backoffStrategy(): GitHubRateLimitBackoffStrategy
    {
        return $this->backoffStrategy ?? new GitHubRateLimitBackoffStrategy;
    }

    /**
     * Resolve the cooldown strategy instance.
     */
    private function cooldownStrategy(): GitHubRateLimitCooldownStrategy
    {
        return $this->cooldownStrategy ?? new GitHubRateLimitCooldownStrategy(
            $this->stateRepository(),
            $this->backoffStrategy(),
        );
    }

    /**
     * Resolve the retry handler instance.
     */
    private function retryHandler(): GitHubRateLimitRetryHandler
    {
        return $this->retryHandler ?? new GitHubRateLimitRetryHandler(
            $this->stateRepository(),
            $this->errorPolicy(),
            $this->backoffStrategy(),
        );
    }
}
