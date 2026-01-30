<?php

declare(strict_types=1);

use App\Models\CodeIndex;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Context\Collectors\ImpactAnalysisCollector;
use App\Services\Context\ContextBag;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $this->installation = Installation::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->repository = Repository::factory()->create([
        'workspace_id' => $this->workspace->id,
        'installation_id' => $this->installation->id,
        'full_name' => 'test-owner/test-repo',
    ]);
    $this->run = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $this->repository->id,
        'metadata' => ['head_sha' => 'abc123'],
    ]);
});

it('has correct name and priority', function (): void {
    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    expect($collector->name())->toBe('impact_analysis')
        ->and($collector->priority())->toBe(75);
});

it('should collect when repository and run are present', function (): void {
    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    $shouldCollect = $collector->shouldCollect([
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($shouldCollect)->toBeTrue();
});

it('should not collect without repository or run', function (): void {
    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    expect($collector->shouldCollect([]))->toBeFalse()
        ->and($collector->shouldCollect(['repository' => $this->repository]))->toBeFalse()
        ->and($collector->shouldCollect(['run' => $this->run]))->toBeFalse();
});

it('skips collection when repository lacks code index', function (): void {
    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldNotReceive('keywordSearch');

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldNotReceive('getFileContents');

    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);
    $bag = new ContextBag(
        semantics: ['file.php' => ['functions' => [['name' => 'test', 'line_start' => 1, 'line_end' => 5]]]],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($bag->impactedFiles)->toBeEmpty();
});

it('skips collection when no semantics available', function (): void {
    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldNotReceive('keywordSearch');

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);
    $bag = new ContextBag();

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($bag->impactedFiles)->toBeEmpty();
});

it('extracts modified symbols from semantic data matching patch lines', function (): void {
    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldReceive('keywordSearch')
        ->once()
        ->with($this->repository, 'calculateTotal(', Mockery::any())
        ->andReturn([]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    // Patch that modifies lines 5-10
    $patch = "@@ -1,10 +1,12 @@\n context\n context\n context\n context\n+added line\n+another added\n context\n context\n context\n context";

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/Calculator.php',
                'status' => 'modified',
                'additions' => 2,
                'deletions' => 0,
                'changes' => 2,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/Calculator.php' => [
                'language' => 'php',
                'functions' => [
                    [
                        'name' => 'calculateTotal',
                        'line_start' => 5,
                        'line_end' => 10,
                        'parameters' => [],
                    ],
                    [
                        'name' => 'notModified',
                        'line_start' => 20,
                        'line_end' => 25,
                        'parameters' => [],
                    ],
                ],
                'classes' => [],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    // Only calculateTotal should be searched (notModified is outside the patch range)
    expect($bag->impactedFiles)->toBeEmpty(); // No results returned from search
});

it('searches code index for each modified symbol', function (): void {
    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldReceive('keywordSearch')
        ->once()
        ->with($this->repository, 'myFunction(', Mockery::any())
        ->andReturn([]);
    $codeSearchService->shouldReceive('keywordSearch')
        ->once()
        ->with($this->repository, 'new MyClass', Mockery::any())
        ->andReturn([]);
    $codeSearchService->shouldReceive('keywordSearch')
        ->once()
        ->with($this->repository, 'extends MyClass', Mockery::any())
        ->andReturn([]);
    $codeSearchService->shouldReceive('keywordSearch')
        ->once()
        ->with($this->repository, 'implements MyClass', Mockery::any())
        ->andReturn([]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    $patch = "@@ -1,5 +1,7 @@\n+added";

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/File.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/File.php' => [
                'language' => 'php',
                'functions' => [
                    ['name' => 'myFunction', 'line_start' => 1, 'line_end' => 5],
                ],
                'classes' => [
                    ['name' => 'MyClass', 'line_start' => 1, 'line_end' => 10],
                ],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);
});

it('excludes files already in the PR', function (): void {
    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldReceive('keywordSearch')
        ->andReturn([
            [
                'file_path' => 'src/Caller.php', // This file is in the PR
                'content' => 'function uses myFunc',
                'score' => 0.8,
            ],
            [
                'file_path' => 'src/OtherFile.php', // This file is NOT in the PR
                'content' => 'function also uses myFunc',
                'score' => 0.7,
            ],
        ]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getFileContents')
        ->once()
        ->with(
            $this->installation->installation_id,
            'test-owner',
            'test-repo',
            'src/OtherFile.php',
            'abc123'
        )
        ->andReturn('<?php // file content');

    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    $patch = "@@ -1,5 +1,7 @@\n+added";

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/Modified.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $patch,
            ],
            [
                'filename' => 'src/Caller.php', // File in PR that references the symbol
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/Modified.php' => [
                'language' => 'php',
                'functions' => [
                    ['name' => 'myFunc', 'line_start' => 1, 'line_end' => 5],
                ],
                'classes' => [],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($bag->impactedFiles)->toHaveCount(1)
        ->and($bag->impactedFiles[0]['file_path'])->toBe('src/OtherFile.php');
});

it('respects max_files configuration limit', function (): void {
    config(['reviews.impact_analysis.max_files' => 2]);

    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldReceive('keywordSearch')
        ->andReturn([
            ['file_path' => 'src/File1.php', 'content' => 'content 1', 'score' => 0.9],
            ['file_path' => 'src/File2.php', 'content' => 'content 2', 'score' => 0.8],
            ['file_path' => 'src/File3.php', 'content' => 'content 3', 'score' => 0.7],
        ]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getFileContents')->twice()->andReturn('<?php // content');

    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    $patch = "@@ -1,5 +1,7 @@\n+added";

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/Modified.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/Modified.php' => [
                'language' => 'php',
                'functions' => [
                    ['name' => 'myFunc', 'line_start' => 1, 'line_end' => 5],
                ],
                'classes' => [],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($bag->impactedFiles)->toHaveCount(2);
});

it('respects min_relevance_score configuration', function (): void {
    config(['reviews.impact_analysis.min_relevance_score' => 0.5]);

    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldReceive('keywordSearch')
        ->andReturn([
            ['file_path' => 'src/HighScore.php', 'content' => 'content', 'score' => 0.8],
            ['file_path' => 'src/LowScore.php', 'content' => 'content', 'score' => 0.3],
        ]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getFileContents')
        ->once() // Only called for high score file
        ->with(
            $this->installation->installation_id,
            'test-owner',
            'test-repo',
            'src/HighScore.php',
            'abc123'
        )
        ->andReturn('<?php // content');

    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    $patch = "@@ -1,5 +1,7 @@\n+added";

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/Modified.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/Modified.php' => [
                'language' => 'php',
                'functions' => [
                    ['name' => 'myFunc', 'line_start' => 1, 'line_end' => 5],
                ],
                'classes' => [],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($bag->impactedFiles)->toHaveCount(1)
        ->and($bag->impactedFiles[0]['file_path'])->toBe('src/HighScore.php');
});

it('prioritizes files with higher match counts', function (): void {
    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    // Simulate the same file appearing multiple times in search results
    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldReceive('keywordSearch')
        ->andReturn([
            ['file_path' => 'src/FrequentCaller.php', 'content' => 'uses myFunc', 'score' => 0.5],
            ['file_path' => 'src/FrequentCaller.php', 'content' => 'uses myFunc again', 'score' => 0.5],
            ['file_path' => 'src/FrequentCaller.php', 'content' => 'uses myFunc third time', 'score' => 0.5],
            ['file_path' => 'src/SingleCaller.php', 'content' => 'uses myFunc once', 'score' => 0.9],
        ]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getFileContents')->andReturn('<?php // content');

    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    $patch = "@@ -1,5 +1,7 @@\n+added";

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/Modified.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/Modified.php' => [
                'language' => 'php',
                'functions' => [
                    ['name' => 'myFunc', 'line_start' => 1, 'line_end' => 5],
                ],
                'classes' => [],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    // FrequentCaller should come first because it has 3 matches
    expect($bag->impactedFiles)->toHaveCount(2)
        ->and($bag->impactedFiles[0]['file_path'])->toBe('src/FrequentCaller.php')
        ->and($bag->impactedFiles[0]['match_count'])->toBe(3)
        ->and($bag->impactedFiles[1]['file_path'])->toBe('src/SingleCaller.php')
        ->and($bag->impactedFiles[1]['match_count'])->toBe(1);
});

it('populates impacted files in context bag with correct structure', function (): void {
    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldReceive('keywordSearch')
        ->andReturn([
            ['file_path' => 'src/Caller.php', 'content' => 'calls myFunc', 'score' => 0.8],
        ]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getFileContents')
        ->andReturn('<?php function caller() { myFunc(); }');

    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    $patch = "@@ -1,5 +1,7 @@\n+added";

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/Modified.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/Modified.php' => [
                'language' => 'php',
                'functions' => [
                    ['name' => 'myFunc', 'line_start' => 1, 'line_end' => 5],
                ],
                'classes' => [],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    expect($bag->impactedFiles)->toHaveCount(1)
        ->and($bag->impactedFiles[0])->toHaveKeys([
            'file_path',
            'content',
            'matched_symbol',
            'match_type',
            'score',
            'match_count',
            'reason',
        ])
        ->and($bag->impactedFiles[0]['file_path'])->toBe('src/Caller.php')
        ->and($bag->impactedFiles[0]['matched_symbol'])->toBe('myFunc')
        ->and($bag->impactedFiles[0]['match_type'])->toBe('function_call')
        ->and($bag->impactedFiles[0]['content'])->toBe('<?php function caller() { myFunc(); }')
        ->and($bag->impactedFiles[0]['reason'])->toBe('Calls function `myFunc()`');
});

it('respects max_symbols configuration limit', function (): void {
    config(['reviews.impact_analysis.max_symbols' => 2]);

    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    // Should only search for 2 symbols (first 2), not all 3
    $codeSearchService->shouldReceive('keywordSearch')->twice()->andReturn([]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    // Patch that covers all lines 1-30 so all 3 functions are "modified"
    $patch = "@@ -1,30 +1,35 @@\n".str_repeat("+added line\n", 5);

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/File.php',
                'status' => 'modified',
                'additions' => 5,
                'deletions' => 0,
                'changes' => 5,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/File.php' => [
                'language' => 'php',
                'functions' => [
                    ['name' => 'func1', 'line_start' => 1, 'line_end' => 3],
                    ['name' => 'func2', 'line_start' => 2, 'line_end' => 4],
                    ['name' => 'func3', 'line_start' => 3, 'line_end' => 5],
                ],
                'classes' => [],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);
});

it('respects max_file_size configuration limit', function (): void {
    config(['reviews.impact_analysis.max_file_size' => 100]);

    CodeIndex::factory()->create([
        'repository_id' => $this->repository->id,
        'file_path' => 'src/Service.php',
    ]);

    $codeSearchService = Mockery::mock(CodeSearchServiceContract::class);
    $codeSearchService->shouldReceive('keywordSearch')
        ->andReturn([
            ['file_path' => 'src/LargeFile.php', 'content' => 'snippet', 'score' => 0.8],
        ]);

    $gitHubService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubService->shouldReceive('getFileContents')
        ->andReturn(str_repeat('x', 200)); // Larger than 100 bytes

    $collector = new ImpactAnalysisCollector($codeSearchService, $gitHubService);

    $patch = "@@ -1,5 +1,7 @@\n+added";

    $bag = new ContextBag(
        files: [
            [
                'filename' => 'src/Modified.php',
                'status' => 'modified',
                'additions' => 1,
                'deletions' => 0,
                'changes' => 1,
                'patch' => $patch,
            ],
        ],
        semantics: [
            'src/Modified.php' => [
                'language' => 'php',
                'functions' => [
                    ['name' => 'myFunc', 'line_start' => 1, 'line_end' => 5],
                ],
                'classes' => [],
            ],
        ],
    );

    $collector->collect($bag, [
        'repository' => $this->repository,
        'run' => $this->run,
    ]);

    // File should be excluded because it's too large
    expect($bag->impactedFiles)->toBeEmpty();
});
