<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use Illuminate\Support\Facades\Cache;

final class GitHubRateLimitStateStore
{
    private const string CACHE_PREFIX = 'github_rate_limit:';

    /**
     * GetCooldownUntil.
     */
    public function getCooldownUntil(): ?int
    {
        $cooldownUntil = Cache::get(self::CACHE_PREFIX.'cooldown');

        return is_int($cooldownUntil) ? $cooldownUntil : null;
    }

    /**
     * SetCooldownUntil.
     */
    public function setCooldownUntil(int $cooldownUntil): void
    {
        Cache::put(self::CACHE_PREFIX.'cooldown', $cooldownUntil, ($cooldownUntil - time()) + 10);
    }

    /**
     * IsInCooldown.
     */
    public function isInCooldown(): bool
    {
        $cooldownUntil = $this->getCooldownUntil();

        return is_int($cooldownUntil) && $cooldownUntil > time();
    }

    /**
     * GetCooldownRemaining.
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
     * GetRateLimitHitsThisHour.
     */
    public function getRateLimitHitsThisHour(): int
    {
        $count = Cache::get($this->hitsKey(), 0);

        return is_int($count) ? $count : 0;
    }

    /**
     * IncrementRateLimitHitsThisHour.
     */
    public function incrementRateLimitHitsThisHour(): void
    {
        $currentCount = $this->getRateLimitHitsThisHour();

        Cache::put($this->hitsKey(), $currentCount + 1, 3600);
    }

    /**
     * HitsKey.
     */
    private function hitsKey(): string
    {
        return self::CACHE_PREFIX.'hits:'.date('Y-m-d:H');
    }
}
