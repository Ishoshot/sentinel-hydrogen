<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Enums\Workspace\ConnectionStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\User;
use App\Models\Workspace;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

beforeEach(function (): void {
    // Ensure GitHub provider exists
    Provider::firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

it('can view connection status when no connection exists', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('github.connection.show', $workspace));

    $response->assertOk()
        ->assertJsonPath('data', null);
});

it('can view existing connection status', function (): void {
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
    Installation::factory()->forConnection($connection)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('github.connection.show', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_active', true);
});

it('can initiate github connection as owner', function (): void {
    config(['github.app_name' => 'test-app']);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('github.connection.store', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['data', 'installation_url', 'message']);

    expect($response->json('installation_url'))->toContain('github.com/apps/test-app');
});

it('can initiate github connection as admin', function (): void {
    config(['github.app_name' => 'test-app']);

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

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(route('github.connection.store', $workspace));

    $response->assertOk();
});

it('cannot initiate github connection as regular member', function (): void {
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

    $response = $this->actingAs($member, 'sanctum')
        ->postJson(route('github.connection.store', $workspace));

    $response->assertForbidden();
});

it('returns existing active connection with configure url when initiating', function (): void {
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

    // Create an installation linked to the connection
    $installation = Installation::factory()->forConnection($connection)->user()->create([
        'installation_id' => 12345678,
        'account_login' => 'testuser',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('github.connection.store', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('installation_url', 'https://github.com/settings/installations/12345678')
        ->assertJsonPath('message', 'Redirect to GitHub to configure repository access.');
});

it('returns existing active connection with org configure url when initiating for organization', function (): void {
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

    // Create an organization installation linked to the connection
    Installation::factory()->forConnection($connection)->organization()->create([
        'installation_id' => 87654321,
        'account_login' => 'test-org',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('github.connection.store', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('installation_url', 'https://github.com/organizations/test-org/settings/installations/87654321')
        ->assertJsonPath('message', 'Redirect to GitHub to configure repository access.');
});

it('can disconnect github as owner', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson(route('github.connection.destroy', $workspace));

    $response->assertOk()
        ->assertJsonPath('message', 'GitHub disconnected successfully.');

    $connection = Connection::where('workspace_id', $workspace->id)->first();
    expect($connection->status)->toBe(ConnectionStatus::Disconnected);
});

it('cannot disconnect github as regular member', function (): void {
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
    Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();

    $response = $this->actingAs($member, 'sanctum')
        ->deleteJson(route('github.connection.destroy', $workspace));

    $response->assertForbidden();
});

it('requires authentication to view connection', function (): void {
    $workspace = Workspace::factory()->create();

    $response = $this->getJson(route('github.connection.show', $workspace));

    $response->assertUnauthorized();
});

it('redirects github callback to frontend integrations page', function (): void {
    config(['app.frontend_url' => 'https://frontend.test']);

    $workspace = Workspace::factory()->create([
        'slug' => 'acme-workspace',
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->firstOrFail();
    $connection = Connection::factory()
        ->forWorkspace($workspace)
        ->forProvider($provider)
        ->pending()
        ->state([
            'metadata' => [
                'state' => 'state-token-123',
            ],
        ])
        ->create();

    $mockGitHubApiService = Mockery::mock(GitHubApiServiceContract::class);
    $mockGitHubApiService->shouldReceive('getInstallation')
        ->once()
        ->with(123456)
        ->andReturn([
            'account' => [
                'type' => 'User',
                'login' => 'acme-user',
                'avatar_url' => null,
            ],
            'permissions' => [],
            'events' => [],
        ]);

    $mockGitHubApiService->shouldReceive('getInstallationRepositories')
        ->once()
        ->with(123456)
        ->andReturn([]);

    $this->app->instance(GitHubApiServiceContract::class, $mockGitHubApiService);

    $response = $this->get(route('github.callback', [
        'installation_id' => 123456,
        'state' => 'state-token-123',
    ]));

    $response->assertRedirect('https://frontend.test/acme-workspace/settings/integrations');
});

it('redirects cancelled github callback to frontend workspaces page', function (): void {
    config(['app.frontend_url' => 'https://frontend.test']);

    $response = $this->get(route('github.callback', [
        'installation_id' => 123456,
        'setup_action' => 'request',
    ]));

    $response->assertRedirect('https://frontend.test/auth/error?message=Installation+was+cancelled.');
});
