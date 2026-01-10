<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Context\Filters\RelevanceFilter;

it('prioritizes app files over vendor files', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'docs/README.md',
                'status' => 'modified',
                'additions' => 10,
                'deletions' => 5,
                'changes' => 15,
                'patch' => 'documentation changes',
            ],
            [
                'filename' => 'app/Models/User.php',
                'status' => 'modified',
                'additions' => 20,
                'deletions' => 10,
                'changes' => 30,
                'patch' => 'model changes',
            ],
        ],
    );

    $filter = new RelevanceFilter();
    $filter->filter($bag);

    // App files should come first
    expect($bag->files[0]['filename'])->toBe('app/Models/User.php');
});

it('prioritizes migrations over seeders', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'database/seeders/UserSeeder.php',
                'status' => 'added',
                'additions' => 50,
                'deletions' => 0,
                'changes' => 50,
                'patch' => 'seeder code',
            ],
            [
                'filename' => 'database/migrations/2024_01_01_create_users_table.php',
                'status' => 'added',
                'additions' => 50,
                'deletions' => 0,
                'changes' => 50,
                'patch' => 'migration code',
            ],
        ],
    );

    $filter = new RelevanceFilter();
    $filter->filter($bag);

    // Migrations should come first (higher priority)
    expect($bag->files[0]['filename'])->toContain('migrations');
});

it('boosts files with more changes', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/smallFile.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 1,
                'changes' => 2,
                'patch' => 'x',
            ],
            [
                'filename' => 'src/largeFile.php',
                'status' => 'modified',
                'additions' => 100,
                'deletions' => 50,
                'changes' => 150,
                'patch' => str_repeat('x', 1000),
            ],
        ],
    );

    $filter = new RelevanceFilter();
    $filter->filter($bag);

    // Larger changes should come first
    expect($bag->files[0]['filename'])->toBe('src/largeFile.php');
});

it('limits files to maximum count', function (): void {
    $files = [];
    for ($i = 0; $i < 100; $i++) {
        $files[] = [
            'filename' => "app/file{$i}.php",
            'status' => 'modified',
            'additions' => 10,
            'deletions' => 5,
            'changes' => 15,
            'patch' => 'code',
        ];
    }

    $bag = new ContextBag(files: $files);

    $filter = new RelevanceFilter();
    $filter->filter($bag);

    // Should be limited to 50 files
    expect(count($bag->files))->toBeLessThanOrEqual(50);
});

it('updates metrics after filtering', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'app/Test.php',
                'status' => 'modified',
                'additions' => 10,
                'deletions' => 5,
                'changes' => 15,
                'patch' => 'code',
            ],
            [
                'filename' => 'docs/README.md',
                'status' => 'modified',
                'additions' => 20,
                'deletions' => 10,
                'changes' => 30,
                'patch' => 'docs',
            ],
        ],
        metrics: [
            'files_changed' => 2,
            'lines_added' => 30,
            'lines_deleted' => 15,
        ],
    );

    $filter = new RelevanceFilter();
    $filter->filter($bag);

    expect($bag->metrics['files_changed'])->toBe(2)
        ->and($bag->metrics['lines_added'])->toBe(30)
        ->and($bag->metrics['lines_deleted'])->toBe(15);
});

it('handles empty files array', function (): void {
    $bag = new ContextBag(files: []);

    $filter = new RelevanceFilter();
    $filter->filter($bag);

    expect($bag->files)->toBe([]);
});

it('prioritizes source over tests', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'tests/Feature/UserTest.php',
                'status' => 'modified',
                'additions' => 50,
                'deletions' => 20,
                'changes' => 70,
                'patch' => 'test code',
            ],
            [
                'filename' => 'app/Models/User.php',
                'status' => 'modified',
                'additions' => 50,
                'deletions' => 20,
                'changes' => 70,
                'patch' => 'model code',
            ],
        ],
    );

    $filter = new RelevanceFilter();
    $filter->filter($bag);

    // Source files should come before test files
    expect($bag->files[0]['filename'])->toBe('app/Models/User.php');
});

it('has correct order', function (): void {
    $filter = new RelevanceFilter();

    expect($filter->order())->toBe(40);
});

it('has correct name', function (): void {
    $filter = new RelevanceFilter();

    expect($filter->name())->toBe('relevance');
});
