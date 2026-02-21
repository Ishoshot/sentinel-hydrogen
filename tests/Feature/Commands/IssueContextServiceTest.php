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
use App\Services\Commands\IssueContextService;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($this->workspace)->forProvider($provider)->create();
    $this->installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $this->repository = Repository::factory()->forInstallation($this->installation)->create([
        'workspace_id' => $this->workspace->id,
        'full_name' => 'owner/repo',
        'name' => 'repo',
    ]);

    $this->githubApi = Mockery::mock(GitHubApiServiceContract::class);
    app()->instance(GitHubApiServiceContract::class, $this->githubApi);
});

describe('buildContext', function (): void {
    it('returns null for pull request commands', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Analyze,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => true,
            'issue_number' => 42,
        ]);

        $service = app(IssueContextService::class);
        $context = $service->buildContext($commandRun);

        expect($context)->toBeNull();
    });

    it('builds context for issue commands', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Explain,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => false,
            'issue_number' => 77,
        ]);

        $this->githubApi->shouldReceive('getIssue')
            ->once()
            ->with(12345, 'owner', 'repo', 77)
            ->andReturn([
                'title' => 'Queue worker crashes under load',
                'body' => 'Workers restart unexpectedly when processing spikes.',
                'state' => 'open',
                'labels' => [
                    ['name' => 'bug'],
                    ['name' => 'queue'],
                ],
                'assignees' => [
                    ['login' => 'alice'],
                    ['login' => 'bob'],
                ],
                'milestone' => ['title' => 'Q1 Reliability'],
            ]);

        $this->githubApi->shouldReceive('getIssueComments')
            ->once()
            ->with(12345, 'owner', 'repo', 77)
            ->andReturn([
                [
                    'user' => ['login' => 'maintainer'],
                    'body' => 'We suspect queue timeout drift.',
                ],
            ]);

        $service = app(IssueContextService::class);
        $context = $service->buildContext($commandRun);

        expect($context)->toBeString()
            ->and($context)->toContain('## Issue Context')
            ->and($context)->toContain('Queue worker crashes under load')
            ->and($context)->toContain('Workers restart unexpectedly')
            ->and($context)->toContain('bug, queue')
            ->and($context)->toContain('@alice, @bob')
            ->and($context)->toContain('Q1 Reliability')
            ->and($context)->toContain('@maintainer')
            ->and($context)->toContain('timeout drift');
    });

    it('returns null when issue api request fails', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Summarize,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => false,
            'issue_number' => 99,
        ]);

        $this->githubApi->shouldReceive('getIssue')
            ->once()
            ->andThrow(new RuntimeException('Network error'));

        $service = app(IssueContextService::class);
        $context = $service->buildContext($commandRun);

        expect($context)->toBeNull();
    });

    it('truncates long issue body and comments', function (): void {
        $commandRun = CommandRun::factory()->create([
            'workspace_id' => $this->workspace->id,
            'repository_id' => $this->repository->id,
            'command_type' => CommandType::Find,
            'status' => CommandRunStatus::Queued,
            'is_pull_request' => false,
            'issue_number' => 10,
        ]);

        $longBody = str_repeat('Detailed issue analysis content ', 200);
        $longComment = str_repeat('comment detail ', 80);

        $this->githubApi->shouldReceive('getIssue')
            ->once()
            ->andReturn([
                'title' => 'Large issue description',
                'body' => $longBody,
                'state' => 'open',
                'labels' => [],
                'assignees' => [],
                'milestone' => null,
            ]);

        $this->githubApi->shouldReceive('getIssueComments')
            ->once()
            ->andReturn([
                ['user' => ['login' => 'dev1'], 'body' => $longComment],
            ]);

        $service = app(IssueContextService::class);
        $context = $service->buildContext($commandRun);

        expect($context)->toBeString()
            ->and($context)->toContain('(description truncated)')
            ->and($context)->toContain('...')
            ->and(mb_strlen($context))->toBeLessThan(7000);
    });
});
