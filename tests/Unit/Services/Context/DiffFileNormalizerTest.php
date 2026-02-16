<?php

declare(strict_types=1);

use App\Services\Context\Collectors\Support\DiffFileNormalizer;

beforeEach(function (): void {
    $this->normalizer = new DiffFileNormalizer;
});

it('normalizes github file data', function (): void {
    $files = [
        [
            'filename' => 'src/app.php',
            'status' => 'modified',
            'additions' => 10,
            'deletions' => 5,
            'changes' => 15,
            'patch' => '@@ -1,5 +1,10 @@',
        ],
    ];

    $result = $this->normalizer->normalize($files);

    expect($result)->toHaveCount(1);
    expect($result[0]['filename'])->toBe('src/app.php');
    expect($result[0]['status'])->toBe('modified');
    expect($result[0]['additions'])->toBe(10);
    expect($result[0]['deletions'])->toBe(5);
    expect($result[0]['changes'])->toBe(15);
    expect($result[0]['patch'])->toBe('@@ -1,5 +1,10 @@');
});

it('handles missing fields with defaults', function (): void {
    $files = [['filename' => 'readme.md']];

    $result = $this->normalizer->normalize($files);

    expect($result[0]['status'])->toBe('modified');
    expect($result[0]['additions'])->toBe(0);
    expect($result[0]['deletions'])->toBe(0);
    expect($result[0]['changes'])->toBe(0);
    expect($result[0]['patch'])->toBeNull();
});

it('calculates metrics from normalized files', function (): void {
    $files = [
        ['filename' => 'a.php', 'status' => 'modified', 'additions' => 10, 'deletions' => 5, 'changes' => 15, 'patch' => null],
        ['filename' => 'b.php', 'status' => 'added', 'additions' => 20, 'deletions' => 0, 'changes' => 20, 'patch' => null],
    ];

    $metrics = $this->normalizer->calculateMetrics($files);

    expect($metrics['files_changed'])->toBe(2);
    expect($metrics['lines_added'])->toBe(30);
    expect($metrics['lines_deleted'])->toBe(5);
});
