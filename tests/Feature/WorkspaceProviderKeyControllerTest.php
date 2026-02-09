<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Models\ProviderKey;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
    ]);

    $this->workspace->teamMembers()->create([
        'user_id' => $this->user->id,
        'team_id' => $this->workspace->team->id,
        'workspace_id' => $this->workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
});

describe('index', function (): void {
    it('lists workspace-level provider keys', function (): void {
        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->anthropic()
            ->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('workspace-provider-keys.index', $this->workspace));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', AiProvider::Anthropic->value);
    });

    it('does not list repo-level keys', function (): void {
        // Create a repo-level key
        ProviderKey::factory()
            ->anthropic()
            ->create(['workspace_id' => $this->workspace->id]);

        // Create a workspace-level key
        ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->openai()
            ->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson(route('workspace-provider-keys.index', $this->workspace));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', AiProvider::OpenAI->value);
    });

    it('requires authentication', function (): void {
        $this->getJson(route('workspace-provider-keys.index', $this->workspace))
            ->assertUnauthorized();
    });
});

describe('store', function (): void {
    it('creates a workspace-level provider key', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(route('workspace-provider-keys.store', $this->workspace), [
                'provider' => 'anthropic',
                'key' => 'sk-ant-test-1234567890abcdef',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.provider', AiProvider::Anthropic->value)
            ->assertJsonPath('message', 'Workspace provider key configured successfully.');

        $key = ProviderKey::query()
            ->where('workspace_id', $this->workspace->id)
            ->whereNull('repository_id')
            ->first();

        expect($key)->not()->toBeNull()
            ->and($key->provider)->toBe(AiProvider::Anthropic)
            ->and($key->isWorkspaceLevel())->toBeTrue();
    });

    it('upserts on same provider', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('workspace-provider-keys.store', $this->workspace), [
                'provider' => 'anthropic',
                'key' => 'sk-ant-test-first-key-value',
            ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('workspace-provider-keys.store', $this->workspace), [
                'provider' => 'anthropic',
                'key' => 'sk-ant-test-second-key-value',
            ]);

        $count = ProviderKey::query()
            ->where('workspace_id', $this->workspace->id)
            ->whereNull('repository_id')
            ->where('provider', AiProvider::Anthropic)
            ->count();

        expect($count)->toBe(1);
    });

    it('validates required fields', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('workspace-provider-keys.store', $this->workspace), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider', 'key']);
    });

    it('validates provider is valid', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('workspace-provider-keys.store', $this->workspace), [
                'provider' => 'invalid-provider',
                'key' => 'sk-ant-test-1234567890abcdef',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);
    });

    it('validates key minimum length', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->postJson(route('workspace-provider-keys.store', $this->workspace), [
                'provider' => 'anthropic',
                'key' => 'short',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    });

    it('denies non-admin members', function (): void {
        $member = User::factory()->create();
        $this->workspace->teamMembers()->create([
            'user_id' => $member->id,
            'team_id' => $this->workspace->team->id,
            'workspace_id' => $this->workspace->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson(route('workspace-provider-keys.store', $this->workspace), [
                'provider' => 'anthropic',
                'key' => 'sk-ant-test-1234567890abcdef',
            ])
            ->assertForbidden();
    });
});

describe('destroy', function (): void {
    it('deletes a workspace-level provider key', function (): void {
        $key = ProviderKey::factory()
            ->forWorkspaceLevel($this->workspace)
            ->anthropic()
            ->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('workspace-provider-keys.destroy', [$this->workspace, $key]));

        $response->assertOk()
            ->assertJsonPath('message', 'Workspace provider key deleted successfully.');

        expect(ProviderKey::find($key->id))->toBeNull();
    });

    it('returns 404 for repo-level key', function (): void {
        $key = ProviderKey::factory()
            ->anthropic()
            ->create(['workspace_id' => $this->workspace->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('workspace-provider-keys.destroy', [$this->workspace, $key]))
            ->assertNotFound();
    });

    it('returns 404 for key from another workspace', function (): void {
        $otherWorkspace = Workspace::factory()->create();
        $key = ProviderKey::factory()
            ->forWorkspaceLevel($otherWorkspace)
            ->anthropic()
            ->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(route('workspace-provider-keys.destroy', [$this->workspace, $key]))
            ->assertNotFound();
    });
});
