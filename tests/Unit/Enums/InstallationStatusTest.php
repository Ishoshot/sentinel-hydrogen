<?php

declare(strict_types=1);

use App\Enums\GitHub\InstallationStatus;

it('returns all values', function (): void {
    $values = InstallationStatus::values();

    expect($values)->toBeArray()
        ->toContain('active')
        ->toContain('suspended')
        ->toContain('uninstalled');
});

it('returns correct labels', function (): void {
    expect(InstallationStatus::Active->label())->toBe('Active');
    expect(InstallationStatus::Suspended->label())->toBe('Suspended');
    expect(InstallationStatus::Uninstalled->label())->toBe('Uninstalled');
});

it('correctly identifies usable statuses', function (): void {
    expect(InstallationStatus::Active->isUsable())->toBeTrue();
    expect(InstallationStatus::Suspended->isUsable())->toBeFalse();
    expect(InstallationStatus::Uninstalled->isUsable())->toBeFalse();
});

it('correctly identifies reactivatable statuses', function (): void {
    expect(InstallationStatus::Suspended->canReactivate())->toBeTrue();
    expect(InstallationStatus::Active->canReactivate())->toBeFalse();
    expect(InstallationStatus::Uninstalled->canReactivate())->toBeFalse();
});
