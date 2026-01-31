<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Context\Filters\VendorPathFilter;

beforeEach(function (): void {
    $this->filter = new VendorPathFilter;
});

it('returns correct name', function (): void {
    expect($this->filter->name())->toBe('vendor_path');
});

it('returns correct order', function (): void {
    expect($this->filter->order())->toBe(10);
});

it('filters out vendor directory files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'vendor/autoload.php', 'additions' => 0, 'deletions' => 0],
            ['filename' => 'vendor/laravel/framework/src/Illuminate/Support/helpers.php', 'additions' => 0, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.php');
});

it('filters out node_modules directory files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.js', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'node_modules/lodash/index.js', 'additions' => 0, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.js');
});

it('filters out .git directory files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => '.git/config', 'additions' => 0, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1);
});

it('filters out storage directory files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'storage/logs/laravel.log', 'additions' => 100, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1);
});

it('filters out build directories', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.js', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'dist/bundle.js', 'additions' => 100, 'deletions' => 0],
            ['filename' => 'build/app.js', 'additions' => 100, 'deletions' => 0],
            ['filename' => 'public/build/manifest.json', 'additions' => 10, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1);
});

it('filters out framework cache directories', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.js', 'additions' => 10, 'deletions' => 5],
            ['filename' => '.next/cache/data.json', 'additions' => 100, 'deletions' => 0],
            ['filename' => '.nuxt/dist/server.js', 'additions' => 100, 'deletions' => 0],
            ['filename' => 'bootstrap/cache/services.php', 'additions' => 100, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1);
});

it('recalculates metrics after filtering', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'vendor/autoload.php', 'additions' => 100, 'deletions' => 50],
        ],
        metrics: ['files_changed' => 2, 'lines_added' => 110, 'lines_deleted' => 55]
    );

    $this->filter->filter($bag);

    expect($bag->metrics)->toBe([
        'files_changed' => 1,
        'lines_added' => 10,
        'lines_deleted' => 5,
    ]);
});
