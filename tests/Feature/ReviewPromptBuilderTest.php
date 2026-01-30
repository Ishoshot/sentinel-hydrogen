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
