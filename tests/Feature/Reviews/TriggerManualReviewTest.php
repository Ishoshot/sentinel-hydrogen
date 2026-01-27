<?php

declare(strict_types=1);

use App\Actions\Reviews\TriggerManualReview;
use App\Enums\OAuthProvider;
use App\Enums\ProviderType;
use App\Enums\RunStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\Reviews\ExecuteReviewRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\ProviderIdentity;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    Bus::fake([ExecuteReviewRun::class]);
});

it('triggers a manual review and creates a run', function (): void {
    // Set up workspace and repository
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
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

    // Add provider key for BYOK requirement
    ProviderKey::factory()->forRepository($repository)->create();

    // Mock GitHub API
    $prData = [
        'number' => 42,
        'title' => 'Test PR',
        'body' => 'Test description',
        'draft' => false,
        'base' => ['ref' => 'main'],
        'head' => ['ref' => 'feature-branch', 'sha' => 'abc123'],
        'user' => ['login' => 'prauthor', 'avatar_url' => 'https://example.com/avatar.jpg'],
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
        ->withArgs(function ($installationId, $owner, $repo, $number, $body) {
            return $installationId === 12345
                && $owner === 'owner'
                && $repo === 'testrepo'
                && $number === 42
                && str_contains($body, 'Starting code review');
        })
        ->andReturn(['id' => 111222]);

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    // Trigger manual review
    $action = app(TriggerManualReview::class);
    $result = $action->handle(
        repository: $repository,
        prNumber: 42,
        senderLogin: 'testuser'
    );

    // Verify result
    expect($result['success'])->toBeTrue()
        ->and($result['run'])->toBeInstanceOf(Run::class)
        ->and($result['message'])->toContain('Review started');

    // Verify Run was created
    $run = $result['run'];
    expect($run->pr_number)->toBe(42)
        ->and($run->pr_title)->toBe('Test PR')
        ->and($run->status)->toBe(RunStatus::Queued)
        ->and($run->workspace_id)->toBe($workspace->id)
        ->and($run->repository_id)->toBe($repository->id)
        ->and($run->metadata['action'])->toBe('manual_trigger');

    // Verify job was dispatched
    Bus::assertDispatched(ExecuteReviewRun::class, function ($job) use ($run) {
        return $job->runId === $run->id;
    });
});

it('returns error when auto-review is disabled', function (): void {
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
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'testrepo')
        ->create([
            'workspace_id' => $workspace->id,
        ]);

    // Create repository settings with auto_review_enabled = false
    RepositorySettings::factory()->forRepository($repository)->create(['auto_review_enabled' => false]);

    $action = app(TriggerManualReview::class);
    $result = $action->handle(
        repository: $repository,
        prNumber: 42,
        senderLogin: 'testuser'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['run'])->toBeNull()
        ->and($result['message'])->toContain('disabled');

    // Verify no Run was created
    expect(Run::count())->toBe(0);
    Bus::assertNotDispatched(ExecuteReviewRun::class);
});

it('returns error when PR cannot be fetched', function (): void {
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
    $repository = Repository::factory()
        ->forInstallation($installation)
        ->withFullName('owner', 'testrepo')
        ->create([
            'workspace_id' => $workspace->id,
        ]);

    // Create repository settings with auto_review_enabled
    RepositorySettings::factory()->forRepository($repository)->create(['auto_review_enabled' => true]);

    // Mock GitHub API to throw exception
    $githubApi = Mockery::mock(GitHubApiServiceContract::class);
    $githubApi->shouldReceive('getPullRequest')
        ->once()
        ->andThrow(new RuntimeException('PR not found'));

    app()->instance(GitHubApiServiceContract::class, $githubApi);

    $action = app(TriggerManualReview::class);
    $result = $action->handle(
        repository: $repository,
        prNumber: 999,
        senderLogin: 'testuser'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['run'])->toBeNull()
        ->and($result['message'])->toContain('Unable to fetch');

    expect(Run::count())->toBe(0);
    Bus::assertNotDispatched(ExecuteReviewRun::class);
});
