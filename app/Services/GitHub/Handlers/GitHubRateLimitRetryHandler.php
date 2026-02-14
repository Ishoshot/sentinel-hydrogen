<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

final readonly class GitHubRateLimitRetryHandler
{
    /**
     * Create a new retry handler instance.
     */
    public function __construct(
        private GitHubRateLimitStateStore $stateStore,
        private GitHubRateLimitErrorInspector $errorInspector,
        private GitHubRateLimitBackoffCalculator $backoffCalculator,
    ) {}

    /**
     * Determine if an exception is a rate-limit exception.
     */
    public function isRateLimitError(RuntimeException $exception): bool
    {
        return $this->errorInspector->isRateLimitError($exception);
    }

    /**
     * Apply retry backoff for a rate-limit exception.
     */
    public function handle(RuntimeException $exception, int $attempt, string $operation): void
    {
        $delay = $this->backoffCalculator->calculate($attempt);
        $resetTime = $this->errorInspector->extractResetTime($exception->getMessage());

        if ($resetTime !== null && $resetTime > time()) {
            $delay = min($resetTime - time(), $this->backoffCalculator->maxDelaySeconds());
            $this->stateStore->setCooldownUntil($resetTime);
        }

        Log::warning('GitHub rate limit hit, backing off', [
            'operation' => $operation,
            'attempt' => $attempt,
            'delay_seconds' => $delay,
            'error' => $exception->getMessage(),
        ]);

        $this->stateStore->incrementRateLimitHitsThisHour();

        Sleep::for((int) ceil($delay))->seconds();
    }
}
