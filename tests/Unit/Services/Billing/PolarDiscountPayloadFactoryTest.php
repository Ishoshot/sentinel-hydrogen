<?php

declare(strict_types=1);

use App\Enums\Promotions\PromotionValueType;
use App\Models\Promotion;
use App\Services\Billing\Factories\PolarDiscountPayloadFactory;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->factory = new PolarDiscountPayloadFactory;
});

it('builds create payload for percentage promotion', function (): void {
    $validFrom = Carbon::parse('2026-01-01T00:00:00+00:00');
    $validTo = Carbon::parse('2026-12-31T23:59:59+00:00');

    $promotion = (new Promotion)->forceFill([
        'name' => 'Summer Sale',
        'code' => 'SUMMER20',
        'value_type' => PromotionValueType::Percentage,
        'value_amount' => 20,
        'max_uses' => 100,
        'valid_from' => $validFrom,
        'valid_to' => $validTo,
    ]);

    $payload = $this->factory->buildCreatePayload($promotion);

    expect($payload['name'])->toBe('Summer Sale');
    expect($payload['code'])->toBe('SUMMER20');
    expect($payload['type'])->toBe('percentage');
    expect($payload['amount'])->toBe(20);
    expect($payload['basis_points'])->toBe(2000);
    expect($payload['fixed_amount'])->toBeNull();
    expect($payload['duration'])->toBe('once');
    expect($payload['max_redemptions'])->toBe(100);
    expect($payload['starts_at'])->toBe($validFrom->format('c'));
    expect($payload['ends_at'])->toBe($validTo->format('c'));
});

it('builds create payload for flat promotion', function (): void {
    $promotion = new Promotion;
    $promotion->forceFill([
        'name' => '$10 Off',
        'code' => 'FLAT10',
        'value_type' => PromotionValueType::Flat,
        'value_amount' => 10,
        'max_uses' => null,
        'valid_from' => null,
        'valid_to' => null,
    ]);
    $promotion->syncOriginal();

    $payload = $this->factory->buildCreatePayload($promotion);

    expect($payload['type'])->toBe('fixed');
    expect($payload['amount'])->toBeNull();
    expect($payload['basis_points'])->toBeNull();
    expect($payload['fixed_amount'])->toBe(1000);
    expect($payload['max_redemptions'])->toBeNull();
    expect($payload['starts_at'])->toBeNull();
    expect($payload['ends_at'])->toBeNull();
});

it('builds update payload with all fields', function (): void {
    $validFrom = Carbon::parse('2026-03-01T00:00:00+00:00');
    $validTo = Carbon::parse('2026-06-30T23:59:59+00:00');

    $promotion = (new Promotion)->forceFill([
        'name' => 'Updated Promo',
        'code' => 'UPDATED',
        'max_uses' => 50,
        'valid_from' => $validFrom,
        'valid_to' => $validTo,
    ]);

    $payload = $this->factory->buildUpdatePayload($promotion);

    expect($payload)->toBe([
        'name' => 'Updated Promo',
        'code' => 'UPDATED',
        'max_redemptions' => 50,
        'starts_at' => $validFrom->format('c'),
        'ends_at' => $validTo->format('c'),
    ]);
});

it('builds update payload with null dates', function (): void {
    $promotion = (new Promotion)->forceFill([
        'name' => 'No Dates',
        'code' => 'NODATES',
        'max_uses' => null,
        'valid_from' => null,
        'valid_to' => null,
    ]);

    $payload = $this->factory->buildUpdatePayload($promotion);

    expect($payload['starts_at'])->toBeNull();
    expect($payload['ends_at'])->toBeNull();
    expect($payload['max_redemptions'])->toBeNull();
});

it('sets duration to once in create payload', function (): void {
    $promotion = (new Promotion)->forceFill([
        'name' => 'Test',
        'code' => 'TEST',
        'value_type' => PromotionValueType::Percentage,
        'value_amount' => 10,
        'max_uses' => null,
        'valid_from' => null,
        'valid_to' => null,
    ]);

    $payload = $this->factory->buildCreatePayload($promotion);

    expect($payload['duration'])->toBe('once');
});
