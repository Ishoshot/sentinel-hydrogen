<?php

declare(strict_types=1);

use App\Actions\Billing\Resolvers\PolarBillingIntervalResolver;
use App\Enums\Billing\BillingInterval;

it('extracts billing interval from subscription recurring_interval', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    $result = $resolver->extract(['recurring_interval' => 'monthly'], null);

    expect($result)->toBe(BillingInterval::Monthly);
});

it('extracts billing interval from subscription billing_interval', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    $result = $resolver->extract(['billing_interval' => 'yearly'], null);

    expect($result)->toBe(BillingInterval::Yearly);
});

it('falls back to order data when subscription is null', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    $result = $resolver->extract(null, ['recurring_interval' => 'monthly']);

    expect($result)->toBe(BillingInterval::Monthly);
});

it('extracts from product prices', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    $result = $resolver->extract([
        'product' => [
            'prices' => [
                ['recurring_interval' => 'yearly'],
            ],
        ],
    ], null);

    expect($result)->toBe(BillingInterval::Yearly);
});

it('returns null for empty data', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    expect($resolver->extract(null, null))->toBeNull();
});

it('returns null when product has no prices', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    $result = $resolver->extract(['product' => ['prices' => []]], null);

    expect($result)->toBeNull();
});

it('returns null for non-array product', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    $result = $resolver->extract(['product' => 'not-array'], null);

    expect($result)->toBeNull();
});

it('returns null when price recurring_interval is not a string', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    $result = $resolver->extract([
        'product' => [
            'prices' => [
                ['recurring_interval' => 123],
            ],
        ],
    ], null);

    expect($result)->toBeNull();
});

it('returns null when prices entry is not an array', function (): void {
    $resolver = new PolarBillingIntervalResolver;

    $result = $resolver->extract([
        'product' => [
            'prices' => ['not-an-array'],
        ],
    ], null);

    expect($result)->toBeNull();
});
