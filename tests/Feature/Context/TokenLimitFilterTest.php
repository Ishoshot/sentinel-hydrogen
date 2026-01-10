<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Context\Filters\TokenLimitFilter;

it('truncates large individual file patches', function (): void {
    // Create a patch that exceeds the per-file limit (~8000 tokens = ~32000 chars)
    $largePatch = str_repeat('x', 40000);

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'large.php',
                'status' => 'modified',
                'additions' => 1000,
                'deletions' => 0,
                'changes' => 1000,
                'patch' => $largePatch,
            ],
        ],
    );

    $filter = new TokenLimitFilter();
    $filter->filter($bag);

    expect(mb_strlen($bag->files[0]['patch']))->toBeLessThan(40000)
        ->and($bag->files[0]['patch'])->toContain('[truncated');
});

it('preserves small file patches', function (): void {
    $smallPatch = 'function hello() { return "world"; }';

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'small.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $smallPatch,
            ],
        ],
    );

    $filter = new TokenLimitFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toBe($smallPatch);
});

it('truncates linked issues when exceeding budget', function (): void {
    $issues = [];
    // Create issues with very large bodies to exceed the token limit
    for ($i = 0; $i < 10; $i++) {
        $issues[] = [
            'number' => $i,
            'title' => 'Issue '.$i,
            'body' => str_repeat('Long issue body content here ', 2000), // ~60000 chars each
            'state' => 'open',
            'labels' => [],
            'comments' => [],
        ];
    }

    $bag = new ContextBag(linkedIssues: $issues);

    $filter = new TokenLimitFilter();
    $filter->filter($bag);

    // Should be truncated to fit within token budget
    expect(count($bag->linkedIssues))->toBeLessThan(10);
});

it('handles empty context bag', function (): void {
    $bag = new ContextBag();

    $filter = new TokenLimitFilter();
    $filter->filter($bag);

    expect($bag->files)->toBeEmpty()
        ->and($bag->linkedIssues)->toBeEmpty()
        ->and($bag->prComments)->toBeEmpty();
});

it('handles null patches gracefully', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'binary.png',
                'status' => 'added',
                'additions' => 0,
                'deletions' => 0,
                'changes' => 0,
                'patch' => null,
            ],
        ],
    );

    $filter = new TokenLimitFilter();
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toBeNull();
});

it('has correct order', function (): void {
    $filter = new TokenLimitFilter();

    expect($filter->order())->toBe(100);
});

it('has correct name', function (): void {
    $filter = new TokenLimitFilter();

    expect($filter->name())->toBe('token_limit');
});

it('truncates total file patches when exceeding total budget', function (): void {
    // Create many files that together exceed the total file token limit
    $files = [];
    for ($i = 0; $i < 30; $i++) {
        $files[] = [
            'filename' => "file{$i}.php",
            'status' => 'modified',
            'additions' => 500,
            'deletions' => 0,
            'changes' => 500,
            'patch' => str_repeat("// Code line {$i}\n", 3000), // ~15000 chars each
        ];
    }

    $bag = new ContextBag(files: $files);

    $filter = new TokenLimitFilter();
    $filter->filter($bag);

    // Check that at least some patches were truncated
    $hasLimitMessage = false;
    foreach ($bag->files as $file) {
        if (str_contains($file['patch'] ?? '', '[truncated') || str_contains($file['patch'] ?? '', '[patch omitted')) {
            $hasLimitMessage = true;
            break;
        }
    }

    expect($hasLimitMessage)->toBeTrue();
});
