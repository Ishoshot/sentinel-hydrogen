<?php

declare(strict_types=1);

use App\Actions\Billing\Resolvers\PolarWebhookTimestampResolver;
use Carbon\CarbonImmutable;

it('parses integer timestamps', function (): void {
    $resolver = new PolarWebhookTimestampResolver;
    $result = $resolver->parse(1707955200);

    expect($result)->toBeInstanceOf(CarbonImmutable::class);
});

it('parses string timestamps', function (): void {
    $resolver = new PolarWebhookTimestampResolver;
    $result = $resolver->parse('2026-02-15T00:00:00Z');

    expect($result)->toBeInstanceOf(CarbonImmutable::class);
    expect($result->year)->toBe(2026);
});

it('returns null for empty string', function (): void {
    $resolver = new PolarWebhookTimestampResolver;

    expect($resolver->parse(''))->toBeNull();
});

it('returns null for null', function (): void {
    $resolver = new PolarWebhookTimestampResolver;

    expect($resolver->parse(null))->toBeNull();
});

it('returns null for non-string non-int', function (): void {
    $resolver = new PolarWebhookTimestampResolver;

    expect($resolver->parse([]))->toBeNull();
    expect($resolver->parse(true))->toBeNull();
});

it('returns null for invalid date strings', function (): void {
    $resolver = new PolarWebhookTimestampResolver;

    expect($resolver->parse('not-a-date'))->toBeNull();
});
