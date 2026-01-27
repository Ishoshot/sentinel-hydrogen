<?php

declare(strict_types=1);

use App\Actions\Commands\ExecuteCommandRun;
use App\Enums\CommandRunStatus;
use App\Enums\ProviderType;
use App\Models\CommandRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

function mockGitHubApi(): void
{
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment');
    app()->instance(GitHubApiServiceContract::class, $githubApi);
}

it('updates command run status to in_progress when execution starts', function (): void {
    mockGitHubApi();

    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Queued,
        'started_at' => null,
    ]);

    $agentService = Mockery::mock(CommandAgentServiceContract::class);
    $agentService->shouldReceive('execute')
        ->once()
        ->andReturn([
            'answer' => 'Test answer',
            'tool_calls' => [],
            'iterations' => 1,
            'metrics' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'thinking_tokens' => 0,
                'duration_ms' => 1000,
                'model' => 'claude-3-sonnet',
                'provider' => 'anthropic',
            ],
        ]);

    app()->instance(CommandAgentServiceContract::class, $agentService);

    $action = app(ExecuteCommandRun::class);
    $action->handle($commandRun);

    $commandRun->refresh();
    expect($commandRun->started_at)->not()->toBeNull();
});

it('updates command run to completed status on success', function (): void {
    mockGitHubApi();

    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Queued,
    ]);

    $agentService = Mockery::mock(CommandAgentServiceContract::class);
    $agentService->shouldReceive('execute')
        ->once()
        ->andReturn([
            'answer' => 'The User model has an is_active boolean column.',
            'tool_calls' => [
                ['name' => 'search_code', 'arguments' => ['query' => 'is_active'], 'result' => 'Found in User.php'],
            ],
            'iterations' => 2,
            'metrics' => [
                'input_tokens' => 200,
                'output_tokens' => 100,
                'thinking_tokens' => 50,
                'duration_ms' => 2000,
                'model' => 'claude-3-sonnet',
                'provider' => 'anthropic',
            ],
        ]);

    app()->instance(CommandAgentServiceContract::class, $agentService);

    $action = app(ExecuteCommandRun::class);
    $action->handle($commandRun);

    $commandRun->refresh();
    expect($commandRun->status)->toBe(CommandRunStatus::Completed)
        ->and($commandRun->completed_at)->not()->toBeNull()
        ->and($commandRun->duration_seconds)->toBeInt()
        ->and($commandRun->response['answer'])->toBe('The User model has an is_active boolean column.')
        ->and($commandRun->response['tool_calls'])->toHaveCount(1)
        ->and($commandRun->response['iterations'])->toBe(2)
        ->and($commandRun->metrics['input_tokens'])->toBe(200)
        ->and($commandRun->metrics['output_tokens'])->toBe(100);
});

it('updates command run to failed status on exception', function (): void {
    mockGitHubApi();

    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Queued,
    ]);

    $agentService = Mockery::mock(CommandAgentServiceContract::class);
    $agentService->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('API rate limit exceeded'));

    app()->instance(CommandAgentServiceContract::class, $agentService);

    $action = app(ExecuteCommandRun::class);
    $action->handle($commandRun);

    $commandRun->refresh();
    expect($commandRun->status)->toBe(CommandRunStatus::Failed)
        ->and($commandRun->completed_at)->not()->toBeNull()
        ->and($commandRun->response['error'])->toBe('API rate limit exceeded');
});

it('stores metrics from agent execution', function (): void {
    mockGitHubApi();

    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Queued,
    ]);

    $expectedMetrics = [
        'input_tokens' => 500,
        'output_tokens' => 250,
        'thinking_tokens' => 100,
        'duration_ms' => 5000,
        'model' => 'claude-3-5-sonnet',
        'provider' => 'anthropic',
    ];

    $agentService = Mockery::mock(CommandAgentServiceContract::class);
    $agentService->shouldReceive('execute')
        ->once()
        ->andReturn([
            'answer' => 'Test answer',
            'tool_calls' => [],
            'iterations' => 1,
            'metrics' => $expectedMetrics,
        ]);

    app()->instance(CommandAgentServiceContract::class, $agentService);

    $action = app(ExecuteCommandRun::class);
    $action->handle($commandRun);

    $commandRun->refresh();
    expect($commandRun->metrics)->toBe($expectedMetrics);
});

it('stores metadata with tool call count and iterations', function (): void {
    mockGitHubApi();

    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
    ]);

    $commandRun = CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Queued,
    ]);

    $toolCalls = [
        ['name' => 'search_code', 'arguments' => ['query' => 'is_active'], 'result' => 'Found'],
        ['name' => 'read_file', 'arguments' => ['path' => 'app/Models/User.php'], 'result' => 'class User'],
        ['name' => 'get_file_structure', 'arguments' => ['path' => 'app/Models'], 'result' => '...'],
    ];

    $agentService = Mockery::mock(CommandAgentServiceContract::class);
    $agentService->shouldReceive('execute')
        ->once()
        ->andReturn([
            'answer' => 'Test answer',
            'tool_calls' => $toolCalls,
            'iterations' => 5,
            'metrics' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'thinking_tokens' => 0,
                'duration_ms' => 1000,
                'model' => 'test',
                'provider' => 'test',
            ],
        ]);

    app()->instance(CommandAgentServiceContract::class, $agentService);

    $action = app(ExecuteCommandRun::class);
    $action->handle($commandRun);

    $commandRun->refresh();
    expect($commandRun->metadata['tool_call_count'])->toBe(3)
        ->and($commandRun->metadata['iterations'])->toBe(5);
});

it('posts response to GitHub on success', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 99999,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/repo',
        'name' => 'repo',
    ]);

    $commandRun = CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Queued,
        'issue_number' => 42,
    ]);

    $agentService = Mockery::mock(CommandAgentServiceContract::class);
    $agentService->shouldReceive('execute')
        ->once()
        ->andReturn([
            'answer' => 'The answer to your question.',
            'tool_calls' => [],
            'iterations' => 1,
            'metrics' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'thinking_tokens' => 0,
                'duration_ms' => 1000,
                'model' => 'test',
                'provider' => 'test',
            ],
        ]);

    app()->instance(CommandAgentServiceContract::class, $agentService);

    // Verify GitHub API is called with correct parameters
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->withArgs(function ($installationId, $owner, $repo, $number, $body) {
            return $installationId === 99999
                && $owner === 'owner'
                && $repo === 'repo'
                && $number === 42
                && str_contains($body, 'The answer to your question.');
        });
    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $action = app(ExecuteCommandRun::class);
    $action->handle($commandRun);
});

it('posts error response to GitHub on exception', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 99999,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/repo',
        'name' => 'repo',
    ]);

    $commandRun = CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Queued,
        'issue_number' => 42,
    ]);

    $agentService = Mockery::mock(CommandAgentServiceContract::class);
    $agentService->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('Provider API unavailable'));

    app()->instance(CommandAgentServiceContract::class, $agentService);

    // Verify GitHub API is called with error message
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->withArgs(function ($installationId, $owner, $repo, $number, $body) {
            return $installationId === 99999
                && $owner === 'owner'
                && $repo === 'repo'
                && $number === 42
                && str_contains($body, 'error');
        });
    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $action = app(ExecuteCommandRun::class);
    $action->handle($commandRun);
});
