<?php

declare(strict_types=1);

use App\Enums\Commands\CommandRunStatus;
use App\Enums\Commands\CommandType;
use App\Models\CommandRun;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Resolvers\IssueApiParameterResolver;
use App\Services\Commands\Resolvers\IssueLinkedReferencesResolver;
use App\Services\Commands\Resolvers\IssueSnapshotResolver;
use App\Services\Commands\Resolvers\SimilarRunsAndFindingsResolver;
use App\Services\Commands\Tools\GetIssueCommentsTool;
use App\Services\Commands\Tools\GetIssueTimelineTool;
use App\Services\Commands\Tools\GetLinkedIssueReferencesTool;
use App\Services\Commands\Tools\SearchSimilarRunsOrFindingsTool;
use App\Services\Commands\Tools\ToolResultFormatter;
use App\Services\Context\SensitiveDataRedactor;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\SentinelConfig\ValueObjects\PathsConfig;
use App\Support\PathRuleMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->repository = Repository::factory()->create([
        'full_name' => 'owner/repo',
        'name' => 'repo',
    ]);

    $this->issueCommandRun = CommandRun::factory()->create([
        'workspace_id' => $this->repository->workspace_id,
        'repository_id' => $this->repository->id,
        'command_type' => CommandType::Explain,
        'status' => CommandRunStatus::Queued,
        'is_pull_request' => false,
        'issue_number' => 77,
        'query' => 'investigate queue timeout',
    ]);

    $this->pullRequestCommandRun = CommandRun::factory()->create([
        'workspace_id' => $this->repository->workspace_id,
        'repository_id' => $this->repository->id,
        'command_type' => CommandType::Explain,
        'status' => CommandRunStatus::Queued,
        'is_pull_request' => true,
        'issue_number' => 77,
    ]);
});

function issuePathRules(): CommandPathRules
{
    return new CommandPathRules(
        PathsConfig::default(),
        new SensitiveDataRedactor(),
        new PathRuleMatcher()
    );
}

it('returns unavailable message for issue tools when command is from a pull request', function (): void {
    $gitHubApiService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubApiService->shouldNotReceive('getIssueComments');
    $gitHubApiService->shouldNotReceive('getIssue');

    $tool = (new GetIssueCommentsTool(
        new IssueSnapshotResolver($gitHubApiService, new IssueApiParameterResolver()),
        new ToolResultFormatter(),
    ))->build($this->pullRequestCommandRun, issuePathRules());

    $result = $tool->handle();

    expect($result)->toContain('Issue context unavailable');
});

it('formats recent issue comments and applies redaction', function (): void {
    $gitHubApiService = Mockery::mock(GitHubApiServiceContract::class);
    $installationId = $this->repository->installation->installation_id;

    $gitHubApiService->shouldReceive('getIssue')
        ->once()
        ->with($installationId, 'owner', 'repo', 77)
        ->andReturn([
            'title' => 'Queue issue',
            'body' => 'Issue body',
        ]);

    $gitHubApiService->shouldReceive('getIssueComments')
        ->once()
        ->with($installationId, 'owner', 'repo', 77)
        ->andReturn([
            [
                'user' => ['login' => 'alice'],
                'body' => 'Initial triage note.',
                'created_at' => '2026-02-18T10:00:00Z',
            ],
            [
                'user' => ['login' => 'bob'],
                'body' => 'Leaked token ghp_abcdefghijklmnopqrstuvwxyz0123456789012 needs rotation.',
                'created_at' => '2026-02-18T11:00:00Z',
            ],
        ]);

    $tool = (new GetIssueCommentsTool(
        new IssueSnapshotResolver($gitHubApiService, new IssueApiParameterResolver()),
        new ToolResultFormatter(),
    ))->build($this->issueCommandRun, issuePathRules());

    $result = $tool->handle(1);

    expect($result)
        ->toContain('Recent issue comments')
        ->toContain('@bob')
        ->toContain('[REDACTED:github_token:')
        ->not->toContain('ghp_abcdefghijklmnopqrstuvwxyz0123456789012')
        ->not->toContain('@alice');
});

