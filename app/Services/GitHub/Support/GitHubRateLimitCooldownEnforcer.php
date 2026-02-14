<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

final readonly class GitHubRateLimitCooldownEnforcer
{
    /**
     * Create a new cooldown enforcer instance.
     */
    public function __construct(
        private GitHubRateLimitStateStore $stateStore,
        private GitHubRateLimitBackoffCalculator $backoffCalculator,
    ) {}

    /**
     * Enforce any active cooldown before an API operation.
     */
    public function enforce(string $operation): void
    {
        $cooldownUntil = $this->stateStore->getCooldownUntil();

        if (! is_int($cooldownUntil) || $cooldownUntil <= time()) {
            return;
        }

        $waitTime = $cooldownUntil - time();

        Log::debug('GitHub rate limit cooldown active', [
            'operation' => $operation,
            'wait_seconds' => $waitTime,
        ]);

        if ($waitTime > 0 && $waitTime <= $this->backoffCalculator->maxDelaySeconds()) {
            Sleep::for($waitTime)->seconds();
        }
    }
}
