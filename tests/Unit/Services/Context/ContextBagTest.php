<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;

it('creates empty context bag', function (): void {
    $bag = new ContextBag;

    expect($bag->pullRequest)->toBe([])
        ->and($bag->files)->toBe([])
        ->and($bag->metrics)->toBe([])
        ->and($bag->linkedIssues)->toBe([])
        ->and($bag->prComments)->toBe([])
        ->and($bag->repositoryContext)->toBe([])
        ->and($bag->reviewHistory)->toBe([])
        ->and($bag->guidelines)->toBe([])
        ->and($bag->fileContents)->toBe([])
        ->and($bag->metadata)->toBe([]);
});

it('creates context bag with data', function (): void {
    $bag = new ContextBag(
        pullRequest: ['title' => 'Test PR', 'number' => 1],
        files: [['filename' => 'test.php', 'additions' => 10, 'deletions' => 5]],
        metrics: ['files_changed' => 1]
    );

    expect($bag->pullRequest)->toBe(['title' => 'Test PR', 'number' => 1])
        ->and($bag->files)->toHaveCount(1)
        ->and($bag->metrics)->toBe(['files_changed' => 1]);
});

it('estimates tokens for empty bag', function (): void {
    $bag = new ContextBag;

    // Empty arrays json_encode to "[]" (2 chars) * 0.25 = 0.5, ceiling = 1
    expect($bag->estimateTokens())->toBeGreaterThanOrEqual(1);
});

it('estimates tokens for bag with content', function (): void {
    $bag = new ContextBag(
        pullRequest: ['title' => 'Test PR', 'body' => 'A description'],
        files: [
            ['filename' => 'test.php', 'patch' => '@@ -1,5 +1,10 @@ function test() {}', 'additions' => 10, 'deletions' => 5],
        ],
        linkedIssues: [
            ['number' => 1, 'title' => 'Issue', 'body' => 'Description', 'state' => 'open', 'labels' => [], 'comments' => []],
        ],
        prComments: [
            ['author' => 'user', 'body' => 'A comment', 'created_at' => '2024-01-01'],
        ],
        repositoryContext: ['readme' => 'README content', 'contributing' => 'Contributing guide'],
        reviewHistory: [
            ['run_id' => 1, 'summary' => 'Review summary', 'findings_count' => 0, 'severity_breakdown' => [], 'key_findings' => [], 'created_at' => '2024-01-01'],
        ],
        guidelines: [
            ['path' => 'GUIDELINES.md', 'description' => null, 'content' => 'Guideline content'],
        ],
        fileContents: ['test.php' => '<?php echo "Hello";'],
    );

    $tokens = $bag->estimateTokens();
    expect($tokens)->toBeGreaterThan(10);
});

it('counts files with patches', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'test1.php', 'patch' => '@@ changes'],
            ['filename' => 'test2.php', 'patch' => null],
            ['filename' => 'test3.php', 'patch' => '@@ more changes'],
            ['filename' => 'test4.php'],
        ]
    );

    expect($bag->getFilesWithPatchCount())->toBe(2);
});

it('converts to array', function (): void {
    $bag = new ContextBag(
        pullRequest: ['title' => 'Test'],
        files: [['filename' => 'test.php']],
        metrics: ['files_changed' => 1],
        linkedIssues: [],
        prComments: [],
        repositoryContext: ['readme' => 'content'],
        reviewHistory: [],
        guidelines: [],
        fileContents: ['test.php' => 'content'],
        metadata: ['key' => 'value']
    );

    $array = $bag->toArray();

    expect($array)->toBeArray()
        ->and($array['pull_request'])->toBe(['title' => 'Test'])
        ->and($array['files'])->toBe([['filename' => 'test.php']])
        ->and($array['metrics'])->toBe(['files_changed' => 1])
        ->and($array['linked_issues'])->toBe([])
        ->and($array['pr_comments'])->toBe([])
        ->and($array['repository_context'])->toBe(['readme' => 'content'])
        ->and($array['review_history'])->toBe([])
        ->and($array['guidelines'])->toBe([])
        ->and($array['file_contents'])->toBe(['test.php' => 'content'])
        ->and($array['metadata'])->toBe(['key' => 'value']);
});

