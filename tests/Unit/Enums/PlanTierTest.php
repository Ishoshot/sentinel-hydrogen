<?php

declare(strict_types=1);

use App\Enums\PlanTier;

it('returns all values', function (): void {
    $values = PlanTier::values();

    expect($values)->toBeArray()
        ->toContain('foundation')
        ->toContain('illuminate')
        ->toContain('orchestrate')
        ->toContain('sanctum');
});

it('returns correct ranks', function (): void {
    expect(PlanTier::Foundation->rank())->toBe(1)
        ->and(PlanTier::Illuminate->rank())->toBe(2)
        ->and(PlanTier::Orchestrate->rank())->toBe(3)
        ->and(PlanTier::Sanctum->rank())->toBe(4);
});

it('correctly identifies free tier', function (): void {
    expect(PlanTier::Foundation->isFree())->toBeTrue()
        ->and(PlanTier::Illuminate->isFree())->toBeFalse()
        ->and(PlanTier::Orchestrate->isFree())->toBeFalse()
        ->and(PlanTier::Sanctum->isFree())->toBeFalse();
});
