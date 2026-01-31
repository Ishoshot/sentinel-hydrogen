<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;

it('returns all values', function (): void {
    $values = ProviderType::values();

    expect($values)->toBeArray()
        ->toContain('github');
});

it('returns correct label', function (): void {
    expect(ProviderType::GitHub->label())->toBe('GitHub');
});

it('returns correct icon', function (): void {
    expect(ProviderType::GitHub->icon())->toBe('github');
});