it('estimates tokens for semantics data', function (): void {
    $bag = new ContextBag(
        semantics: [
            'test.php' => ['functions' => ['foo', 'bar'], 'classes' => ['TestClass']],
        ],
    );

    $tokens = $bag->estimateTokens();
    expect($tokens)->toBeGreaterThan(0);
});

it('estimates tokens for project context', function (): void {
    $bag = new ContextBag(
        projectContext: [
            'languages' => ['PHP', 'JavaScript'],
            'frameworks' => [['name' => 'Laravel', 'version' => '11.0']],
            'dependencies' => [['name' => 'pest', 'version' => '2.0', 'dev' => true]],
        ],
    );

    $tokens = $bag->estimateTokens();
    expect($tokens)->toBeGreaterThan(0);
});

it('estimates tokens for impacted files', function (): void {
    $bag = new ContextBag(
        impactedFiles: [
            [
                'file_path' => 'related.php',
                'content' => 'class RelatedClass { public function useModifiedCode() {} }',
                'matched_symbol' => 'ModifiedClass',
                'match_type' => 'class_instantiation',
                'score' => 0.85,
                'match_count' => 3,
                'reason' => 'Instantiates class `ModifiedClass`',
            ],
        ],
    );

    $tokens = $bag->estimateTokens();
    expect($tokens)->toBeGreaterThan(0);
});

it('estimates tokens for linked issues with comments', function (): void {
    $bag = new ContextBag(
        linkedIssues: [
            [
                'number' => 42,
                'title' => 'Fix critical bug',
                'body' => 'This issue describes a critical bug that needs fixing',
                'state' => 'open',
                'labels' => ['bug', 'critical'],
                'comments' => [
                    ['author' => 'dev1', 'body' => 'I can reproduce this'],
                    ['author' => 'dev2', 'body' => 'Working on a fix'],
                ],
            ],
        ],
    );

    $tokens = $bag->estimateTokens();
    expect($tokens)->toBeGreaterThan(10);
});

it('recalculates metrics from files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'a.php', 'status' => 'modified', 'additions' => 10, 'deletions' => 5, 'changes' => 15, 'patch' => '+code'],
            ['filename' => 'b.php', 'status' => 'added', 'additions' => 25, 'deletions' => 0, 'changes' => 25, 'patch' => '+code'],
            ['filename' => 'c.php', 'status' => 'deleted', 'additions' => 0, 'deletions' => 15, 'changes' => 15, 'patch' => '-code'],
        ],
    );

    $bag->recalculateMetrics();

    expect($bag->metrics['files_changed'])->toBe(3);
    expect($bag->metrics['lines_added'])->toBe(35);
    expect($bag->metrics['lines_deleted'])->toBe(20);
});

it('handles null patches in files', function (): void {
    $bag = new ContextBag(
        files: [
            ['filename' => 'binary.png', 'status' => 'added', 'additions' => 0, 'deletions' => 0, 'changes' => 0, 'patch' => null],
        ],
    );

    $tokens = $bag->estimateTokens();
    expect($tokens)->toBeGreaterThanOrEqual(0);
});

it('includes all array keys in toArray output', function (): void {
    $bag = new ContextBag(
        semantics: ['file.php' => ['data' => 'value']],
        projectContext: ['languages' => ['PHP']],
        impactedFiles: [['file_path' => 'test.php', 'content' => 'content', 'matched_symbol' => 'sym', 'match_type' => 'function_call', 'score' => 0.5, 'match_count' => 1, 'reason' => 'reason']],
    );

    $array = $bag->toArray();

    expect($array)->toHaveKey('semantics');
    expect($array)->toHaveKey('project_context');
    expect($array)->toHaveKey('impacted_files');
    expect($array['semantics'])->toBe(['file.php' => ['data' => 'value']]);
    expect($array['project_context'])->toBe(['languages' => ['PHP']]);
    expect($array['impacted_files'])->toHaveCount(1);
});
