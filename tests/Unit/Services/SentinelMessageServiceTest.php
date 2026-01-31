<?php

declare(strict_types=1);

use App\Services\SentinelMessageService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('sentinel:messages');
    $this->service = new SentinelMessageService;
});

it('gets a random greeting with expected structure', function (): void {
    $greeting = $this->service->getRandomGreeting();

    expect($greeting)->toBeArray()
        ->toHaveKeys(['emoji', 'message'])
        ->and($greeting['emoji'])->toBeString()->not->toBeEmpty()
        ->and($greeting['message'])->toBeString()->not->toBeEmpty();
});

it('gets a random branding string', function (): void {
    $branding = $this->service->getRandomBranding();

    expect($branding)->toBeString()->not->toBeEmpty()
        ->and($branding)->toContain('Sentinel');
});

it('builds greeting comment with proper structure', function (): void {
    $comment = $this->service->buildGreetingComment();

    expect($comment)->toBeString()
        ->toContain('---')
        ->toContain('Sentinel');
});

it('builds review sign off with url', function (): void {
    $url = 'https://example.com/runs/123';
    $signOff = $this->service->buildReviewSignOff($url);

    expect($signOff)->toBeString()
        ->toContain('---')
        ->toContain('[View full analysis]')
        ->toContain($url)
        ->toContain('Sentinel');
});

it('builds config error comment with error message', function (): void {
    $error = 'Invalid YAML syntax at line 5';
    $comment = $this->service->buildConfigErrorComment($error);

    expect($comment)->toBeString()
        ->toContain('Configuration Error')
        ->toContain($error)
        ->toContain('---')
        ->toContain('Sentinel');
});

it('builds no provider keys comment', function (): void {
    $comment = $this->service->buildNoProviderKeysComment();

    expect($comment)->toBeString()
        ->toContain('Review Skipped')
        ->toContain('No API Key')
        ->toContain('---')
        ->toContain('Sentinel');
});

it('builds run failed comment with error type', function (): void {
    $errorType = 'RateLimitExceeded';
    $comment = $this->service->buildRunFailedComment($errorType);

    expect($comment)->toBeString()
        ->toContain('Review Failed')
        ->toContain($errorType)
        ->toContain('---')
        ->toContain('Sentinel');
});

it('builds plan limit reached comment with custom message', function (): void {
    $message = 'You have used 100 of 100 reviews this month.';
    $comment = $this->service->buildPlanLimitReachedComment($message);

    expect($comment)->toBeString()
        ->toContain('Plan Limit Reached')
        ->toContain($message)
        ->toContain('---')
        ->toContain('Sentinel');
});

it('builds plan limit reached comment with null message uses default', function (): void {
    $comment = $this->service->buildPlanLimitReachedComment(null);

    expect($comment)->toBeString()
        ->toContain('Plan Limit Reached')
        ->toContain('Your current plan has reached its limit')
        ->toContain('---')
        ->toContain('Sentinel');
});

it('caches messages after first load', function (): void {
    // First call loads from file
    $greeting1 = $this->service->getRandomGreeting();

    // Verify cache was set
    expect(Cache::has('sentinel:messages'))->toBeTrue();

    // Second call should use cache (we can't directly verify this,
    // but we verify the result is still valid)
    $greeting2 = $this->service->getRandomGreeting();

    expect($greeting2)->toBeArray()
        ->toHaveKeys(['emoji', 'message']);
});

it('validates cached message structure', function (): void {
    // Store invalid cache data
    Cache::put('sentinel:messages', ['invalid' => 'data'], 3600);

    // Service should reload from file due to invalid structure
    $greeting = $this->service->getRandomGreeting();

    expect($greeting)->toBeArray()
        ->toHaveKeys(['emoji', 'message']);
});

it('loads valid messages from cache on new instance', function (): void {
    // First, load and cache the messages
    $this->service->getRandomGreeting();

    // Create a new service instance - it should use the cached messages
    $newService = new SentinelMessageService;
    $greeting = $newService->getRandomGreeting();

    expect($greeting)->toBeArray()
        ->toHaveKeys(['emoji', 'message']);
});
