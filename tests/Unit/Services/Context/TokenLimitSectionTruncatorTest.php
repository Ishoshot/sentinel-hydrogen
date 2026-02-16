<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Context\Filters\Support\AbstractTokenTruncator;
use App\Services\Context\Filters\Support\FileContentsTruncator;
use App\Services\Context\Filters\Support\ImpactedFileTruncator;
use App\Services\Context\Filters\Support\TokenLimitCodeSectionTruncator;
use App\Services\Context\Filters\Support\TokenLimitCommentSectionTruncator;
use App\Services\Context\Filters\Support\TokenLimitContextManager;
use App\Services\Context\Filters\Support\TokenLimitFilePatchTruncator;
use App\Services\Context\Filters\Support\TokenLimitGuidelineSectionTruncator;
use App\Services\Context\Filters\Support\TokenLimitIssueSectionTruncator;
use App\Services\Context\Filters\Support\TokenLimitProgressiveTruncator;
use App\Services\Context\Filters\Support\TokenLimitProjectContextTruncator;
use App\Services\Context\Filters\Support\TokenLimitRepositoryContextTruncator;
use App\Services\Context\Filters\Support\TokenLimitReviewHistoryTruncator;
use App\Services\Context\Filters\Support\TokenLimitSectionTruncator;
use App\Services\Context\Filters\Support\TokenLimitSemanticDataTruncator;
use App\Services\Context\Filters\Support\TokenLimitSupplementalSectionTruncator;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;

beforeEach(function (): void {
    $tokenCounter = new HeuristicTokenCounter;
    $tokenTruncator = new AbstractTokenTruncator($tokenCounter);

    $filePatchTruncator = new TokenLimitFilePatchTruncator($tokenTruncator);
    $semanticDataTruncator = new TokenLimitSemanticDataTruncator($tokenTruncator);
    $impactedFileTruncator = new ImpactedFileTruncator($tokenTruncator);
    $fileContentsTruncator = new FileContentsTruncator($tokenTruncator);

    $codeSectionTruncator = new TokenLimitCodeSectionTruncator(
        $tokenTruncator,
        $semanticDataTruncator,
        $impactedFileTruncator,
        $fileContentsTruncator,
    );

    $commentTruncator = new TokenLimitCommentSectionTruncator($tokenTruncator);
    $issueTruncator = new TokenLimitIssueSectionTruncator($tokenTruncator);
    $guidelineTruncator = new TokenLimitGuidelineSectionTruncator($tokenTruncator);
    $repositoryContextTruncator = new TokenLimitRepositoryContextTruncator($tokenTruncator);
    $reviewHistoryTruncator = new TokenLimitReviewHistoryTruncator($tokenTruncator);
    $projectContextTruncator = new TokenLimitProjectContextTruncator($tokenTruncator);

    $supplementalSectionTruncator = new TokenLimitSupplementalSectionTruncator(
        $issueTruncator,
        $commentTruncator,
        $guidelineTruncator,
        $repositoryContextTruncator,
        $reviewHistoryTruncator,
        $projectContextTruncator,
    );

    $progressiveTruncator = new TokenLimitProgressiveTruncator($filePatchTruncator);

    $contextManager = new TokenLimitContextManager(
        $tokenCounter,
        $filePatchTruncator,
        $codeSectionTruncator,
        $supplementalSectionTruncator,
    );

    $this->truncator = new TokenLimitSectionTruncator(
        $tokenCounter,
        $filePatchTruncator,
        $codeSectionTruncator,
        $supplementalSectionTruncator,
        $progressiveTruncator,
        $contextManager,
    );
});

it('delegates truncateFiles to file patch truncator', function (): void {
    $files = [
        [
            'filename' => 'a.php',
            'status' => 'modified',
            'additions' => 10,
            'deletions' => 5,
            'changes' => 15,
            'patch' => str_repeat('x', 40),
        ],
    ];

    $result = $this->truncator->truncateFiles($files, 500, 2000);

    expect($result)->toHaveCount(1)
        ->and($result[0]['patch'])->toBe(str_repeat('x', 40));
});

it('delegates truncatePrComments to supplemental section truncator', function (): void {
    $comments = [
        ['author' => 'alice', 'body' => 'LGTM', 'created_at' => '2026-01-01T00:00:00Z'],
    ];

    $result = $this->truncator->truncatePrComments($comments, 1000);

    expect($result)->toHaveCount(1)
        ->and($result[0]['author'])->toBe('alice');
});

it('delegates truncateImpactedFiles to code section truncator', function (): void {
    $files = [
        [
            'file_path' => 'src/User.php',
            'content' => 'class User {}',
            'matched_symbol' => 'User',
            'match_type' => 'class',
            'score' => 0.9,
            'match_count' => 1,
            'reason' => 'Modified class',
        ],
    ];

    $result = $this->truncator->truncateImpactedFiles($files, 5000);

    expect($result)->toHaveCount(1)
        ->and($result[0]['file_path'])->toBe('src/User.php');
});

