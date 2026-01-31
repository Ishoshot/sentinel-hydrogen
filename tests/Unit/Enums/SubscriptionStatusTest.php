<?php

declare(strict_types=1);

use App\Enums\Billing\SubscriptionStatus;

it('returns all values', function (): void {
    $values = SubscriptionStatus::values();

    expect($values)->toBeArray()
        ->toContain('active')
        ->toContain('trialing')
        ->toContain('past_due')
        ->toContain('canceled');
});

it('correctly identifies active statuses', function (): void {
    expect(SubscriptionStatus::Active->isActive())->toBeTrue();
    expect(SubscriptionStatus::Trialing->isActive())->toBeTrue();
    expect(SubscriptionStatus::PastDue->isActive())->toBeFalse();
    expect(SubscriptionStatus::Canceled->isActive())->toBeFalse();
});
