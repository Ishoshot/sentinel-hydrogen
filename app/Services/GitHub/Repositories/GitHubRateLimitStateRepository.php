<?php

declare(strict_types=1);

namespace App\Services\GitHub\Repositories;

use Illuminate\Support\Facades\Cache;

final class GitHubRateLimitStateRepository
{
    private const string CACHE_PREFIX = 'github_rate_limit:';

    /**
     * Get the cooldown-until timestamp.
     */
    public function getCooldownUntil(): ?int
    {
        $cooldownUntil = Cache::get(self::CACHE_PREFIX.'cooldown');

        return is_int($cooldownUntil) ? $cooldownUntil : null;
    }

    /**
     * Set the cooldown-until timestamp.
     */
    public function setCooldownUntil(int $cooldownUntil): void
    {
        Cache::put(self::CACHE_PREFIX.'cooldown', $cooldownUntil, ($cooldownUntil - time()) + 10);
    }

    /**
     * Determine if the rate limiter is currently in cooldown.
     */
    public function isInCooldown(): bool
    {
        $cooldownUntil = $this->getCooldownUntil();

        return is_int($cooldownUntil) && $cooldownUntil > time();
    }

    /**
     * Get the remaining cooldown time in seconds.
     */
    public function getCooldownRemaining(): int
    {
        $cooldownUntil = $this->getCooldownUntil();

        if (! is_int($cooldownUntil) || $cooldownUntil <= time()) {
            return 0;
        }

        return $cooldownUntil - time();
    }

    /**
     * Get the number of rate limit hits in the current hour.
     */
    public function getRateLimitHitsThisHour(): int
    {
        $count = Cache::get($this->hitsKey(), 0);

        return is_int($count) ? $count : 0;
    }

    /**
     * Increment the rate limit hit counter for the current hour.
     */
    public function incrementRateLimitHitsThisHour(): void
    {
        $currentCount = $this->getRateLimitHitsThisHour();

        Cache::put($this->hitsKey(), $currentCount + 1, 3600);
    }

    /**
     * Get the cache key for hourly hits.
     */
    private function hitsKey(): string
    {
        return self::CACHE_PREFIX.'hits:'.date('Y-m-d:H');
    }
}
