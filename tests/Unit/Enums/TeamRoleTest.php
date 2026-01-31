<?php

declare(strict_types=1);

use App\Enums\TeamRole;

it('returns all values', function (): void {
    $values = TeamRole::values();

    expect($values)->toBeArray()
        ->toContain('owner')
        ->toContain('admin')
        ->toContain('member');
});

it('returns assignable roles', function (): void {
    $roles = TeamRole::assignableRoles();

    expect($roles)->toBeArray()
        ->toContain('admin')
        ->toContain('member')
        ->not->toContain('owner');
});

it('returns correct labels', function (): void {
    expect(TeamRole::Owner->label())->toBe('Owner');
    expect(TeamRole::Admin->label())->toBe('Admin');
    expect(TeamRole::Member->label())->toBe('Member');
});

it('correctly identifies member management capability', function (): void {
    expect(TeamRole::Owner->canManageMembers())->toBeTrue();
    expect(TeamRole::Admin->canManageMembers())->toBeTrue();
    expect(TeamRole::Member->canManageMembers())->toBeFalse();
});

it('correctly identifies settings management capability', function (): void {
    expect(TeamRole::Owner->canManageSettings())->toBeTrue();
    expect(TeamRole::Admin->canManageSettings())->toBeTrue();
    expect(TeamRole::Member->canManageSettings())->toBeFalse();
});

it('correctly identifies workspace deletion capability', function (): void {
    expect(TeamRole::Owner->canDeleteWorkspace())->toBeTrue();
    expect(TeamRole::Admin->canDeleteWorkspace())->toBeFalse();
    expect(TeamRole::Member->canDeleteWorkspace())->toBeFalse();
});

it('correctly identifies ownership transfer capability', function (): void {
    expect(TeamRole::Owner->canTransferOwnership())->toBeTrue();
    expect(TeamRole::Admin->canTransferOwnership())->toBeFalse();
    expect(TeamRole::Member->canTransferOwnership())->toBeFalse();
});
