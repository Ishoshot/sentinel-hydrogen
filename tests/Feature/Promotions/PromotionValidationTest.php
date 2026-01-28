<?php

declare(strict_types=1);

use App\Actions\Promotions\ValidatePromotion;
use App\Models\Promotion;

it('validates a valid promo code', function (): void {
    $promotion = Promotion::factory()->create([
        'code' => 'SUMMER2026',
        'value_amount' => 20,
    ]);

    $action = app(ValidatePromotion::class);
    $result = $action->handle('SUMMER2026');

    expect($result['valid'])->toBeTrue()
        ->and($result['promotion'])->toBeInstanceOf(Promotion::class)
        ->and($result['promotion']->id)->toBe($promotion->id)
        ->and($result['message'])->toBeNull();
});

it('rejects an invalid promo code', function (): void {
    $action = app(ValidatePromotion::class);
    $result = $action->handle('NONEXISTENT');

    expect($result['valid'])->toBeFalse()
        ->and($result['promotion'])->toBeNull()
        ->and($result['message'])->toBe('Invalid promotion code.');
});

it('rejects an inactive promo code', function (): void {
    Promotion::factory()->inactive()->create([
        'code' => 'INACTIVE',
    ]);

    $action = app(ValidatePromotion::class);
    $result = $action->handle('INACTIVE');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toBe('This promotion is no longer active.');
});

it('rejects an expired promo code', function (): void {
    Promotion::factory()->expired()->create([
        'code' => 'EXPIRED',
    ]);

    $action = app(ValidatePromotion::class);
    $result = $action->handle('EXPIRED');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toBe('This promotion has expired.');
});

it('rejects a future promo code', function (): void {
    Promotion::factory()->future()->create([
        'code' => 'FUTURE',
    ]);

    $action = app(ValidatePromotion::class);
    $result = $action->handle('FUTURE');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toBe('This promotion is not yet active.');
});

it('rejects an exhausted promo code', function (): void {
    Promotion::factory()->exhausted()->create([
        'code' => 'EXHAUSTED',
    ]);

    $action = app(ValidatePromotion::class);
    $result = $action->handle('EXHAUSTED');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toBe('This promotion has reached its usage limit.');
});

it('is case insensitive for promo codes', function (): void {
    Promotion::factory()->create([
        'code' => 'DISCOUNT',
    ]);

    $action = app(ValidatePromotion::class);
    $result = $action->handle('discount');

    expect($result['valid'])->toBeTrue();
});

it('trims whitespace from promo codes', function (): void {
    Promotion::factory()->create([
        'code' => 'TRIMME',
    ]);

    $action = app(ValidatePromotion::class);
    $result = $action->handle('  TRIMME  ');

    expect($result['valid'])->toBeTrue();
});

it('rejects a promo code not synced to Polar', function (): void {
    Promotion::factory()->notSynced()->create([
        'code' => 'NOTSYNCED',
    ]);

    $action = app(ValidatePromotion::class);
    $result = $action->handle('NOTSYNCED');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toBe('This promotion code is not activated.');
});
