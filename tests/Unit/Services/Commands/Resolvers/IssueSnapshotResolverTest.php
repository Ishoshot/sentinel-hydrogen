<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Enums\Commands\CommandRunStatus;
use App\Enums\Commands\CommandType;
use App\Models\CommandRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\Commands\Resolvers\IssueApiParameterResolver;
use App\Services\Commands\Resolvers\IssueSnapshotResolver;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 98765,
    ]);

    $this->repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/repo',
        'name' => 'repo',
    ]);

    $this->commandRun = CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $this->repository->id,
        'command_type' => CommandType::Explain,
        'status' => CommandRunStatus::Queued,
        'is_pull_request' => false,
        'issue_number' => 14,
    ]);
});

it('reuses cached issue snapshot and avoids duplicate issue and comment API calls', function (): void {
    $gitHubApiService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubApiService->shouldReceive('getIssue')
        ->once()
        ->with(98765, 'owner', 'repo', 14)
        ->andReturn(['title' => 'Issue title']);
    $gitHubApiService->shouldReceive('getIssueComments')
        ->once()
        ->with(98765, 'owner', 'repo', 14)
        ->andReturn([['body' => 'Comment']]);
    $gitHubApiService->shouldNotReceive('getIssueTimeline');

    $resolver = new IssueSnapshotResolver($gitHubApiService, new IssueApiParameterResolver());

    $first = $resolver->resolve($this->commandRun);
    $second = $resolver->resolve($this->commandRun);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($first?->issue['title'])->toBe('Issue title')
        ->and($second?->comments)->toHaveCount(1);
});

it('fetches timeline once when transitioning from non timeline to timeline snapshot', function (): void {
    $gitHubApiService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubApiService->shouldReceive('getIssue')
        ->once()
        ->with(98765, 'owner', 'repo', 14)
        ->andReturn(['title' => 'Issue title']);
    $gitHubApiService->shouldReceive('getIssueComments')
        ->once()
        ->with(98765, 'owner', 'repo', 14)
        ->andReturn([['body' => 'Comment']]);
    $gitHubApiService->shouldReceive('getIssueTimeline')
        ->once()
        ->with(98765, 'owner', 'repo', 14)
        ->andReturn([['event' => 'referenced']]);

    $resolver = new IssueSnapshotResolver($gitHubApiService, new IssueApiParameterResolver());

    $resolver->resolve($this->commandRun, includeTimeline: false);
    $withTimeline = $resolver->resolve($this->commandRun, includeTimeline: true);
    $withTimelineAgain = $resolver->resolve($this->commandRun, includeTimeline: true);

    expect($withTimeline)->not->toBeNull()
        ->and($withTimeline?->hasTimeline)->toBeTrue()
        ->and($withTimelineAgain?->timeline)->toHaveCount(1);
});

it('fails open and returns null when github API throws', function (): void {
    $gitHubApiService = Mockery::mock(GitHubApiServiceContract::class);
    $gitHubApiService->shouldReceive('getIssue')
        ->once()
        ->with(98765, 'owner', 'repo', 14)
        ->andThrow(new RuntimeException('GitHub timeout'));

    $resolver = new IssueSnapshotResolver($gitHubApiService, new IssueApiParameterResolver());

    expect($resolver->resolve($this->commandRun))->toBeNull();
});
