<?php

declare(strict_types=1);

use App\Services\Context\Filters\Support\AbstractTokenTruncator;
use App\Services\Context\Filters\Support\TokenLimitFilePatchTruncator;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;

beforeEach(function (): void {
    $this->tokenTruncator = new AbstractTokenTruncator(new HeuristicTokenCounter);
    $this->truncator = new TokenLimitFilePatchTruncator($this->tokenTruncator);
});

function makeFile(string $filename, ?string $patch = null): array
{
    return [
        'filename' => $filename,
        'status' => 'modified',
        'additions' => 10,
        'deletions' => 5,
        'changes' => 15,
        'patch' => $patch,
    ];
}

it('passes through files with null patches unchanged', function (): void {
    $files = [makeFile('README.md', null)];

    $result = $this->truncator->truncateFiles($files, 500, 2000);

    expect($result)->toHaveCount(1)
        ->and($result[0]['patch'])->toBeNull();
});

it('returns files unchanged when patches are within budget', function (): void {
    $files = [
        makeFile('a.php', str_repeat('a', 40)),
        makeFile('b.php', str_repeat('b', 40)),
    ];

    $result = $this->truncator->truncateFiles($files, 500, 2000);

    expect($result)->toHaveCount(2)
        ->and($result[0]['patch'])->toBe(str_repeat('a', 40))
        ->and($result[1]['patch'])->toBe(str_repeat('b', 40));
});

it('truncates a single file patch that exceeds per-file limit', function (): void {
    // 2000 chars = 500 tokens, maxTokensPerFile = 100
    $files = [makeFile('large.php', str_repeat('x', 2000))];

    $result = $this->truncator->truncateFiles($files, 100, 5000);

    expect($result[0]['patch'])->toContain('[truncated - file too large]')
        ->and(mb_strlen($result[0]['patch']))->toBeLessThan(2000);
});

it('truncates when total tokens exceed the all-files limit with remaining budget', function (): void {
    // Each 400-char patch = 100 tokens. maxTokensAllFiles = 250
    // First file: 100 tokens, second file would push to 200, third to 300 which exceeds 250
    $files = [
        makeFile('a.php', str_repeat('a', 400)),
        makeFile('b.php', str_repeat('b', 400)),
        makeFile('c.php', str_repeat('c', 400)),
    ];

    $result = $this->truncator->truncateFiles($files, 500, 250);

    // First two should be fine (200 tokens), third should be truncated to remaining budget
    expect($result)->toHaveCount(3)
        ->and($result[0]['patch'])->toBe(str_repeat('a', 400))
        ->and($result[1]['patch'])->toBe(str_repeat('b', 400));

    // Third file should either be truncated or omitted
    $thirdPatch = $result[2]['patch'];
    expect($thirdPatch)->toBeString();
});

it('omits patch when remaining budget is below MIN_SECTION_TOKENS', function (): void {
    // First file: 2000 chars = 500 tokens, maxTokensAllFiles = 510
    // Second file: remaining budget = 10, below MIN_SECTION_TOKENS (500)
    $files = [
        makeFile('a.php', str_repeat('a', 2000)),
        makeFile('b.php', str_repeat('b', 400)),
    ];

    $result = $this->truncator->truncateFiles($files, 5000, 510);

    expect($result[1]['patch'])->toBe('[patch omitted - token limit reached]');
});

it('aggressively truncates long patches beyond 2000 characters', function (): void {
    $longPatch = str_repeat('x', 5000);
    $files = [makeFile('a.php', $longPatch)];

    $result = $this->truncator->aggressiveTruncateFiles($files);

    expect($result[0]['patch'])->toEndWith("\n... [aggressively truncated]")
        ->and(mb_strlen($result[0]['patch']))->toBeLessThan(5000);
});

it('aggressively omits patches beyond 15 files', function (): void {
    $files = [];
    for ($i = 0; $i < 20; $i++) {
        $files[] = makeFile("file{$i}.php", 'short patch');
    }

    $result = $this->truncator->aggressiveTruncateFiles($files);

    // First 15 should retain their patches, rest should be omitted
    expect($result[14]['patch'])->toBe('short patch')
        ->and($result[15]['patch'])->toBe('[patch omitted - too many files]')
        ->and($result[19]['patch'])->toBe('[patch omitted - too many files]');
});

it('does not aggressively truncate files with null or empty patches', function (): void {
    $files = [
        makeFile('null.php', null),
        makeFile('empty.php', ''),
    ];

    $result = $this->truncator->aggressiveTruncateFiles($files);

    expect($result[0]['patch'])->toBeNull()
        ->and($result[1]['patch'])->toBe('');
});

it('does not count null-patch files against the 15-file limit', function (): void {
    $files = [];
    for ($i = 0; $i < 10; $i++) {
        $files[] = makeFile("null{$i}.php", null);
    }
    for ($i = 0; $i < 16; $i++) {
        $files[] = makeFile("patched{$i}.php", 'some patch');
    }

    $result = $this->truncator->aggressiveTruncateFiles($files);

    // Null patches don't count, so first 15 patched files should keep their patches
    expect($result[24]['patch'])->toBe('some patch')  // 10 null + 15th patched
        ->and($result[25]['patch'])->toBe('[patch omitted - too many files]');  // 16th patched
});

it('aggressively truncates patches at exactly 2000 characters', function (): void {
    // Patch at exactly 2000 chars should NOT be truncated (> 2000 is the condition)
    $patch = str_repeat('x', 2000);
    $files = [makeFile('exact.php', $patch)];

    $result = $this->truncator->aggressiveTruncateFiles($files);

    expect($result[0]['patch'])->toBe($patch);
});
