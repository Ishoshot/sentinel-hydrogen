<?php

declare(strict_types=1);

use App\Actions\Commands\CreateCommandRun;
use App\Enums\Auth\ProviderType;
use App\Enums\Commands\CommandRunStatus;
use App\Enums\Commands\CommandType;
use App\Models\CommandRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Commands\ValueObjects\ContextHints;
use App\Services\Commands\ValueObjects\LineRange;

it('creates a command run with all required fields', function (): void {
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
    $user = User::factory()->create();

    $action = app(CreateCommandRun::class);

    $commandRun = $action->handle(
        workspace: $workspace,
        repository: $repository,
        user: $user,
        commandType: CommandType::Explain,
        query: 'the is_active column on User model',
        githubCommentId: 12345,
        issueNumber: 42,
        isPullRequest: false,
    );

    expect($commandRun)->toBeInstanceOf(CommandRun::class)
        ->and($commandRun->workspace_id)->toBe($workspace->id)
        ->and($commandRun->repository_id)->toBe($repository->id)
        ->and($commandRun->initiated_by_id)->toBe($user->id)
        ->and($commandRun->command_type)->toBe(CommandType::Explain)
        ->and($commandRun->query)->toBe('the is_active column on User model')
        ->and($commandRun->github_comment_id)->toBe(12345)
        ->and($commandRun->issue_number)->toBe(42)
        ->and($commandRun->is_pull_request)->toBeFalse()
        ->and($commandRun->status)->toBe(CommandRunStatus::Queued)
        ->and($commandRun->external_reference)->toBe('github:comment:12345');
});

it('creates a command run with context hints', function (): void {
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
    $user = User::factory()->create();

    $action = app(CreateCommandRun::class);

    $contextHints = new ContextHints(
        files: ['app/Models/User.php'],
        symbols: ['User', 'isActive'],
        lines: [new LineRange(start: 42)],
    );

    $commandRun = $action->handle(
        workspace: $workspace,
        repository: $repository,
        user: $user,
        commandType: CommandType::Explain,
        query: 'the is_active column',
        githubCommentId: 12345,
        issueNumber: 42,
        isPullRequest: false,
        contextHints: $contextHints,
    );

    expect($commandRun->context_snapshot)->toBeArray()
        ->and($commandRun->context_snapshot['context_hints'])->toBe($contextHints->toArray());
});

it('creates a command run for a pull request', function (): void {
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
    $user = User::factory()->create();

    $action = app(CreateCommandRun::class);

    $commandRun = $action->handle(
        workspace: $workspace,
        repository: $repository,
        user: $user,
        commandType: CommandType::Review,
        query: 'the changes in this PR',
        githubCommentId: 67890,
        issueNumber: 15,
        isPullRequest: true,
    );

    expect($commandRun->is_pull_request)->toBeTrue()
        ->and($commandRun->issue_number)->toBe(15)
        ->and($commandRun->command_type)->toBe(CommandType::Review);
});

it('creates a command run without a user', function (): void {
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

    $action = app(CreateCommandRun::class);

    $commandRun = $action->handle(
        workspace: $workspace,
        repository: $repository,
        user: null,
        commandType: CommandType::Summarize,
        query: 'this PR',
        githubCommentId: 11111,
        issueNumber: 5,
        isPullRequest: true,
    );

    expect($commandRun->initiated_by_id)->toBeNull()
        ->and($commandRun->command_type)->toBe(CommandType::Summarize);
});

it('creates command runs with different command types', function (CommandType $commandType): void {
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
    $user = User::factory()->create();

    $action = app(CreateCommandRun::class);

    $commandRun = $action->handle(
        workspace: $workspace,
        repository: $repository,
        user: $user,
        commandType: $commandType,
        query: 'test query',
        githubCommentId: fake()->randomNumber(5),
        issueNumber: 1,
        isPullRequest: false,
    );

    expect($commandRun->command_type)->toBe($commandType);
})->with([
    'explain' => CommandType::Explain,
    'analyze' => CommandType::Analyze,
    'review' => CommandType::Review,
    'summarize' => CommandType::Summarize,
    'find' => CommandType::Find,
]);

it('deduplicates command runs for the same GitHub comment', function (): void {
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
    $user = User::factory()->create();

    $action = app(CreateCommandRun::class);

    $first = $action->handle(
        workspace: $workspace,
        repository: $repository,
        user: $user,
        commandType: CommandType::Explain,
        query: 'test query',
        githubCommentId: 22222,
        issueNumber: 9,
        isPullRequest: true,
    );

    $second = $action->handle(
        workspace: $workspace,
        repository: $repository,
        user: $user,
        commandType: CommandType::Explain,
        query: 'test query',
        githubCommentId: 22222,
        issueNumber: 9,
        isPullRequest: true,
    );

    expect($second->id)->toBe($first->id)
        ->and($second->wasRecentlyCreated)->toBeFalse()
        ->and(CommandRun::query()->where('external_reference', 'github:comment:22222')->count())->toBe(1);
});
