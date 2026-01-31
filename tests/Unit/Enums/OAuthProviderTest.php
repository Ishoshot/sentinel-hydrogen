<?php

declare(strict_types=1);

use App\Enums\Auth\OAuthProvider;

it('returns all values', function (): void {
    $values = OAuthProvider::values();

    expect($values)->toBeArray()
        ->toContain('github')
        ->toContain('google');
});

it('returns correct labels', function (): void {
    expect(OAuthProvider::GitHub->label())->toBe('GitHub');
    expect(OAuthProvider::Google->label())->toBe('Google');
});

it('returns correct icons', function (): void {
    expect(OAuthProvider::GitHub->icon())->toBe('github');
    expect(OAuthProvider::Google->icon())->toBe('google');
});
