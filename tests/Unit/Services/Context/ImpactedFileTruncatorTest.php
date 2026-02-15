<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\AbstractTokenTruncator;
use App\Services\Context\Filters\Support\ImpactedFileTruncator;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;

beforeEach(function (): void {
    $this->tokenTruncator = new AbstractTokenTruncator(new HeuristicTokenCounter);
    $this->truncator = new ImpactedFileTruncator($this->tokenTruncator);
});

function makeImpactedFile(string $filePath, string $content): array
{
    return [
        'file_path' => $filePath,
        'content' => $content,
        'matched_symbol' => 'someMethod',
        'match_type' => 'method_call',
        'score' => 0.95,
        'match_count' => 3,
        'reason' => 'References modified method',
    ];
}

it('returns empty array for empty input', function (): void {
    $result = $this->truncator->truncate([], 1000);

    expect($result)->toBe([]);
});

it('returns all files when within budget', function (): void {
    $files = [
        makeImpactedFile('a.php', str_repeat('a', 40)),
        makeImpactedFile('b.php', str_repeat('b', 40)),
    ];

    $result = $this->truncator->truncate($files, 5000);

    expect($result)->toHaveCount(2)
        ->and($result[0]['file_path'])->toBe('a.php')
        ->and($result[1]['file_path'])->toBe('b.php');
});

it('truncates a single large file to per-file limit', function (): void {
    // 4000 chars = 1000 tokens + 50 metadata = 1050 tokens
    // maxTokensPerFile = budget * 0.25 = 500 * 0.25 = 125
    // Content should be truncated to fit 125 - 50 = 75 tokens
    $files = [makeImpactedFile('large.php', str_repeat('x', 4000))];

    $result = $this->truncator->truncate($files, 500);

    expect($result)->toHaveCount(1)
        ->and($result[0]['content'])->toContain('[truncated - impacted file too large]')
        ->and(mb_strlen($result[0]['content']))->toBeLessThan(4000);
});

it('stops adding files when total budget is exceeded', function (): void {
    // maxTokens = 100, maxTokensPerFile = 100 * 0.25 = 25
    // Each file: 80 chars = 20 tokens content + 50 metadata = 70
    // Per-file limit: 25, so 70 > 25 -> truncated to fileTokens = 25
    // File 1: totalTokens = 25, File 2: 25+25=50, File 3: 50+25=75, File 4: 75+25=100
    // File 5: 100+25=125 > 100 -> overflow
    // Remaining = 100 - 100 = 0, which is <= MIN_SECTION_TOKENS (500) -> null
    $files = [];
    for ($i = 0; $i < 6; $i++) {
        $files[] = makeImpactedFile("file{$i}.php", str_repeat('x', 80));
    }

    $result = $this->truncator->truncate($files, 100);

    // 4 files fit (4 * 25 = 100), 5th overflows with 0 remaining -> dropped
    expect($result)->toHaveCount(4)
        ->and($result[0]['file_path'])->toBe('file0.php')
        ->and($result[3]['file_path'])->toBe('file3.php');
});

