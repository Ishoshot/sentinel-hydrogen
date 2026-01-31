<?php

declare(strict_types=1);

use App\Services\Commands\ValueObjects\LineRange;

it('can be constructed with start and end', function (): void {
    $range = new LineRange(start: 10, end: 20);

    expect($range->start)->toBe(10);
    expect($range->end)->toBe(20);
});

it('can be constructed with start only', function (): void {
    $range = new LineRange(start: 5);

    expect($range->start)->toBe(5);
    expect($range->end)->toBeNull();
});

it('identifies single line when end is null', function (): void {
    $range = new LineRange(start: 15);

    expect($range->isSingleLine())->toBeTrue();
});

it('identifies single line when end equals start', function (): void {
    $range = new LineRange(start: 10, end: 10);

    expect($range->isSingleLine())->toBeTrue();
});

it('identifies range when end differs from start', function (): void {
    $range = new LineRange(start: 10, end: 20);

    expect($range->isSingleLine())->toBeFalse();
});

it('calculates line count for single line', function (): void {
    $range = new LineRange(start: 5);

    expect($range->lineCount())->toBe(1);
});

it('calculates line count for range', function (): void {
    $range = new LineRange(start: 10, end: 20);

    expect($range->lineCount())->toBe(11);
});

it('calculates line count for same start and end', function (): void {
    $range = new LineRange(start: 5, end: 5);

    expect($range->lineCount())->toBe(1);
});

it('ensures minimum line count of 1', function (): void {
    $range = new LineRange(start: 20, end: 10);

    expect($range->lineCount())->toBe(1);
});

it('converts to array with end', function (): void {
    $range = new LineRange(start: 5, end: 15);

    expect($range->toArray())->toBe([
        'start' => 5,
        'end' => 15,
    ]);
});

it('converts to array without end', function (): void {
    $range = new LineRange(start: 10);

    expect($range->toArray())->toBe([
        'start' => 10,
        'end' => null,
    ]);
});
