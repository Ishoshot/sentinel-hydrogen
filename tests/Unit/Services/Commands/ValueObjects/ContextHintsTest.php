<?php

declare(strict_types=1);

use App\Services\Commands\ValueObjects\ContextHints;
use App\Services\Commands\ValueObjects\LineRange;

it('can be constructed with all parameters', function (): void {
    $lines = [new LineRange(10, 20)];
    $hints = new ContextHints(
        files: ['src/app.php', 'tests/AppTest.php'],
        symbols: ['MyClass', 'myFunction'],
        lines: $lines,
    );

    expect($hints->files)->toBe(['src/app.php', 'tests/AppTest.php']);
    expect($hints->symbols)->toBe(['MyClass', 'myFunction']);
    expect($hints->lines)->toBe($lines);
});

it('can be constructed with default empty values', function (): void {
    $hints = new ContextHints();

    expect($hints->files)->toBe([]);
    expect($hints->symbols)->toBe([]);
    expect($hints->lines)->toBe([]);
});

it('creates from array with all fields', function (): void {
    $hints = ContextHints::fromArray([
        'files' => ['file1.php', 'file2.php'],
        'symbols' => ['Symbol1'],
        'lines' => [
            ['start' => 10, 'end' => 20],
            ['start' => 30, 'end' => null],
        ],
    ]);

    expect($hints->files)->toBe(['file1.php', 'file2.php']);
    expect($hints->symbols)->toBe(['Symbol1']);
    expect($hints->lines)->toHaveCount(2);
    expect($hints->lines[0]->start)->toBe(10);
    expect($hints->lines[0]->end)->toBe(20);
    expect($hints->lines[1]->start)->toBe(30);
    expect($hints->lines[1]->end)->toBeNull();
});

it('creates from array with partial fields', function (): void {
    $hints = ContextHints::fromArray([
        'files' => ['only-file.php'],
    ]);

    expect($hints->files)->toBe(['only-file.php']);
    expect($hints->symbols)->toBe([]);
    expect($hints->lines)->toBe([]);
});

it('creates from empty array', function (): void {
    $hints = ContextHints::fromArray([]);

    expect($hints->files)->toBe([]);
    expect($hints->symbols)->toBe([]);
    expect($hints->lines)->toBe([]);
});

it('creates empty instance', function (): void {
    $hints = ContextHints::empty();

    expect($hints->files)->toBe([]);
    expect($hints->symbols)->toBe([]);
    expect($hints->lines)->toBe([]);
});

it('detects when it has files', function (): void {
    $hints = new ContextHints(files: ['file.php']);

    expect($hints->hasAny())->toBeTrue();
});

it('detects when it has symbols', function (): void {
    $hints = new ContextHints(symbols: ['MyClass']);

    expect($hints->hasAny())->toBeTrue();
});

it('detects when it has lines', function (): void {
    $hints = new ContextHints(lines: [new LineRange(1)]);

    expect($hints->hasAny())->toBeTrue();
});

it('detects when it has no hints', function (): void {
    $hints = new ContextHints();

    expect($hints->hasAny())->toBeFalse();
});

it('converts to array', function (): void {
    $hints = new ContextHints(
        files: ['app.php'],
        symbols: ['Class'],
        lines: [new LineRange(5, 10)],
    );

    $array = $hints->toArray();

    expect($array)->toBe([
        'files' => ['app.php'],
        'symbols' => ['Class'],
        'lines' => [
            ['start' => 5, 'end' => 10],
        ],
    ]);
});

it('converts empty hints to array', function (): void {
    $hints = ContextHints::empty();

    expect($hints->toArray())->toBe([
        'files' => [],
        'symbols' => [],
        'lines' => [],
    ]);
});
