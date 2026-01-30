<?php

declare(strict_types=1);

use App\Models\Promotion;
use App\Services\Promotions\Contracts\PromotionValidatorContract;
use App\Services\Promotions\ValueObjects\PromotionValidationResult;

it('validates a valid promo code', function (): void {
    $promotion = Promotion::factory()->create([
        'code' => 'SUMMER2026',
        'value_amount' => 20,
    ]);

    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('SUMMER2026');

    expect($result)->toBeInstanceOf(PromotionValidationResult::class)
        ->and($result->isValid())->toBeTrue()
        ->and($result->promotion)->toBeInstanceOf(Promotion::class)
        ->and($result->promotion->id)->toBe($promotion->id)
        ->and($result->message)->toBeNull();
});

it('rejects an invalid promo code', function (): void {
    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('NONEXISTENT');

    expect($result->failed())->toBeTrue()
        ->and($result->promotion)->toBeNull()
        ->and($result->message)->toBe('Invalid promotion code.');
});

it('rejects an inactive promo code', function (): void {
    Promotion::factory()->inactive()->create([
        'code' => 'INACTIVE',
    ]);

    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('INACTIVE');

    expect($result->failed())->toBeTrue()
        ->and($result->message)->toBe('This promotion is no longer active.');
});

it('rejects an expired promo code', function (): void {
    Promotion::factory()->expired()->create([
        'code' => 'EXPIRED',
    ]);

    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('EXPIRED');

    expect($result->failed())->toBeTrue()
        ->and($result->message)->toBe('This promotion has expired.');
});

it('rejects a future promo code', function (): void {
    Promotion::factory()->future()->create([
        'code' => 'FUTURE',
    ]);

    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('FUTURE');

    expect($result->failed())->toBeTrue()
        ->and($result->message)->toBe('This promotion is not yet active.');
});

it('rejects an exhausted promo code', function (): void {
    Promotion::factory()->exhausted()->create([
        'code' => 'EXHAUSTED',
    ]);

    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('EXHAUSTED');

    expect($result->failed())->toBeTrue()
        ->and($result->message)->toBe('This promotion has reached its usage limit.');
});

it('is case insensitive for promo codes', function (): void {
    Promotion::factory()->create([
        'code' => 'DISCOUNT',
    ]);

    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('discount');

    expect($result->isValid())->toBeTrue();
});

it('trims whitespace from promo codes', function (): void {
    Promotion::factory()->create([
        'code' => 'TRIMME',
    ]);

    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('  TRIMME  ');

    expect($result->isValid())->toBeTrue();
});

it('rejects a promo code not synced to Polar', function (): void {
    Promotion::factory()->notSynced()->create([
        'code' => 'NOTSYNCED',
    ]);

    $validator = app(PromotionValidatorContract::class);
    $result = $validator->validate('NOTSYNCED');

    expect($result->failed())->toBeTrue()
        ->and($result->message)->toBe('This promotion code is not activated.');
});
