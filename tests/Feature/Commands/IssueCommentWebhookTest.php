<?php

declare(strict_types=1);

use App\Enums\Auth\OAuthProvider;
use App\Enums\Auth\ProviderType;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Commands\CommandRunStatus;
use App\Enums\Commands\CommandType;
use App\Enums\Reviews\RunStatus;
use App\Jobs\Commands\ExecuteCommandRunJob;
use App\Jobs\GitHub\ProcessIssueCommentWebhook;
use App\Models\CommandRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\ProviderIdentity;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\User;
use App\Models\Workspace;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

it('ignores comments without @sentinel mention', function (): void {
    Queue::fake();
    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 12345,
            'body' => 'This is just a regular comment',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/repo',
        ],
        'installation' => [
            'id' => 1,
        ],
        'issue' => [
            'number' => 42,
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    expect(CommandRun::count())->toBe(0);
    Queue::assertNotPushed(ExecuteCommandRunJob::class);
});

it('ignores non-created actions', function (): void {
    Queue::fake();
    $payload = [
        'action' => 'edited', // Not 'created'
        'comment' => [
            'id' => 12345,
            'body' => '@sentinel explain something',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/repo',
        ],
        'installation' => [
            'id' => 1,
        ],
        'issue' => [
            'number' => 42,
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    expect(CommandRun::count())->toBe(0);
    Queue::assertNotPushed(ExecuteCommandRunJob::class);
});

it('creates command run and dispatches job for valid @sentinel command', function (): void {
    Bus::fake([ExecuteCommandRunJob::class]);

    // Set up authenticated user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Set up workspace and repository
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Add provider key
    ProviderKey::factory()->forRepository($repository)->create();

    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 99999,
            'body' => '@sentinel explain the is_active column on User model',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/testrepo',
        ],
        'installation' => [
            'id' => 12345,
        ],
        'issue' => [
            'number' => 42,
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    // Verify command run was created
    expect(CommandRun::count())->toBe(1);

    $commandRun = CommandRun::first();
    expect($commandRun->command_type)->toBe(CommandType::Explain)
        ->and($commandRun->query)->toBe('the is_active column on User model')
        ->and($commandRun->github_comment_id)->toBe(99999)
        ->and($commandRun->issue_number)->toBe(42)
        ->and($commandRun->is_pull_request)->toBeFalse()
        ->and($commandRun->status)->toBe(CommandRunStatus::Queued)
        ->and($commandRun->workspace_id)->toBe($workspace->id)
        ->and($commandRun->repository_id)->toBe($repository->id)
        ->and($commandRun->initiated_by_id)->toBe($user->id);

    // Verify job was dispatched
    Bus::assertDispatched(ExecuteCommandRunJob::class, function ($job) use ($commandRun) {
        return $job->commandRunId === $commandRun->id;
    });
});

it('detects pull request comments', function (): void {
    // Set up authenticated user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Set up workspace and repository
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Add provider key
    ProviderKey::factory()->forRepository($repository)->create();

    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 99999,
            'body' => '@sentinel summarize this PR',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/testrepo',
        ],
        'installation' => [
            'id' => 12345,
        ],
        'issue' => [
            'number' => 15,
            'pull_request' => [
                'url' => 'https://api.github.com/repos/owner/testrepo/pulls/15',
            ],
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    $commandRun = CommandRun::first();
    expect($commandRun->is_pull_request)->toBeTrue()
        ->and($commandRun->command_type)->toBe(CommandType::Summarize);
});

it('posts denial comment when user lacks permission', function (): void {
    Bus::fake([ExecuteCommandRunJob::class]);

    // Create user but NOT add to workspace
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'outsider',
    ]);

    // Create workspace without adding the user
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Mock GitHub API to verify comment is posted
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->withArgs(function ($installationId, $owner, $repo, $number, $body) {
            return $installationId === 12345
                && $owner === 'owner'
                && $repo === 'testrepo'
                && $number === 42
                && str_contains($body, 'not a member');
        });

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 99999,
            'body' => '@sentinel explain something',
        ],
        'sender' => [
            'login' => 'outsider',
        ],
        'repository' => [
            'full_name' => 'owner/testrepo',
        ],
        'installation' => [
            'id' => 12345,
        ],
        'issue' => [
            'number' => 42,
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    // Verify no command run was created
    expect(CommandRun::count())->toBe(0);
    Bus::assertNotDispatched(ExecuteCommandRunJob::class);
});

it('extracts context hints from command', function (): void {
    // Set up authenticated user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Set up workspace and repository
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Add provider key
    ProviderKey::factory()->forRepository($repository)->create();

    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 99999,
            'body' => '@sentinel explain app/Models/User.php line 42',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/testrepo',
        ],
        'installation' => [
            'id' => 12345,
        ],
        'issue' => [
            'number' => 10,
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    $commandRun = CommandRun::first();
    expect($commandRun->context_snapshot)->toBeArray()
        ->and($commandRun->context_snapshot['context_hints']['files'])->toContain('app/Models/User.php')
        ->and($commandRun->context_snapshot['context_hints']['lines'])->toContain(['start' => 42, 'end' => null]);
});

