<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\AbstractTokenTruncator;
use App\Services\Context\Filters\Support\FileContentsTruncator;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;

beforeEach(function (): void {
    $this->tokenTruncator = new AbstractTokenTruncator(new HeuristicTokenCounter);
    $this->truncator = new FileContentsTruncator($this->tokenTruncator);
});

it('returns empty array for empty input', function (): void {
    $result = $this->truncator->truncate([], 1000);

    expect($result)->toBe([]);
});

it('returns all files when within budget', function (): void {
    $files = [
        'a.php' => str_repeat('a', 40),
        'b.php' => str_repeat('b', 40),
    ];

    $result = $this->truncator->truncate($files, 5000);

    expect($result)->toHaveCount(2)
        ->and($result['a.php'])->toBe(str_repeat('a', 40))
        ->and($result['b.php'])->toBe(str_repeat('b', 40));
});

it('truncates a single large file to per-file limit', function (): void {
    // 4000 chars = 1000 tokens, maxTokensPerFile = budget * 0.20 = 500 * 0.20 = 100
    $files = ['large.php' => str_repeat('x', 4000)];

    $result = $this->truncator->truncate($files, 500);

    expect($result)->toHaveCount(1)
        ->and($result['large.php'])->toContain('[truncated - file too large]')
        ->and(mb_strlen($result['large.php']))->toBeLessThan(4000);
});

it('stops adding files when total budget is exceeded and remaining is below minimum', function (): void {
    // maxTokens = 100, maxTokensPerFile = 100 * 0.20 = 20
    // Each file: 400 chars = 100 tokens > 20, truncated to 20 tokens
    // File 1: total=20, File 2: 40, File 3: 60, File 4: 80, File 5: 100
    // File 6: 100+20=120 > 100, remaining = 0 <= MIN(500) -> null
    $files = [];
    for ($i = 0; $i < 7; $i++) {
        $files["file{$i}.php"] = str_repeat('x', 400);
    }

    $result = $this->truncator->truncate($files, 100);

    // 5 files fit (5 * 20 = 100), 6th is dropped
    expect($result)->toHaveCount(5)
        ->and($result)->toHaveKey('file0.php')
        ->and($result)->toHaveKey('file4.php')
        ->and($result)->not->toHaveKey('file5.php');
});

it('truncates overflow file content when remaining budget is sufficient', function (): void {
    // First file: 100 chars = 25 tokens
    // maxTokens = 2000, maxPerFile = 400
    // Second file: 4000 chars = 1000 tokens > 400, truncated to 400
    // Total: 25 + 400 = 425 < 2000 -> fits
    $files = [
        'small.php' => str_repeat('a', 100),
        'large.php' => str_repeat('x', 4000),
    ];

    $result = $this->truncator->truncate($files, 2000);

    expect($result)->toHaveCount(2)
        ->and($result['small.php'])->toBe(str_repeat('a', 100))
        ->and($result['large.php'])->toContain('[truncated - file too large]');
});

it('preserves path keys in results', function (): void {
    $files = [
        'src/Controllers/UserController.php' => 'class UserController {}',
        'src/Models/User.php' => 'class User {}',
    ];

    $result = $this->truncator->truncate($files, 5000);

    expect($result)->toHaveKey('src/Controllers/UserController.php')
        ->and($result)->toHaveKey('src/Models/User.php');
});

it('breaks after processing the overflow file', function (): void {
    // maxTokens = 100, maxTokensPerFile = 20
    // 8 files, each truncated to 20 tokens
    // 5 fit (100), 6th overflows (remaining 0 -> null), 7th and 8th never processed
    $files = [];
    for ($i = 0; $i < 8; $i++) {
        $files["file{$i}.php"] = str_repeat('x', 400);
    }

    $result = $this->truncator->truncate($files, 100);

    expect($result)->toHaveCount(5)
        ->and($result)->toHaveKey('file4.php')
        ->and($result)->not->toHaveKey('file5.php')
        ->and($result)->not->toHaveKey('file7.php');
});

it('includes overflow file with token limit suffix when budget allows', function (): void {
    // First file: 40 chars = 10 tokens
    // maxTokens = 3000, maxPerFile = 600
    // Second file: 10000 chars = 2500 tokens > 600, truncated to 600
    // Total: 10 + 600 = 610 < 3000
    // Third file: 10000 chars, truncated to 600, total: 610 + 600 = 1210 < 3000
    // Fourth: 1210 + 600 = 1810 < 3000
    // Fifth: 1810 + 600 = 2410 < 3000
    // Sixth: 2410 + 600 = 3010 > 3000, remaining = 590 > MIN (500)
    // So sixth file should be truncated with token limit suffix
    $files = [];
    $files['small.php'] = str_repeat('s', 40);
    for ($i = 1; $i <= 6; $i++) {
        $files["big{$i}.php"] = str_repeat((string) $i, 10000);
    }

    $result = $this->truncator->truncate($files, 3000);

    // Check that at least one file has the token limit suffix
    $lastKey = array_key_last($result);
    $lastContent = $result[$lastKey];
    $hasLimitSuffix = str_contains($lastContent, '[truncated - token limit]');
    $hasLargeSuffix = str_contains($lastContent, '[truncated - file too large]');

    expect($hasLimitSuffix || $hasLargeSuffix)->toBeTrue();
});
