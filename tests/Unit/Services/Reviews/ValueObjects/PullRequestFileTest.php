<?php

declare(strict_types=1);

use App\Services\Reviews\ValueObjects\PullRequestFile;

it('can be constructed with all parameters', function (): void {
    $file = new PullRequestFile(
        filename: 'src/app.php',
        additions: 10,
        deletions: 5,
        changes: 15,
    );

    expect($file->filename)->toBe('src/app.php');
    expect($file->additions)->toBe(10);
    expect($file->deletions)->toBe(5);
    expect($file->changes)->toBe(15);
});

it('can be created from array', function (): void {
    $file = PullRequestFile::fromArray([
        'filename' => 'tests/ExampleTest.php',
        'additions' => 20,
        'deletions' => 0,
        'changes' => 20,
    ]);

    expect($file->filename)->toBe('tests/ExampleTest.php');
    expect($file->additions)->toBe(20);
    expect($file->deletions)->toBe(0);
    expect($file->changes)->toBe(20);
});

it('converts to array correctly', function (): void {
    $file = new PullRequestFile(
        filename: 'src/app.php',
        additions: 10,
        deletions: 5,
        changes: 15,
    );

    $array = $file->toArray();

    expect($array)->toBe([
        'filename' => 'src/app.php',
        'additions' => 10,
        'deletions' => 5,
        'changes' => 15,
    ]);
});

it('roundtrips through fromArray and toArray', function (): void {
    $original = [
        'filename' => 'README.md',
        'additions' => 100,
        'deletions' => 50,
        'changes' => 150,
    ];

    $file = PullRequestFile::fromArray($original);
    $result = $file->toArray();

    expect($result)->toBe($original);
});

it('handles zero values', function (): void {
    $file = new PullRequestFile(
        filename: 'empty.txt',
        additions: 0,
        deletions: 0,
        changes: 0,
    );

    expect($file->additions)->toBe(0);
    expect($file->deletions)->toBe(0);
    expect($file->changes)->toBe(0);
});