it('formats recent issue timeline events with commit metadata', function (): void {
    $gitHubApiService = Mockery::mock(GitHubApiServiceContract::class);
    $installationId = $this->repository->installation->installation_id;

    $gitHubApiService->shouldReceive('getIssue')
        ->once()
        ->with($installationId, 'owner', 'repo', 77)
        ->andReturn([
            'title' => 'Queue issue',
            'body' => 'Issue body',
        ]);

    $gitHubApiService->shouldReceive('getIssueComments')
        ->once()
        ->with($installationId, 'owner', 'repo', 77)
        ->andReturn([]);

    $gitHubApiService->shouldReceive('getIssueTimeline')
        ->once()
        ->with($installationId, 'owner', 'repo', 77)
        ->andReturn([
            [
                'event' => 'referenced',
                'actor' => ['login' => 'maintainer'],
                'created_at' => '2026-02-18T12:00:00Z',
                'commit_id' => 'aabbccddeeff00112233445566778899aabbccdd',
            ],
        ]);

    $tool = (new GetIssueTimelineTool(
        new IssueSnapshotResolver($gitHubApiService, new IssueApiParameterResolver()),
        new ToolResultFormatter(),
    ))->build($this->issueCommandRun, issuePathRules());

    $result = $tool->handle();

    expect($result)
        ->toContain('Recent issue timeline events')
        ->toContain('referenced by @maintainer')
        ->toContain('commit: aabbccddeeff');
});

it('resolves linked pull requests and commits from issue data', function (): void {
    $gitHubApiService = Mockery::mock(GitHubApiServiceContract::class);
    $installationId = $this->repository->installation->installation_id;

    $gitHubApiService->shouldReceive('getIssue')
        ->once()
        ->with($installationId, 'owner', 'repo', 77)
        ->andReturn([
            'body' => 'Related implementation https://github.com/acme/app/pull/42',
        ]);

    $gitHubApiService->shouldReceive('getIssueComments')
        ->once()
        ->with($installationId, 'owner', 'repo', 77)
        ->andReturn([
            ['body' => 'Investigated in https://github.com/acme/app/commit/abcdef1234567890'],
        ]);

    $gitHubApiService->shouldReceive('getIssueTimeline')
        ->once()
        ->with($installationId, 'owner', 'repo', 77)
        ->andReturn([]);

    $tool = (new GetLinkedIssueReferencesTool(
        new IssueSnapshotResolver($gitHubApiService, new IssueApiParameterResolver()),
        new IssueLinkedReferencesResolver(),
    ))->build($this->issueCommandRun, issuePathRules());

    $result = $tool->handle();

    expect($result)
        ->toContain('Linked references:')
        ->toContain('#42')
        ->toContain('abcdef123456');
});

it('formats similar historical runs and findings from resolver output', function (): void {
    $run = Run::factory()
        ->forRepository($this->repository)
        ->create([
            'status' => App\Enums\Reviews\RunStatus::Completed,
            'pr_number' => 18,
            'pr_title' => 'Stabilize queue worker retries',
            'base_branch' => 'main',
            'head_branch' => 'feature/queue',
        ]);

    Finding::factory()
        ->forRun($run)
        ->create([
            'title' => 'Queue worker timeout mismatch',
            'file_path' => 'app/Jobs/ProcessQueue.php',
            'confidence' => 0.91,
        ]);

    $tool = (new SearchSimilarRunsOrFindingsTool(new SimilarRunsAndFindingsResolver()))
        ->build($this->issueCommandRun, issuePathRules());

    $result = $tool->handle('timeout reliability', 3, 2);

    expect($result)
        ->toContain('Similar historical context')
        ->toContain('Findings:')
        ->toContain('Queue worker timeout mismatch')
        ->toContain('confidence: 0.91');
});
