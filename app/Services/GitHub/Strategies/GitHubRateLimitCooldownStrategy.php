<?php

declare(strict_types=1);

namespace App\Services\GitHub\Strategies;

use App\Services\GitHub\Repositories\GitHubRateLimitStateRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

final readonly class GitHubRateLimitCooldownStrategy
{
    /**
     * Create a new cooldown strategy instance.
     */
    public function __construct(
        private GitHubRateLimitStateRepository $stateRepository,
        private GitHubRateLimitBackoffStrategy $backoffStrategy,
    ) {}

    /**
     * Enforce any active cooldown before an API operation.
     */
    public function enforce(string $operation): void
    {
        $cooldownUntil = $this->stateRepository->getCooldownUntil();

        if (! is_int($cooldownUntil) || $cooldownUntil <= time()) {
            return;
        }

        $waitTime = $cooldownUntil - time();

        Log::debug('GitHub rate limit cooldown active', [
            'operation' => $operation,
            'wait_seconds' => $waitTime,
        ]);

        if ($waitTime > 0 && $waitTime <= $this->backoffStrategy->maxDelaySeconds()) {
            Sleep::for($waitTime)->seconds();
        }
    }
}
