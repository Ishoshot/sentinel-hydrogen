<?php

declare(strict_types=1);

namespace App\Services\GitHub\Handlers;

use App\Services\GitHub\Policies\GitHubRateLimitErrorPolicy;
use App\Services\GitHub\Repositories\GitHubRateLimitStateRepository;
use App\Services\GitHub\Strategies\GitHubRateLimitBackoffStrategy;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

final readonly class GitHubRateLimitRetryHandler
{
    /**
     * Create a new retry handler instance.
     */
    public function __construct(
        private GitHubRateLimitStateRepository $stateRepository,
        private GitHubRateLimitErrorPolicy $errorPolicy,
        private GitHubRateLimitBackoffStrategy $backoffStrategy,
    ) {}

    /**
     * Determine if an exception is a rate-limit exception.
     */
    public function isRateLimitError(RuntimeException $exception): bool
    {
        return $this->errorPolicy->isRateLimitError($exception);
    }

    /**
     * Apply retry backoff for a rate-limit exception.
     */
    public function handle(RuntimeException $exception, int $attempt, string $operation): void
    {
        $delay = $this->backoffStrategy->calculate($attempt);
        $resetTime = $this->errorPolicy->extractResetTime($exception->getMessage());

        if ($resetTime !== null && $resetTime > time()) {
            $delay = min($resetTime - time(), $this->backoffStrategy->maxDelaySeconds());
            $this->stateRepository->setCooldownUntil($resetTime);
        }

        Log::warning('GitHub rate limit hit, backing off', [
            'operation' => $operation,
            'attempt' => $attempt,
            'delay_seconds' => $delay,
            'error' => $exception->getMessage(),
        ]);

        $this->stateRepository->incrementRateLimitHitsThisHour();

        Sleep::for((int) ceil($delay))->seconds();
    }
}
