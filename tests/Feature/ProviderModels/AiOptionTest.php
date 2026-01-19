<?php

declare(strict_types=1);

use App\Enums\AiProvider;
use App\Enums\ProviderType;
use App\Models\AiOption;
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

function createWorkspaceWithRepo(User $owner): array
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

describe('AI Options API', function (): void {
    it('lists active models for anthropic provider', function (): void {
        $user = User::factory()->create();

        AiOption::factory()->anthropicSonnet45()->create();
        AiOption::factory()->anthropicSonnet4()->create();
        AiOption::factory()->anthropicHaiku35()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('ai-options.index', 'anthropic'));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.provider', 'anthropic');
    });

    it('lists active models for openai provider', function (): void {
        $user = User::factory()->create();

        AiOption::factory()->openaiGpt4o()->create();
        AiOption::factory()->openaiGpt4oMini()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('ai-options.index', 'openai'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.provider', 'openai');
    });

    it('does not list inactive models', function (): void {
        $user = User::factory()->create();

        AiOption::factory()->anthropicSonnet45()->create();
        AiOption::factory()->anthropicSonnet4()->inactive()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('ai-options.index', 'anthropic'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('returns 404 for invalid provider', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('ai-options.index', 'invalid'));

        $response->assertNotFound();
    });

    it('requires authentication', function (): void {
        $response = $this->getJson(route('ai-options.index', 'anthropic'));

        $response->assertUnauthorized();
    });
});

describe('Provider Key with Model Selection', function (): void {
    it('can create provider key with model selection', function (): void {
        $user = User::factory()->create();
        [$workspace, $repository] = createWorkspaceWithRepo($user);

        $aiOption = AiOption::factory()->anthropicHaiku35()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('provider-keys.store', [$workspace, $repository]), [
                'provider' => 'anthropic',
                'key' => 'sk-ant-api03-test-key-for-testing',
                'provider_model_id' => $aiOption->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.provider', 'anthropic');

        $this->assertDatabaseHas('provider_keys', [
            'repository_id' => $repository->id,
            'provider' => 'anthropic',
            'provider_model_id' => $aiOption->id,
        ]);
    });

    it('can create provider key without model selection', function (): void {
        $user = User::factory()->create();
        [$workspace, $repository] = createWorkspaceWithRepo($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('provider-keys.store', [$workspace, $repository]), [
                'provider' => 'anthropic',
                'key' => 'sk-ant-api03-test-key-for-testing',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('provider_keys', [
            'repository_id' => $repository->id,
            'provider' => 'anthropic',
            'provider_model_id' => null,
        ]);
    });

    it('rejects provider model from different provider', function (): void {
        $user = User::factory()->create();
        [$workspace, $repository] = createWorkspaceWithRepo($user);

        $openaiOption = AiOption::factory()->openaiGpt4o()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('provider-keys.store', [$workspace, $repository]), [
                'provider' => 'anthropic',
                'key' => 'sk-ant-api03-test-key-for-testing',
                'provider_model_id' => $openaiOption->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid model selected for this provider.');
    });

    it('rejects inactive provider model', function (): void {
        $user = User::factory()->create();
        [$workspace, $repository] = createWorkspaceWithRepo($user);

        $inactiveOption = AiOption::factory()->anthropicSonnet4()->inactive()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('provider-keys.store', [$workspace, $repository]), [
                'provider' => 'anthropic',
                'key' => 'sk-ant-api03-test-key-for-testing',
                'provider_model_id' => $inactiveOption->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid model selected for this provider.');
    });

    it('lists provider keys with model info', function (): void {
        $user = User::factory()->create();
        [$workspace, $repository] = createWorkspaceWithRepo($user);

        $aiOption = AiOption::factory()->anthropicHaiku35()->create();

        ProviderKey::factory()->create([
            'repository_id' => $repository->id,
            'workspace_id' => $workspace->id,
            'provider' => AiProvider::Anthropic,
            'provider_model_id' => $aiOption->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('provider-keys.index', [$workspace, $repository]));

        $response->assertOk()
            ->assertJsonPath('data.0.ai_model.name', 'Claude Haiku 3.5')
            ->assertJsonPath('data.0.ai_model.identifier', 'claude-3-5-haiku-20241022');
    });
});

describe('AiOption Model', function (): void {
    it('gets default model for provider', function (): void {
        AiOption::factory()->anthropicSonnet45()->default()->create();
        AiOption::factory()->anthropicSonnet4()->create();

        $default = AiOption::getDefault(AiProvider::Anthropic);

        expect($default)->not->toBeNull()
            ->and($default->identifier)->toBe('claude-sonnet-4-5-20250929');
    });

    it('returns null when no default model', function (): void {
        AiOption::factory()->anthropicSonnet4()->create();

        $default = AiOption::getDefault(AiProvider::Anthropic);

        expect($default)->toBeNull();
    });

    it('does not return inactive default model', function (): void {
        AiOption::factory()->anthropicSonnet45()->default()->inactive()->create();

        $default = AiOption::getDefault(AiProvider::Anthropic);

        expect($default)->toBeNull();
    });
});
