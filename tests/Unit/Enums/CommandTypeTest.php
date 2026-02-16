<?php

declare(strict_types=1);

use App\Enums\Commands\CommandType;

it('returns all values', function (): void {
    $values = CommandType::values();

    expect($values)->toBeArray()
        ->toContain('explain')
        ->toContain('analyze')
        ->toContain('review')
        ->toContain('summarize')
        ->toContain('find');
});

it('returns descriptions for all types', function (): void {
    foreach (CommandType::cases() as $case) {
        expect($case->description())->toBeString()->not->toBeEmpty();
    }
});
