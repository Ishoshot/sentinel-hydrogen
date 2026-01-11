<?php

declare(strict_types=1);

use App\Enums\AiProvider;
use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    Provider::firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
});

function createWorkspaceWithRepository(User $owner): array
{
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forWorkspace($workspace)->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    return [$workspace, $repository];
}

it('can list provider keys for a repository', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::Anthropic,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('provider-keys.index', [$workspace, $repository]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.provider', 'anthropic')
        ->assertJsonPath('data.0.provider_label', 'Anthropic (Claude)');
});

it('never returns encrypted_key in list response', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::Anthropic,
        'encrypted_key' => 'sk-ant-secret-key-12345',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('provider-keys.index', [$workspace, $repository]));

    $response->assertOk()
        ->assertJsonMissing(['encrypted_key'])
        ->assertJsonMissingPath('data.0.encrypted_key');
});

it('owner can create provider key', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'anthropic',
            'key' => 'sk-ant-api03-test-key-for-testing',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.provider', 'anthropic')
        ->assertJsonPath('message', 'Provider key configured successfully.')
        ->assertJsonMissingPath('data.encrypted_key');

    $this->assertDatabaseHas('provider_keys', [
        'repository_id' => $repository->id,
        'provider' => 'anthropic',
    ]);
});

it('admin can create provider key', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($owner);
    $workspace->teamMembers()->create([
        'user_id' => $admin->id,
        'team_id' => $workspace->team->id,
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'openai',
            'key' => 'sk-openai-test-key-12345',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.provider', 'openai');
});

it('member cannot create provider key', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($owner);
    $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'anthropic',
            'key' => 'sk-ant-api03-test-key-for-testing',
        ]);

    $response->assertForbidden();
});

it('owner can delete provider key', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $providerKey = ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::Anthropic,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson(route('provider-keys.destroy', [$workspace, $repository, $providerKey]));

    $response->assertOk()
        ->assertJsonPath('message', 'Provider key deleted successfully.');

    $this->assertDatabaseMissing('provider_keys', [
        'id' => $providerKey->id,
    ]);
});

it('admin can delete provider key', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($owner);
    $workspace->teamMembers()->create([
        'user_id' => $admin->id,
        'team_id' => $workspace->team->id,
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    $providerKey = ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::OpenAI,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson(route('provider-keys.destroy', [$workspace, $repository, $providerKey]));

    $response->assertOk();
    $this->assertDatabaseMissing('provider_keys', ['id' => $providerKey->id]);
});

it('member cannot delete provider key', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($owner);
    $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $providerKey = ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::Anthropic,
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->deleteJson(route('provider-keys.destroy', [$workspace, $repository, $providerKey]));

    $response->assertForbidden();
});

it('creating key for same provider updates existing key (upsert)', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    // Create initial key
    ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::Anthropic,
        'encrypted_key' => 'old-key-value',
    ]);

    $this->assertDatabaseCount('provider_keys', 1);

    // Store new key for same provider
    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'anthropic',
            'key' => 'new-key-value-12345',
        ]);

    $response->assertCreated();

    // Should still only have 1 key (upsert)
    $this->assertDatabaseCount('provider_keys', 1);
});

it('can have multiple keys for different providers', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    // Create Anthropic key
    $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'anthropic',
            'key' => 'sk-ant-api03-test-key',
        ]);

    // Create OpenAI key
    $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'openai',
            'key' => 'sk-openai-test-key-12345',
        ]);

    $this->assertDatabaseCount('provider_keys', 2);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('provider-keys.index', [$workspace, $repository]));

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns 404 for repository from different workspace', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $otherUser = User::factory()->create();
    [$otherWorkspace, $otherRepository] = createWorkspaceWithRepository($otherUser);

    // Try to access other workspace's repository
    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('provider-keys.index', [$workspace, $otherRepository]));

    $response->assertNotFound();
});

it('returns 404 for provider key from different repository', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    // Create another repository in the same workspace using existing installation
    $installation = $repository->installation;
    $otherRepository = Repository::factory()->forInstallation($installation)->create();

    $providerKey = ProviderKey::factory()->create([
        'repository_id' => $otherRepository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::Anthropic,
    ]);

    // Try to delete key from different repository
    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson(route('provider-keys.destroy', [$workspace, $repository, $providerKey]));

    $response->assertNotFound();
});

it('validates provider is required', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'key' => 'sk-ant-api03-test-key',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['provider']);
});

it('validates provider must be valid', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'invalid-provider',
            'key' => 'sk-some-api-key-12345',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['provider']);
});

it('validates key is required', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'anthropic',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);
});

it('validates key minimum length', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'anthropic',
            'key' => 'short',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);
});

it('member can view provider keys', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($owner);
    $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::Anthropic,
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->getJson(route('provider-keys.index', [$workspace, $repository]));

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('logs activity when provider key is created', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $this->actingAs($user, 'sanctum')
        ->postJson(route('provider-keys.store', [$workspace, $repository]), [
            'provider' => 'anthropic',
            'key' => 'sk-ant-api03-test-key-for-testing',
        ]);

    $this->assertDatabaseHas('activities', [
        'workspace_id' => $workspace->id,
        'type' => 'provider_key.updated',
        'actor_id' => $user->id,
    ]);
});

it('logs activity when provider key is deleted', function (): void {
    $user = User::factory()->create();
    [$workspace, $repository] = createWorkspaceWithRepository($user);

    $providerKey = ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $workspace->id,
        'provider' => AiProvider::Anthropic,
    ]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson(route('provider-keys.destroy', [$workspace, $repository, $providerKey]));

    $this->assertDatabaseHas('activities', [
        'workspace_id' => $workspace->id,
        'type' => 'provider_key.deleted',
        'actor_id' => $user->id,
    ]);
});
