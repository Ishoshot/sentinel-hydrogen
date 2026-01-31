<?php

declare(strict_types=1);

use App\Enums\Billing\BillingInterval;

it('returns all values', function (): void {
    $values = BillingInterval::values();

    expect($values)->toBeArray()
        ->toContain('monthly')
        ->toContain('yearly');
});

it('returns correct labels', function (): void {
    expect(BillingInterval::Monthly->label())->toBe('Monthly');
    expect(BillingInterval::Yearly->label())->toBe('Yearly');
});