it('triggers manual review for @sentinel review on PR', function (): void {
    Bus::fake([App\Jobs\Reviews\ExecuteReviewRun::class]);

    // Set up authenticated user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Set up workspace and repository
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'testrepo')
        ->create([
            'workspace_id' => $workspace->id,
            'github_id' => 67890,
        ]);

    // Create repository settings with auto_review_enabled
    RepositorySettings::factory()->forRepository($repository)->create(['auto_review_enabled' => true]);

    // Add provider key
    ProviderKey::factory()->forRepository($repository)->create();

    // Mock GitHub API
    $prData = [
        'number' => 42,
        'title' => 'Test PR Title',
        'body' => 'Test description',
        'draft' => false,
        'base' => ['ref' => 'main'],
        'head' => ['ref' => 'feature-branch', 'sha' => 'abc123'],
        'user' => ['login' => 'prauthor', 'avatar_url' => null],
        'assignees' => [],
        'requested_reviewers' => [],
        'labels' => [],
    ];

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('getPullRequest')
        ->once()
        ->with(12345, 'owner', 'testrepo', 42)
        ->andReturn($prData);

    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->andReturn(['id' => 111222]);

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 99999,
            'body' => '@sentinel review',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/testrepo',
        ],
        'installation' => [
            'id' => 12345,
        ],
        'issue' => [
            'number' => 42,
            'pull_request' => [
                'url' => 'https://api.github.com/repos/owner/testrepo/pulls/42',
            ],
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    // Verify NO CommandRun was created (review uses Run instead)
    expect(CommandRun::count())->toBe(0);

    // Verify a Run was created
    expect(App\Models\Run::count())->toBe(1);

    $run = App\Models\Run::first();
    expect($run->pr_number)->toBe(42)
        ->and($run->pr_title)->toBe('Test PR Title')
        ->and($run->metadata['action'])->toBe('manual_trigger')
        ->and($run->status)->toBe(RunStatus::Queued);

    // Verify ExecuteReviewRun job was dispatched
    Bus::assertDispatched(App\Jobs\Reviews\ExecuteReviewRun::class);
});

it('triggers manual review for @sentinel re-review on PR', function (): void {
    Bus::fake([App\Jobs\Reviews\ExecuteReviewRun::class]);

    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'testrepo')
        ->create([
            'workspace_id' => $workspace->id,
            'github_id' => 67890,
        ]);

    // Create repository settings with auto_review_enabled
    RepositorySettings::factory()->forRepository($repository)->create(['auto_review_enabled' => true]);

    ProviderKey::factory()->forRepository($repository)->create();

    $prData = [
        'number' => 15,
        'title' => 'Another PR',
        'body' => null,
        'draft' => false,
        'base' => ['ref' => 'main'],
        'head' => ['ref' => 'fix-branch', 'sha' => 'def456'],
        'user' => ['login' => 'author', 'avatar_url' => null],
        'assignees' => [],
        'requested_reviewers' => [],
        'labels' => [],
    ];

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('getPullRequest')->once()->andReturn($prData);
    $githubApi->shouldReceive('createIssueComment')->once()->andReturn(['id' => 333]);

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 88888,
            'body' => '@sentinel re-review',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/testrepo',
        ],
        'installation' => [
            'id' => 12345,
        ],
        'issue' => [
            'number' => 15,
            'pull_request' => [
                'url' => 'https://api.github.com/repos/owner/testrepo/pulls/15',
            ],
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    // Verify Run was created (not CommandRun)
    expect(CommandRun::count())->toBe(0);
    expect(App\Models\Run::count())->toBe(1);

    Bus::assertDispatched(App\Jobs\Reviews\ExecuteReviewRun::class);
});

it('posts guidance for @sentinel review on issue (not PR)', function (): void {
    Bus::fake([ExecuteCommandRunJob::class]);

    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    ProviderKey::factory()->forRepository($repository)->create();

    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('createIssueComment')
        ->once()
        ->withArgs(function ($installationId, $owner, $repo, $number, $body) {
            return $installationId === 12345
                && $owner === 'owner'
                && $repo === 'testrepo'
                && $number === 10
                && str_contains($body, 'Manual reviews are only supported on pull requests');
        })
        ->andReturn(['id' => 555]);
    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 77777,
            'body' => '@sentinel review the authentication logic',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/testrepo',
        ],
        'installation' => [
            'id' => 12345,
        ],
        'issue' => [
            'number' => 10,
            // No 'pull_request' key - this is a regular issue
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);

    // On an issue (not PR), @sentinel review should not create a CommandRun
    expect(CommandRun::count())->toBe(0);
    expect(App\Models\Run::count())->toBe(0);

    Bus::assertNotDispatched(ExecuteCommandRunJob::class);
});

it('deduplicates repeated issue comment webhooks', function (): void {
    Bus::fake([ExecuteCommandRunJob::class]);

    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => 12345,
    ]);
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    ProviderKey::factory()->forRepository($repository)->create();

    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 77777,
            'body' => '@sentinel explain the authentication logic',
        ],
        'sender' => [
            'login' => 'testuser',
        ],
        'repository' => [
            'full_name' => 'owner/testrepo',
        ],
        'installation' => [
            'id' => 12345,
        ],
        'issue' => [
            'number' => 10,
        ],
    ];

    ProcessIssueCommentWebhook::dispatchSync($payload);
    ProcessIssueCommentWebhook::dispatchSync($payload);

    expect(CommandRun::count())->toBe(1);
    Bus::assertDispatchedTimes(ExecuteCommandRunJob::class, 1);
});
