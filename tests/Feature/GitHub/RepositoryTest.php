<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    Provider::firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('can list repositories', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();

    Repository::factory()->forInstallation($installation)->count(3)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('repositories.index', $workspace));

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can view repository details', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create([
        'name' => 'test-repo',
        'full_name' => 'owner/test-repo',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('repositories.show', [$workspace, $repository]));

    $response->assertOk()
        ->assertJsonPath('data.name', 'test-repo')
        ->assertJsonPath('data.full_name', 'owner/test-repo');
});

it('can update repository settings as owner', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    RepositorySettings::factory()->forRepository($repository)->create(['auto_review_enabled' => true]);

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson(route('repositories.update', [$workspace, $repository]), [
            'auto_review_enabled' => false,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.auto_review_enabled', false);
});

it('can update repository settings as admin', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $admin->id,
        'team_id' => $workspace->team->id,
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    RepositorySettings::factory()->forRepository($repository)->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson(route('repositories.update', [$workspace, $repository]), [
            'auto_review_enabled' => false,
        ]);

    $response->assertOk();
});

it('cannot update repository settings as regular member', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $response = $this->actingAs($member, 'sanctum')
        ->patchJson(route('repositories.update', [$workspace, $repository]), [
            'auto_review_enabled' => false,
        ]);

    $response->assertForbidden();
});

it('member can view repositories', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
    $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    Repository::factory()->forInstallation($installation)->count(2)->create();

    $response = $this->actingAs($member, 'sanctum')
        ->getJson(route('repositories.index', $workspace));

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns 404 for repository from different workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $otherWorkspace = Workspace::factory()->create();
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($otherWorkspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('repositories.show', [$workspace, $repository]));

    $response->assertNotFound();
});

it('validates repository settings update', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson(route('repositories.update', [$workspace, $repository]), [
            'auto_review_enabled' => 'not-a-boolean',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['auto_review_enabled']);
});
