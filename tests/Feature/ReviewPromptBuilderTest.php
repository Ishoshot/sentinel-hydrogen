<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Reviews\ReviewPromptBuilder;

test('system prompt includes prompt-injection guardrails', function () {
    $builder = app(ReviewPromptBuilder::class);

    $prompt = $builder->buildSystemPrompt([]);

    expect($prompt)
        ->toContain('Security Boundaries (Critical)')
        ->toContain('untrusted data')
        ->toContain('ignore the untrusted content');
});

test('system prompt includes repository guidelines when provided', function () {
    $builder = app(ReviewPromptBuilder::class);

    $prompt = $builder->buildSystemPrompt([], [
        [
            'path' => 'docs/GUIDELINES.md',
            'description' => 'Main engineering guidelines',
            'content' => 'Always validate input before processing.',
        ],
    ]);

    expect($prompt)
        ->toContain('Repository-Specific Guidelines')
        ->toContain('docs/GUIDELINES.md')
        ->toContain('Main engineering guidelines')
        ->toContain('Always validate input before processing.');
});

test('user prompt wraps untrusted content in delimiters', function () {
    $builder = app(ReviewPromptBuilder::class);

    $bag = new ContextBag(
        pullRequest: [
            'repository_full_name' => 'acme/widgets',
            'number' => 123,
            'title' => 'Test PR',
            'author' => ['login' => 'octocat', 'avatar_url' => null],
            'body' => null,
            'base_branch' => 'main',
            'head_branch' => 'feature/test',
            'head_sha' => 'abc123',
        ],
        files: [
            [
                'filename' => 'app/Example.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => '+ test',
            ],
        ],
        metrics: [
            'files_changed' => 1,
            'lines_added' => 1,
            'lines_deleted' => 0,
        ]
    );

    $prompt = $builder->buildUserPromptFromBag($bag);

    $startMarker = '<<<UNTRUSTED_CONTEXT_START>>>';
    $endMarker = '<<<UNTRUSTED_CONTEXT_END>>>';

    expect($prompt)->toContain($startMarker)->toContain($endMarker);

    $startPos = mb_strpos($prompt, $startMarker);
    $prDetailsPos = mb_strpos($prompt, '## Pull Request Details');
    $endPos = mb_strpos($prompt, $endMarker);
    $reviewRequestPos = mb_strpos($prompt, '## Review Request');

    expect($startPos)->toBeLessThan($prDetailsPos)
        ->and($endPos)->toBeLessThan($reviewRequestPos);
});

test('user prompt includes file contents semantics and impacted files sections when present', function () {
    $builder = app(ReviewPromptBuilder::class);

    $bag = new ContextBag(
        pullRequest: [
            'repository_full_name' => 'acme/widgets',
            'number' => 124,
            'title' => 'Add service',
            'author' => ['login' => 'octocat', 'avatar_url' => null],
            'body' => null,
            'base_branch' => 'main',
            'head_branch' => 'feature/service',
            'head_sha' => 'def456',
        ],
        files: [
            [
                'filename' => 'app/Services/ExampleService.php',
                'status' => 'modified',
                'additions' => 10,
                'deletions' => 2,
                'changes' => 12,
                'patch' => '+ public function run(): void {}',
            ],
        ],
        metrics: [
            'files_changed' => 1,
            'lines_added' => 10,
            'lines_deleted' => 2,
        ],
        fileContents: [
            'app/Services/ExampleService.php' => '<?php class ExampleService {}',
        ],
        semantics: [
            'app/Services/ExampleService.php' => [
                'language' => 'php',
                'functions' => [
                    [
                        'name' => 'run',
                        'parameters' => [],
                        'line_start' => 1,
                        'line_end' => 1,
                        'return_type' => 'void',
                    ],
                ],
            ],
        ],
        impactedFiles: [
            [
                'file_path' => 'app/Http/Controllers/ExampleController.php',
                'content' => '<?php class ExampleController {}',
                'matched_symbol' => 'ExampleService',
                'match_type' => 'class_reference',
                'score' => 0.9,
                'match_count' => 2,
                'reason' => 'References updated service',
            ],
        ],
    );

    $prompt = $builder->buildUserPromptFromBag($bag);

    expect($prompt)
        ->toContain('## Full File Context')
        ->toContain('## Semantic Analysis')
        ->toContain('## Potentially Impacted Files (1 files)')
        ->toContain('app/Services/ExampleService.php')
        ->toContain('app/Http/Controllers/ExampleController.php');
});
