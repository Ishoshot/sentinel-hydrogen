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
