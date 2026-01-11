<?php

declare(strict_types=1);

use App\Services\GitHub\GitHubRateLimiter;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Cache::flush();
    Sleep::fake();
});

it('executes callback successfully', function (): void {
    $rateLimiter = new GitHubRateLimiter;

    $result = $rateLimiter->handle(fn () => ['data' => 'test']);

    expect($result)->toBe(['data' => 'test']);
});

it('returns callback result on success', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $callCount = 0;

    $result = $rateLimiter->handle(function () use (&$callCount): string {
        $callCount++;

        return 'success';
    });

    expect($result)->toBe('success')
        ->and($callCount)->toBe(1);
});

it('detects rate limit error from message', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    try {
        $rateLimiter->handle(function () use (&$attempts): never {
            $attempts++;
            throw new RuntimeException('API rate limit exceeded');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('API rate limit exceeded');
    }

    expect($attempts)->toBe(3);
});

it('detects abuse rate limit error', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    try {
        $rateLimiter->handle(function () use (&$attempts): never {
            $attempts++;
            throw new RuntimeException('You have triggered an abuse detection mechanism');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('abuse detection');
    }

    expect($attempts)->toBe(3);
});

it('detects secondary rate limit error', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    try {
        $rateLimiter->handle(function () use (&$attempts): never {
            $attempts++;
            throw new RuntimeException('You have exceeded a secondary rate limit');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('secondary rate limit');
    }

    expect($attempts)->toBe(3);
});

it('detects 403 rate limit error', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    try {
        $rateLimiter->handle(function () use (&$attempts): never {
            $attempts++;
            throw new RuntimeException('403 Forbidden: rate limit exceeded');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('403 Forbidden');
    }

    expect($attempts)->toBe(3);
});

it('detects 429 too many requests error', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    try {
        $rateLimiter->handle(function () use (&$attempts): never {
            $attempts++;
            throw new RuntimeException('429 Too Many Requests');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('429 Too Many Requests');
    }

    expect($attempts)->toBe(3);
});

it('does not retry non-rate-limit errors', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    try {
        $rateLimiter->handle(function () use (&$attempts): never {
            $attempts++;
            throw new RuntimeException('Resource not found');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Resource not found');
    }

    expect($attempts)->toBe(1);
});

it('rethrows non-RuntimeException errors', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    try {
        $rateLimiter->handle(function () use (&$attempts): never {
            $attempts++;
            throw new InvalidArgumentException('Invalid argument');
        });
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toBe('Invalid argument');
    }

    expect($attempts)->toBe(1);
});

it('succeeds on retry after rate limit', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    $result = $rateLimiter->handle(function () use (&$attempts): string {
        $attempts++;
        if ($attempts < 2) {
            throw new RuntimeException('Rate limit exceeded');
        }

        return 'success after retry';
    });

    expect($result)->toBe('success after retry')
        ->and($attempts)->toBe(2);
});

it('increments rate limit counter on hit', function (): void {
    $rateLimiter = new GitHubRateLimiter;

    expect($rateLimiter->getRateLimitHitsThisHour())->toBe(0);

    try {
        $rateLimiter->handle(fn (): never => throw new RuntimeException('Rate limit exceeded'));
    } catch (RuntimeException) {
        // Expected
    }

    expect($rateLimiter->getRateLimitHitsThisHour())->toBeGreaterThan(0);
});

it('checks cooldown status', function (): void {
    $rateLimiter = new GitHubRateLimiter;

    expect($rateLimiter->isInCooldown())->toBeFalse()
        ->and($rateLimiter->getCooldownRemaining())->toBe(0);
});

it('respects existing cooldown period', function (): void {
    // Set a cooldown in cache
    $cooldownUntil = time() + 10;
    Cache::put('github_rate_limit:cooldown', $cooldownUntil, 20);

    $rateLimiter = new GitHubRateLimiter;

    expect($rateLimiter->isInCooldown())->toBeTrue()
        ->and($rateLimiter->getCooldownRemaining())->toBeGreaterThan(0)
        ->and($rateLimiter->getCooldownRemaining())->toBeLessThanOrEqual(10);
});

it('returns zero cooldown when expired', function (): void {
    // Set an expired cooldown in cache
    Cache::put('github_rate_limit:cooldown', time() - 10, 20);

    $rateLimiter = new GitHubRateLimiter;

    expect($rateLimiter->isInCooldown())->toBeFalse()
        ->and($rateLimiter->getCooldownRemaining())->toBe(0);
});

it('extracts retry-after from error message', function (): void {
    $rateLimiter = new GitHubRateLimiter;
    $attempts = 0;

    try {
        $rateLimiter->handle(function () use (&$attempts): never {
            $attempts++;
            throw new RuntimeException('Rate limit exceeded. Retry after 5 seconds');
        });
    } catch (RuntimeException) {
        // Expected
    }

    // Verify it attempted retries
    expect($attempts)->toBe(3);
});

it('handles operation description in logging', function (): void {
    $rateLimiter = new GitHubRateLimiter;

    $result = $rateLimiter->handle(
        fn () => 'test result',
        'getPullRequest(owner/repo#123)'
    );

    expect($result)->toBe('test result');
});

it('sleeps between retries', function (): void {
    $rateLimiter = new GitHubRateLimiter;

    try {
        $rateLimiter->handle(fn (): never => throw new RuntimeException('Rate limit exceeded'));
    } catch (RuntimeException) {
        // Expected
    }

    // Sleep should have been called for each retry (3 retries = 3 sleeps)
    Sleep::assertSleptTimes(3);
});
