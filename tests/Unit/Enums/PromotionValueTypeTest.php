<?php

declare(strict_types=1);

use App\Enums\PromotionValueType;

it('returns all values', function (): void {
    $values = PromotionValueType::values();

    expect($values)->toBeArray()
        ->toContain('flat')
        ->toContain('percentage');
});

it('returns correct labels', function (): void {
    expect(PromotionValueType::Flat->label())->toBe('Flat Amount');
    expect(PromotionValueType::Percentage->label())->toBe('Percentage');
});
