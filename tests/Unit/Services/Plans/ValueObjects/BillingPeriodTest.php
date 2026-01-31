<?php

declare(strict_types=1);

use App\Services\Plans\ValueObjects\BillingPeriod;
use Carbon\CarbonImmutable;

it('can be constructed with start and end dates', function (): void {
    $start = CarbonImmutable::parse('2025-01-01');
    $end = CarbonImmutable::parse('2025-01-31');

    $period = new BillingPeriod($start, $end);

    expect($period->start)->toBe($start);
    expect($period->end)->toBe($end);
});

it('creates current month billing period', function (): void {
    $period = BillingPeriod::currentMonth();

    expect($period->start->day)->toBe(1);
    expect($period->start->month)->toBe(CarbonImmutable::now()->month);
    expect($period->start->year)->toBe(CarbonImmutable::now()->year);
});

it('creates billing period for specific month', function (): void {
    $period = BillingPeriod::forMonth(2025, 6);

    expect($period->start->year)->toBe(2025);
    expect($period->start->month)->toBe(6);
    expect($period->start->day)->toBe(1);
    expect($period->end->year)->toBe(2025);
    expect($period->end->month)->toBe(6);
    expect($period->end->day)->toBe(30);
});

it('checks if date is within period', function (): void {
    $period = BillingPeriod::forMonth(2025, 1);

    expect($period->contains(CarbonImmutable::parse('2025-01-15')))->toBeTrue();
    expect($period->contains(CarbonImmutable::parse('2025-02-01')))->toBeFalse();
    expect($period->contains(CarbonImmutable::parse('2024-12-31')))->toBeFalse();
});

it('calculates days in period', function (): void {
    $period = BillingPeriod::forMonth(2025, 1);

    expect($period->daysInPeriod())->toBe(31);
});

it('calculates days in period for february', function (): void {
    $period = BillingPeriod::forMonth(2025, 2);

    expect($period->daysInPeriod())->toBe(28);
});

it('calculates days remaining when past end date', function (): void {
    $start = CarbonImmutable::now()->subMonth()->startOfMonth();
    $end = $start->endOfMonth();

    $period = new BillingPeriod($start, $end);

    expect($period->daysRemaining())->toBe(0);
});

it('calculates days remaining when before start date', function (): void {
    $start = CarbonImmutable::now()->addMonth()->startOfMonth();
    $end = $start->endOfMonth();

    $period = new BillingPeriod($start, $end);

    expect($period->daysRemaining())->toBe($period->daysInPeriod());
});

it('returns start as string', function (): void {
    $period = BillingPeriod::forMonth(2025, 1);

    expect($period->startAsString())->toContain('2025-01-01');
});

it('returns end as string', function (): void {
    $period = BillingPeriod::forMonth(2025, 1);

    expect($period->endAsString())->toContain('2025-01-31');
});

it('converts to array', function (): void {
    $period = BillingPeriod::forMonth(2025, 1);

    $array = $period->toArray();

    expect($array)->toHaveKeys(['start', 'end']);
    expect($array['start'])->toContain('2025-01-01');
    expect($array['end'])->toContain('2025-01-31');
});
