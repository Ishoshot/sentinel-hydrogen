<?php

declare(strict_types=1);

use App\Models\CodeIndex;
use App\Models\CommandRun;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Commands\Builders\IssueRetrievalContextBuilder;
use App\Services\Commands\CommandPathRules;
use App\Services\Context\SensitiveDataRedactor;
use App\Services\SentinelConfig\ValueObjects\PathsConfig;
use App\Support\PathRuleMatcher;

it('builds issue retrieval context from ranked search, symbol hits, and file discovery', function (): void {
    $repository = Repository::factory()->create();

    CodeIndex::factory()->forRepository($repository)->withFilePath('app/Services/QueueWorkerService.php')->create();
    CodeIndex::factory()->forRepository($repository)->withFilePath('app/Jobs/ProcessQueueBacklog.php')->create();

    $commandRun = new CommandRun([
        'query' => 'brainstorm queue worker timeout fix',
        'is_pull_request' => false,
        'issue_number' => 44,
    ]);
    $commandRun->setRelation('repository', $repository);

    $searchService = Mockery::mock(CodeSearchServiceContract::class);

    $searchService->allows('search')->andReturn([
        [
            'file_path' => 'app/Services/QueueWorkerService.php',
            'content' => 'Queue worker timeout is configured in this service and drives retry behavior.',
            'score' => 0.93,
            'match_type' => 'hybrid',
            'metadata' => [],
        ],
        [
            'file_path' => 'app/Jobs/ProcessQueueBacklog.php',
            'content' => 'Backlog processor can starve workers when timeout is too low.',
            'score' => 0.79,
            'match_type' => 'keyword',
            'metadata' => [],
        ],
    ]);

    $searchService->allows('findSymbol')->andReturn([
        [
            'file_path' => 'app/Services/QueueWorkerService.php',
            'symbol_name' => 'QueueWorkerService',
            'chunk_type' => 'class',
            'content' => 'class QueueWorkerService {}',
            'metadata' => [],
        ],
    ]);

    $pathRules = new CommandPathRules(PathsConfig::default(), new SensitiveDataRedactor(), new PathRuleMatcher());
    $builder = new IssueRetrievalContextBuilder($searchService);

    $issueContext = <<<'TXT'
## Issue Context

**Title**: Queue worker timeout spikes

**Description**:
Queue workers restart under heavy backlog.
TXT;

    $context = $builder->build($commandRun, $pathRules, $issueContext);

    expect($context)->toBeString()
        ->and($context)->toContain('## Issue Retrieval Context')
        ->and($context)->toContain('### Retrieval Intents')
        ->and($context)->toContain('### Top Code Matches')
        ->and($context)->toContain('QueueWorkerService')
        ->and($context)->toContain('### Symbol Hits')
        ->and($context)->toContain('### File Path Discovery')
        ->and($context)->toContain('app/Services/QueueWorkerService.php');
});

it('returns null when issue context is not available', function (): void {
    $repository = Repository::factory()->create();

    $commandRun = new CommandRun([
        'query' => 'summarize issue',
        'is_pull_request' => false,
        'issue_number' => 8,
    ]);
    $commandRun->setRelation('repository', $repository);

    $searchService = Mockery::mock(CodeSearchServiceContract::class);
    $builder = new IssueRetrievalContextBuilder($searchService);
    $pathRules = new CommandPathRules(PathsConfig::default(), new SensitiveDataRedactor(), new PathRuleMatcher());

    expect($builder->build($commandRun, $pathRules, null))->toBeNull();
});
