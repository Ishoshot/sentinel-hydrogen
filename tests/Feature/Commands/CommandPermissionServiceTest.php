<?php

declare(strict_types=1);

use App\Enums\Auth\OAuthProvider;
use App\Enums\Auth\ProviderType;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Commands\CommandRunStatus;
use App\Models\CommandRun;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\ProviderIdentity;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Commands\CommandPermissionService;

beforeEach(function (): void {
    $this->service = app(CommandPermissionService::class);
});

it('denies permission when user has no Sentinel account', function (): void {
    $result = $this->service->checkPermission('unknown_github_user', 'owner/repo');

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('user_not_found')
        ->and($result->message)->toContain('Sentinel account');
});

it('denies permission when repository is not found', function (): void {
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    $result = $this->service->checkPermission('testuser', 'nonexistent/repo');

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('repository_not_found');
});

it('denies permission when user is not a workspace member', function (): void {
    // Create a user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Create workspace and repository (user is NOT a member)
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    $result = $this->service->checkPermission('testuser', 'owner/testrepo');

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('not_workspace_member');
});

it('denies permission when subscription is inactive', function (): void {
    // Create a user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Create workspace with inactive subscription
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);

    // Add user to workspace
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
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    $result = $this->service->checkPermission('testuser', 'owner/testrepo');

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('subscription_inactive');
});

it('denies permission when command limit is reached', function (): void {
    // Create plan with command limit of 1
    $plan = Plan::factory()->create([
        'monthly_commands_limit' => 1,
    ]);

    // Create a user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Create workspace
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // Add user to workspace
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
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Add provider key (BYOK requirement)
    ProviderKey::factory()->forRepository($repository)->create();

    // Create a command run that counts against the limit
    CommandRun::factory()->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Completed,
        'created_at' => now(),
    ]);

    $result = $this->service->checkPermission('testuser', 'owner/testrepo');

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('commands_limit');
});

it('denies permission when no provider keys are configured', function (): void {
    // Create a user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Create workspace
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // Add user to workspace
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
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // No provider keys configured

    $result = $this->service->checkPermission('testuser', 'owner/testrepo');

    expect($result->allowed)->toBeFalse()
        ->and($result->code)->toBe('no_provider_keys');
});

it('allows permission when all checks pass with workspace-level provider key', function (): void {
    // Create a user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Create workspace
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // Add user to workspace
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
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Add provider key for the repository
    ProviderKey::factory()->forRepository($repository)->create();

    $result = $this->service->checkPermission('testuser', 'owner/testrepo');

    expect($result->allowed)->toBeTrue()
        ->and($result->user->id)->toBe($user->id)
        ->and($result->workspace->id)->toBe($workspace->id)
        ->and($result->repository->id)->toBe($repository->id);
});

it('allows commands when under the limit', function (): void {
    // Create plan with command limit of 10
    $plan = Plan::factory()->create([
        'monthly_commands_limit' => 10,
    ]);

    // Create a user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Create workspace
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // Add user to workspace
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
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Add provider key
    ProviderKey::factory()->forRepository($repository)->create();

    // Create 5 command runs (under the limit of 10)
    CommandRun::factory()->count(5)->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Completed,
        'created_at' => now(),
    ]);

    $result = $this->service->checkPermission('testuser', 'owner/testrepo');

    expect($result->allowed)->toBeTrue();
});

it('is case insensitive for GitHub username matching', function (): void {
    // Create a user with GitHub identity (lowercase)
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser', // lowercase
    ]);

    // Create workspace
    $workspace = Workspace::factory()->create([
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // Add user to workspace
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
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Add provider key
    ProviderKey::factory()->forRepository($repository)->create();

    // Query with exact case
    $result = $this->service->checkPermission('testuser', 'owner/testrepo');
    expect($result->allowed)->toBeTrue();
});

it('allows unlimited commands when plan has null limit', function (): void {
    // Create plan with unlimited commands
    $plan = Plan::factory()->create([
        'monthly_commands_limit' => null, // unlimited
    ]);

    // Create a user with GitHub identity
    $user = User::factory()->create();
    ProviderIdentity::factory()->create([
        'user_id' => $user->id,
        'provider' => OAuthProvider::GitHub,
        'nickname' => 'testuser',
    ]);

    // Create workspace
    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    // Add user to workspace
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
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'workspace_id' => $workspace->id,
        'full_name' => 'owner/testrepo',
    ]);

    // Add provider key
    ProviderKey::factory()->forRepository($repository)->create();

    // Create many command runs
    CommandRun::factory()->count(100)->create([
        'workspace_id' => $workspace->id,
        'repository_id' => $repository->id,
        'status' => CommandRunStatus::Completed,
        'created_at' => now(),
    ]);

    $result = $this->service->checkPermission('testuser', 'owner/testrepo');

    expect($result->allowed)->toBeTrue();
});
