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

    $filter = app(TokenLimitFilter::class);
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

    $filter = app(TokenLimitFilter::class);
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

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    // Should be truncated to fit within token budget
    expect(count($bag->linkedIssues))->toBeLessThan(10);
});

it('handles empty context bag', function (): void {
    $bag = new ContextBag();

    $filter = app(TokenLimitFilter::class);
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

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    expect($bag->files[0]['patch'])->toBeNull();
});

it('has correct order', function (): void {
    $filter = app(TokenLimitFilter::class);

    expect($filter->order())->toBe(100);
});

it('has correct name', function (): void {
    $filter = app(TokenLimitFilter::class);

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

    $filter = app(TokenLimitFilter::class);
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

it('respects context token budget metadata', function (): void {
    $bag = new ContextBag(
        guidelines: [
            [
                'path' => 'docs/guidelines.md',
                'description' => null,
                'content' => str_repeat('a', 20000),
            ],
        ],
        metadata: [
            'context_token_budget' => 10000,
        ],
    );

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    expect($bag->guidelines[0]['content'])->toContain('[truncated');
});

it('truncates impacted files when exceeding budget', function (): void {
    $impactedFiles = [];
    for ($i = 0; $i < 20; $i++) {
        $impactedFiles[] = [
            'file_path' => "src/Service{$i}.php",
            'content' => str_repeat("// Large file content line {$i}\n", 2000),
            'matched_symbol' => 'processData',
            'match_type' => 'function_call',
            'score' => 0.9 - ($i * 0.02),
            'match_count' => 5 - ($i % 5),
            'reason' => 'Calls function `processData()`',
        ];
    }

    $bag = new ContextBag(
        impactedFiles: $impactedFiles,
        metadata: ['context_token_budget' => 50000],
    );

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    // Should be truncated - either fewer files or truncated content
    $totalContent = array_sum(array_map(fn ($f) => mb_strlen($f['content']), $bag->impactedFiles));
    expect($totalContent)->toBeLessThan(20 * 2000 * 35); // Less than original
});

it('preserves small impacted files', function (): void {
    $impactedFiles = [
        [
            'file_path' => 'src/Helper.php',
            'content' => 'function helper() { return true; }',
            'matched_symbol' => 'helper',
            'match_type' => 'function_call',
            'score' => 0.85,
            'match_count' => 2,
            'reason' => 'Calls function `helper()`',
        ],
    ];

    $bag = new ContextBag(impactedFiles: $impactedFiles);

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    expect($bag->impactedFiles)->toHaveCount(1)
        ->and($bag->impactedFiles[0]['content'])->toBe('function helper() { return true; }');
});

it('truncates individual large impacted files', function (): void {
    $impactedFiles = [
        [
            'file_path' => 'src/LargeService.php',
            'content' => str_repeat('x', 100000), // Very large file
            'matched_symbol' => 'process',
            'match_type' => 'method_call',
            'score' => 0.95,
            'match_count' => 10,
            'reason' => 'Calls method `process()`',
        ],
    ];

    $bag = new ContextBag(
        impactedFiles: $impactedFiles,
        metadata: ['context_token_budget' => 50000],
    );

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    expect($bag->impactedFiles)->toHaveCount(1)
        ->and(mb_strlen($bag->impactedFiles[0]['content']))->toBeLessThan(100000)
        ->and($bag->impactedFiles[0]['content'])->toContain('[truncated');
});

it('clears impacted files during progressive truncation when over budget', function (): void {
    // Create a bag that's severely over budget with many sections filled
    $bag = new ContextBag(
        files: array_map(fn ($i) => [
            'filename' => "file{$i}.php",
            'status' => 'modified',
            'additions' => 100,
            'deletions' => 0,
            'changes' => 100,
            'patch' => str_repeat("code\n", 5000),
        ], range(1, 20)),
        impactedFiles: array_map(fn ($i) => [
            'file_path' => "impact{$i}.php",
            'content' => str_repeat("content\n", 3000),
            'matched_symbol' => 'func',
            'match_type' => 'function_call',
            'score' => 0.8,
            'match_count' => 1,
            'reason' => 'Calls function `func()`',
        ], range(1, 10)),
        linkedIssues: array_map(fn ($i) => [
            'number' => $i,
            'title' => "Issue {$i}",
            'body' => str_repeat('body ', 5000),
            'state' => 'open',
            'labels' => [],
            'comments' => [],
        ], range(1, 5)),
        metadata: ['context_token_budget' => 20000], // Very small budget
    );

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    // With such a small budget, impacted files should be reduced or cleared
    expect(count($bag->impactedFiles))->toBeLessThanOrEqual(5);
});

it('scales budgets proportionally with context window', function (): void {
    // With a large budget, files should get more tokens
    $largeBag = new ContextBag(
        files: [[
            'filename' => 'large.php',
            'status' => 'modified',
            'additions' => 1000,
            'deletions' => 0,
            'changes' => 1000,
            'patch' => str_repeat('x', 80000),
        ]],
        metadata: ['context_token_budget' => 150000], // Large budget
    );

    // With a small budget, same file should be more truncated
    $smallBag = new ContextBag(
        files: [[
            'filename' => 'large.php',
            'status' => 'modified',
            'additions' => 1000,
            'deletions' => 0,
            'changes' => 1000,
            'patch' => str_repeat('x', 80000),
        ]],
        metadata: ['context_token_budget' => 30000], // Small budget
    );

    $filter = app(TokenLimitFilter::class);
    $filter->filter($largeBag);
    $filter->filter($smallBag);

    // Large budget should preserve more content
    expect(mb_strlen($largeBag->files[0]['patch']))
        ->toBeGreaterThan(mb_strlen($smallBag->files[0]['patch']));
});

it('truncates file contents when exceeding budget', function (): void {
    $fileContents = [];
    for ($i = 0; $i < 10; $i++) {
        $fileContents["src/Service{$i}.php"] = str_repeat("// Large file {$i}\n", 3000);
    }

    $bag = new ContextBag(
        fileContents: $fileContents,
        metadata: ['context_token_budget' => 30000],
    );

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    // Should have fewer files or truncated content
    $totalContent = array_sum(array_map('mb_strlen', $bag->fileContents));
    expect($totalContent)->toBeLessThan(10 * 3000 * 18); // Less than original
});

it('preserves small file contents', function (): void {
    $fileContents = [
        'src/Helper.php' => 'function helper() { return true; }',
    ];

    $bag = new ContextBag(fileContents: $fileContents);

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    expect($bag->fileContents)->toHaveCount(1)
        ->and($bag->fileContents['src/Helper.php'])->toBe('function helper() { return true; }');
});

it('truncates semantics when exceeding budget', function (): void {
    $semantics = [];
    for ($i = 0; $i < 20; $i++) {
        $semantics["src/File{$i}.php"] = [
            'language' => 'php',
            'functions' => array_map(fn ($j) => [
                'name' => "function{$j}",
                'line_start' => $j * 10,
                'line_end' => $j * 10 + 5,
                'parameters' => [],
            ], range(1, 50)),
            'classes' => [],
            'imports' => array_map(fn ($j) => [
                'module' => "Namespace\\Class{$j}",
                'symbols' => [],
            ], range(1, 30)),
            'calls' => array_map(fn ($j) => [
                'callee' => "method{$j}",
                'line' => $j,
                'is_method_call' => true,
                'receiver' => 'this',
            ], range(1, 100)),
        ];
    }

    $bag = new ContextBag(
        semantics: $semantics,
        metadata: ['context_token_budget' => 20000],
    );

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    // Should have fewer files
    expect(count($bag->semantics))->toBeLessThan(20);
});

it('preserves small semantics', function (): void {
    $semantics = [
        'src/Helper.php' => [
            'language' => 'php',
            'functions' => [['name' => 'helper', 'line_start' => 1, 'line_end' => 3]],
            'classes' => [],
            'imports' => [],
        ],
    ];

    $bag = new ContextBag(semantics: $semantics);

    $filter = app(TokenLimitFilter::class);
    $filter->filter($bag);

    expect($bag->semantics)->toHaveCount(1)
        ->and($bag->semantics['src/Helper.php']['language'])->toBe('php');
});
