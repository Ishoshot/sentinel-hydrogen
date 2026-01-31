<?php

declare(strict_types=1);

use App\Enums\ConnectionStatus;

it('returns all values', function (): void {
    $values = ConnectionStatus::values();

    expect($values)->toBeArray()
        ->toContain('pending')
        ->toContain('active')
        ->toContain('disconnected')
        ->toContain('failed');
});

it('returns correct labels', function (): void {
    expect(ConnectionStatus::Pending->label())->toBe('Pending');
    expect(ConnectionStatus::Active->label())->toBe('Active');
    expect(ConnectionStatus::Disconnected->label())->toBe('Disconnected');
    expect(ConnectionStatus::Failed->label())->toBe('Failed');
});

it('correctly identifies usable statuses', function (): void {
    expect(ConnectionStatus::Active->isUsable())->toBeTrue();
    expect(ConnectionStatus::Pending->isUsable())->toBeFalse();
    expect(ConnectionStatus::Disconnected->isUsable())->toBeFalse();
    expect(ConnectionStatus::Failed->isUsable())->toBeFalse();
});

it('correctly identifies reconnectable statuses', function (): void {
    expect(ConnectionStatus::Disconnected->canReconnect())->toBeTrue();
    expect(ConnectionStatus::Failed->canReconnect())->toBeTrue();
    expect(ConnectionStatus::Active->canReconnect())->toBeFalse();
    expect(ConnectionStatus::Pending->canReconnect())->toBeFalse();
});