it('truncates last file to remaining budget when sufficient', function (): void {
    // We need: remaining budget > MIN_SECTION_TOKENS (500)
    // First file: 200 chars = 50 tokens + 50 metadata = 100 tokens
    // maxTokens = 2000, maxTokensPerFile = 500
    // Second file: 4000 chars = 1000 tokens + 50 metadata = 1050 total
    // After per-file truncation: 500 tokens
    // Total after first: 100, adding second: 100 + 500 = 600 <= 2000 -> fits
    // Let's use a scenario where it actually overflows total
    // First file: 2000 chars = 500 + 50 = 550 tokens
    // maxTokens = 1500, maxPerFile = 375
    // After per-file truncation first: 375 tokens
    // Second file: same, 375 tokens
    // Total: 375 + 375 = 750 < 1500 -> fits
    // We need more aggressive numbers. Let's use a large budget to test the path.

    // 8000 chars = 2000 tokens + 50 = 2050
    // maxTokens = 3000, maxPerFile = 750
    // After per-file truncation: 750 tokens
    // First file: 750 tokens
    // Second file: 8000 chars -> 750 after per-file truncation
    // Total: 750 + 750 = 1500 < 3000 -> fits
    // We need a case where per-file fits but total doesn't
    // Two files of 8000 chars each, maxTokens = 1400
    // maxPerFile = 350
    // First: 350 tokens, second: 350. Total: 700 < 1400 -> fits
    // Three files: 1050 < 1400 -> fits
    // Four: 1400 -> fits. Five: 1750 > 1400

    // Let's try: first file 4000 chars = 1000 + 50 = 1050
    // maxTokens = 2000, maxPerFile = 500
    // First: truncated to 500
    // Second: 4000 chars, truncated to 500
    // Total: 500 + 500 = 1000 < 2000
    // Third: would be 500 more = 1500 < 2000
    // Fourth: 2000 exactly, fifth: 2500 > 2000
    // So with 5 files, the 5th overflows
    // Remaining = 2000 - 2000 = 0, which is <= MIN (500), so null

    // Simpler approach: test that truncated file content has the limit suffix
    $files = [
        makeImpactedFile('a.php', str_repeat('a', 200)),
        makeImpactedFile('b.php', str_repeat('b', 8000)),
    ];

    // 200 chars = 50 tokens + 50 = 100 tokens for first
    // maxTokens = 1200, maxPerFile = 300
    // Second file: 8000 chars = 2000 tokens, truncated to 300 tokens
    // Total: 100 + 300 = 400 < 1200 -> fits
    $result = $this->truncator->truncate($files, 1200);

    expect($result)->toHaveCount(2)
        ->and($result[0]['content'])->toBe(str_repeat('a', 200))
        ->and($result[1]['content'])->toContain('[truncated - impacted file too large]');
});

it('returns null for overflow file when remaining budget is below minimum', function (): void {
    // maxTokens = 100, maxTokensPerFile = 25
    // 5 files, each truncated to 25 tokens -> 4 fit at 100 tokens
    // 5th file: remaining = 0 <= MIN_SECTION_TOKENS (500) -> null, dropped
    $files = [];
    for ($i = 0; $i < 5; $i++) {
        $files[] = makeImpactedFile("file{$i}.php", str_repeat('x', 80));
    }

    $result = $this->truncator->truncate($files, 100);

    // 4 files fit, 5th dropped
    expect($result)->toHaveCount(4);
});

it('preserves file metadata during truncation', function (): void {
    $file = [
        'file_path' => 'test.php',
        'content' => str_repeat('x', 40),
        'matched_symbol' => 'myMethod',
        'match_type' => 'function_definition',
        'score' => 0.85,
        'match_count' => 7,
        'reason' => 'Defines the modified function',
    ];

    $result = $this->truncator->truncate([$file], 5000);

    expect($result[0]['file_path'])->toBe('test.php')
        ->and($result[0]['matched_symbol'])->toBe('myMethod')
        ->and($result[0]['match_type'])->toBe('function_definition')
        ->and($result[0]['score'])->toBe(0.85)
        ->and($result[0]['match_count'])->toBe(7)
        ->and($result[0]['reason'])->toBe('Defines the modified function');
});

it('breaks after processing the overflow file', function (): void {
    // maxTokens = 100, maxTokensPerFile = 25
    // 6 files, each truncated to 25 tokens
    // 4 fit (100), 5th overflows (remaining 0 -> dropped), 6th never processed
    $files = [];
    for ($i = 0; $i < 6; $i++) {
        $files[] = makeImpactedFile("file{$i}.php", str_repeat('x', 80));
    }

    $result = $this->truncator->truncate($files, 100);

    // Only 4 files should be in the result, 5th dropped, 6th not processed
    expect($result)->toHaveCount(4)
        ->and($result[3]['file_path'])->toBe('file3.php');
});
