<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Context\Filters\BinaryFileFilter;

beforeEach(function (): void {
    $this->filter = new BinaryFileFilter;
});

it('returns correct name', function (): void {
    expect($this->filter->name())->toBe('binary_file');
});

it('returns correct order', function (): void {
    expect($this->filter->order())->toBe(20);
});

it('filters out binary image files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'images/logo.png', 'additions' => 0, 'deletions' => 0],
            ['filename' => 'assets/photo.jpg', 'additions' => 0, 'deletions' => 0],
            ['filename' => 'icons/icon.svg', 'additions' => 0, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.php');
});

it('filters out lock files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'package-lock.json', 'additions' => 100, 'deletions' => 50],
            ['filename' => 'composer.lock', 'additions' => 100, 'deletions' => 50],
            ['filename' => 'yarn.lock', 'additions' => 100, 'deletions' => 50],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.php');
});

it('filters out minified files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.js', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'dist/bundle.min.js', 'additions' => 100, 'deletions' => 50],
            ['filename' => 'public/style.min.css', 'additions' => 100, 'deletions' => 50],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1)
        ->and($bag->files[0]['filename'])->toBe('src/app.js');
});

it('filters out zip and archive files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'files/archive.zip', 'additions' => 0, 'deletions' => 0],
            ['filename' => 'files/backup.tar.gz', 'additions' => 0, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1);
});

it('filters out font files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'fonts/font.woff', 'additions' => 0, 'deletions' => 0],
            ['filename' => 'fonts/font.woff2', 'additions' => 0, 'deletions' => 0],
            ['filename' => 'fonts/font.ttf', 'additions' => 0, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1);
});

it('filters out .DS_Store files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => '.DS_Store', 'additions' => 0, 'deletions' => 0],
            ['filename' => 'folder/.DS_Store', 'additions' => 0, 'deletions' => 0],
        ]
    );

    $this->filter->filter($bag);

    expect($bag->files)->toHaveCount(1);
});

it('recalculates metrics after filtering', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'src/app.php', 'additions' => 10, 'deletions' => 5],
            ['filename' => 'package-lock.json', 'additions' => 100, 'deletions' => 50],
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