it('delegates truncateFileContents to code section truncator', function (): void {
    $contents = ['file.php' => 'some content'];

    $result = $this->truncator->truncateFileContents($contents, 5000);

    expect($result)->toHaveCount(1)
        ->and($result['file.php'])->toBe('some content');
});

it('delegates truncateSemantics to code section truncator', function (): void {
    $semantics = [
        'file.php' => ['language' => 'php', 'functions' => ['myFunc']],
    ];

    $result = $this->truncator->truncateSemantics($semantics, 5000);

    expect($result)->toHaveCount(1)
        ->and($result['file.php']['language'])->toBe('php');
});

it('estimates bag tokens', function (): void {
    $bag = new ContextBag(
        files: [
            [
                'filename' => 'test.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => 'some patch content',
            ],
        ],
    );

    $tokens = $this->truncator->estimateBagTokens($bag);

    expect($tokens)->toBeGreaterThan(0);
});

it('sets context and propagates to sub-truncators', function (): void {
    $context = TokenCounterContext::fromMetadata([]);

    // Should not throw; ensures context propagation works
    $this->truncator->setContext($context);

    // Verify it still works after context is set
    $comments = [
        ['author' => 'alice', 'body' => 'Test', 'created_at' => '2026-01-01T00:00:00Z'],
    ];

    $result = $this->truncator->truncatePrComments($comments, 1000);

    expect($result)->toHaveCount(1);
});

it('performs progressive truncation to reduce context size', function (): void {
    $bag = new ContextBag(
        prComments: [
            ['author' => 'alice', 'body' => str_repeat('x', 1000), 'created_at' => '2026-01-01T00:00:00Z'],
            ['author' => 'bob', 'body' => str_repeat('y', 1000), 'created_at' => '2026-01-01T01:00:00Z'],
        ],
        reviewHistory: [
            [
                'run_id' => 1,
                'summary' => str_repeat('s', 500),
                'findings_count' => 5,
                'severity_breakdown' => ['high' => 2, 'medium' => 3],
                'key_findings' => [],
                'created_at' => '2026-01-01T00:00:00Z',
            ],
        ],
        repositoryContext: ['readme' => str_repeat('r', 500)],
    );

    // Use a very small budget to trigger progressive truncation
    $this->truncator->progressiveTruncation($bag, 1);

    // After progressive truncation with budget of 1, most things should be cleared
    expect($bag->reviewHistory)->toBe([])
        ->and($bag->repositoryContext)->toBe([]);
});

it('delegates truncateLinkedIssues to supplemental section truncator', function (): void {
    $issues = [
        [
            'number' => 42,
            'title' => 'Fix bug',
            'body' => 'Description',
            'state' => 'open',
            'labels' => ['bug'],
            'comments' => [],
        ],
    ];

    $result = $this->truncator->truncateLinkedIssues($issues, 5000);

    expect($result)->toHaveCount(1)
        ->and($result[0]['number'])->toBe(42);
});

it('delegates truncateGuidelines to supplemental section truncator', function (): void {
    $guidelines = [
        ['path' => '.sentinel/guidelines.md', 'description' => 'Coding standards', 'content' => 'Use strict types'],
    ];

    $result = $this->truncator->truncateGuidelines($guidelines, 5000);

    expect($result)->toHaveCount(1)
        ->and($result[0]['path'])->toBe('.sentinel/guidelines.md');
});

it('delegates truncateRepositoryContext to supplemental section truncator', function (): void {
    $context = ['readme' => 'Some readme content'];

    $result = $this->truncator->truncateRepositoryContext($context, 5000);

    expect($result)->toHaveKey('readme')
        ->and($result['readme'])->toBe('Some readme content');
});

it('delegates truncateReviewHistory to supplemental section truncator', function (): void {
    $reviews = [
        [
            'run_id' => 1,
            'summary' => 'All good',
            'findings_count' => 0,
            'severity_breakdown' => [],
            'key_findings' => [],
            'created_at' => '2026-01-01T00:00:00Z',
        ],
    ];

    $result = $this->truncator->truncateReviewHistory($reviews, 5000);

    expect($result)->toHaveCount(1)
        ->and($result[0]['run_id'])->toBe(1);
});

it('delegates truncateProjectContext to supplemental section truncator', function (): void {
    $context = [
        'languages' => ['php', 'javascript'],
        'runtime' => ['name' => 'php', 'version' => '8.4'],
    ];

    $result = $this->truncator->truncateProjectContext($context, 5000);

    expect($result)->toHaveKey('languages')
        ->and($result['languages'])->toBe(['php', 'javascript']);
});
