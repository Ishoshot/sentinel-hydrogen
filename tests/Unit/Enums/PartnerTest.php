<?php

declare(strict_types=1);

use App\Enums\Billing\Partner;

it('has correct cases', function (): void {
    expect(Partner::cases())->toHaveCount(2);
    expect(Partner::Polar->value)->toBe('polar');
    expect(Partner::GitHub->value)->toBe('github');
});

it('returns correct labels', function (): void {
    expect(Partner::Polar->label())->toBe('Polar');
    expect(Partner::GitHub->label())->toBe('GitHub');
});
