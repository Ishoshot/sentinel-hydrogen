<?php

declare(strict_types=1);

use App\Services\Reviews\ValueObjects\GitHubLabel;

it('can be constructed with name and color', function (): void {
    $label = new GitHubLabel(
        name: 'bug',
        color: 'ff0000',
    );

    expect($label->name)->toBe('bug');
    expect($label->color)->toBe('ff0000');
});

it('uses default color when not provided', function (): void {
    $label = new GitHubLabel(
        name: 'bug',
    );

    expect($label->color)->toBe('cccccc');
});

it('can be created from array with color', function (): void {
    $label = GitHubLabel::fromArray([
        'name' => 'enhancement',
        'color' => '00ff00',
    ]);

    expect($label->name)->toBe('enhancement');
    expect($label->color)->toBe('00ff00');
});

it('can be created from array without color', function (): void {
    $label = GitHubLabel::fromArray([
        'name' => 'documentation',
    ]);

    expect($label->name)->toBe('documentation');
    expect($label->color)->toBe('cccccc');
});

it('converts to array correctly', function (): void {
    $label = new GitHubLabel(
        name: 'bug',
        color: 'ff0000',
    );

    $array = $label->toArray();

    expect($array)->toBe([
        'name' => 'bug',
        'color' => 'ff0000',
    ]);
});

it('roundtrips through fromArray and toArray', function (): void {
    $original = [
        'name' => 'feature',
        'color' => '0000ff',
    ];

    $label = GitHubLabel::fromArray($original);
    $result = $label->toArray();

    expect($result)->toBe($original);
});
