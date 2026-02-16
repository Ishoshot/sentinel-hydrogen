<?php

declare(strict_types=1);

use App\Casts\PromotionValueCast;
use App\Enums\Promotions\PromotionValueType;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->cast = new PromotionValueCast;
    $this->model = Mockery::mock(Model::class);
});

it('gets null value', function (): void {
    $result = $this->cast->get($this->model, 'value_amount', null, ['value_type' => 'flat']);

    expect($result)->toBeNull();
});

it('gets flat value converting cents to dollars', function (): void {
    $result = $this->cast->get($this->model, 'value_amount', 1000, ['value_type' => 'flat']);

    expect($result)->toBe(10.0);
});

it('gets percentage value as integer', function (): void {
    $result = $this->cast->get($this->model, 'value_amount', 20, ['value_type' => 'percentage']);

    expect($result)->toBe(20);
});

it('gets value with enum value_type attribute', function (): void {
    $result = $this->cast->get($this->model, 'value_amount', 500, ['value_type' => PromotionValueType::Flat]);

    expect($result)->toBe(5.0);
});

it('defaults to percentage when value_type is missing', function (): void {
    $result = $this->cast->get($this->model, 'value_amount', 25, []);

    expect($result)->toBe(25);
});

it('sets null value', function (): void {
    $result = $this->cast->set($this->model, 'value_amount', null, ['value_type' => 'flat']);

    expect($result)->toBeNull();
});

it('sets flat value converting dollars to cents', function (): void {
    $result = $this->cast->set($this->model, 'value_amount', 10.00, ['value_type' => 'flat']);

    expect($result)->toBe(1000);
});

it('sets percentage value as integer', function (): void {
    $result = $this->cast->set($this->model, 'value_amount', 20, ['value_type' => 'percentage']);

    expect($result)->toBe(20);
});

it('throws for non-numeric value on set', function (): void {
    $this->cast->set($this->model, 'value_amount', 'not-a-number', ['value_type' => 'flat']);
})->throws(InvalidArgumentException::class);

it('resolves unknown string value_type to percentage', function (): void {
    $result = $this->cast->get($this->model, 'value_amount', 30, ['value_type' => 'unknown_type']);

    expect($result)->toBe(30);
});
