<?php

declare(strict_types=1);

use App\Casts\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->cast = new MoneyCast;
    $this->model = Mockery::mock(Model::class);
});

describe('get', function (): void {
    it('returns null for null value', function (): void {
        $result = $this->cast->get($this->model, 'amount', null, []);

        expect($result)->toBeNull();
    });

    it('returns null for empty string value', function (): void {
        $result = $this->cast->get($this->model, 'amount', '', []);

        expect($result)->toBeNull();
    });

    it('returns Money object for numeric string value', function (): void {
        $result = $this->cast->get($this->model, 'amount', '1000', []);

        expect($result)->toBeInstanceOf(Money::class)
            ->and($result->getMinorAmount()->toInt())->toBe(1000)
            ->and($result->getCurrency()->getCurrencyCode())->toBe('USD');
    });

    it('returns Money object for integer value', function (): void {
        $result = $this->cast->get($this->model, 'amount', 5000, []);

        expect($result)->toBeInstanceOf(Money::class)
            ->and($result->getMinorAmount()->toInt())->toBe(5000);
    });

    it('throws exception for non-numeric value', function (): void {
        expect(fn () => $this->cast->get($this->model, 'amount', 'invalid', []))
            ->toThrow(InvalidArgumentException::class, 'Invalid money value for amount.');
    });
});

describe('set', function (): void {
    it('returns null for null value', function (): void {
        $result = $this->cast->set($this->model, 'amount', null, []);

        expect($result)->toBeNull();
    });

    it('returns minor amount for Money object', function (): void {
        $money = Money::ofMinor(2500, 'USD');
        $result = $this->cast->set($this->model, 'amount', $money, []);

        expect($result)->toBe(2500);
    });

    it('returns int for integer value', function (): void {
        $result = $this->cast->set($this->model, 'amount', 1500, []);

        expect($result)->toBe(1500);
    });

    it('returns int for numeric string value', function (): void {
        $result = $this->cast->set($this->model, 'amount', '3000', []);

        expect($result)->toBe(3000);
    });

    it('throws exception for non-numeric non-Money value', function (): void {
        expect(fn () => $this->cast->set($this->model, 'amount', 'invalid', []))
            ->toThrow(InvalidArgumentException::class, 'Invalid money value for amount.');
    });
});
