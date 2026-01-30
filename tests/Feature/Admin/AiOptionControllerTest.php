<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Models\Admin;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Repository;

beforeEach(function (): void {
    $this->admin = Admin::factory()->create();
});

describe('index', function (): void {
    it('requires authentication', function (): void {
        $this->getJson(route('admin.ai-options.index'))
            ->assertUnauthorized();
    });

    it('lists all ai options', function (): void {
        AiOption::factory()->count(3)->create();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.ai-options.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'provider', 'identifier', 'name', 'is_default', 'is_active', 'sort_order', 'context_window_tokens', 'max_output_tokens'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('filters by provider', function (): void {
        AiOption::factory()->count(2)->create(['provider' => AiProvider::Anthropic]);
        AiOption::factory()->create(['provider' => AiProvider::OpenAI]);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.ai-options.index', ['provider' => 'anthropic']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('filters active options only', function (): void {
        AiOption::factory()->count(2)->create(['is_active' => true]);
        AiOption::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.ai-options.index', ['active_only' => true]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('store', function (): void {
    it('creates an ai option', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.ai-options.store'), [
                'provider' => AiProvider::Anthropic->value,
                'identifier' => 'claude-test-model',
                'name' => 'Claude Test Model',
                'description' => 'A test model',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 0,
                'context_window_tokens' => 200000,
                'max_output_tokens' => 64000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'anthropic')
            ->assertJsonPath('data.identifier', 'claude-test-model')
            ->assertJsonPath('data.name', 'Claude Test Model')
            ->assertJsonPath('data.context_window_tokens', 200000)
            ->assertJsonPath('data.max_output_tokens', 64000)
            ->assertJsonPath('message', 'AI model created successfully.');

        $this->assertDatabaseHas('provider_models', [
            'identifier' => 'claude-test-model',
            'name' => 'Claude Test Model',
            'context_window_tokens' => 200000,
            'max_output_tokens' => 64000,
        ]);
    });

    it('creates an ai option with nullable token limits', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.ai-options.store'), [
                'provider' => AiProvider::Anthropic->value,
                'identifier' => 'claude-no-limits',
                'name' => 'Claude No Limits',
            ])
            ->assertCreated()
            ->assertJsonPath('data.context_window_tokens', null)
            ->assertJsonPath('data.max_output_tokens', null);
    });

    it('validates required fields', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.ai-options.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider', 'identifier', 'name']);
    });

    it('validates unique identifier per provider', function (): void {
        AiOption::factory()->create([
            'provider' => AiProvider::Anthropic,
            'identifier' => 'existing-model',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.ai-options.store'), [
                'provider' => AiProvider::Anthropic->value,
                'identifier' => 'existing-model',
                'name' => 'New Model',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identifier']);
    });

    it('allows same identifier for different providers', function (): void {
        AiOption::factory()->create([
            'provider' => AiProvider::Anthropic,
            'identifier' => 'shared-model',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.ai-options.store'), [
                'provider' => AiProvider::OpenAI->value,
                'identifier' => 'shared-model',
                'name' => 'OpenAI Model',
            ])
            ->assertCreated();
    });

    it('unsets other defaults when creating a new default', function (): void {
        $existing = AiOption::factory()->create([
            'provider' => AiProvider::Anthropic,
            'is_default' => true,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.ai-options.store'), [
                'provider' => AiProvider::Anthropic->value,
                'identifier' => 'new-default',
                'name' => 'New Default',
                'is_default' => true,
            ])
            ->assertCreated();

        expect($existing->refresh()->is_default)->toBeFalse();
    });
});

describe('show', function (): void {
    it('returns a single ai option', function (): void {
        $aiOption = AiOption::factory()->create(['name' => 'Show Test']);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.ai-options.show', ['ai_option' => $aiOption]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Show Test');
    });

    it('returns 404 for non-existent ai option', function (): void {
        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.ai-options.show', ['ai_option' => 99999]))
            ->assertNotFound();
    });
});

describe('update', function (): void {
    it('updates an ai option', function (): void {
        $aiOption = AiOption::factory()->create(['name' => 'Original']);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.ai-options.update', ['ai_option' => $aiOption]), [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated')
            ->assertJsonPath('message', 'AI model updated successfully.');

        $this->assertDatabaseHas('provider_models', [
            'id' => $aiOption->id,
            'name' => 'Updated',
        ]);
    });

    it('unsets other defaults when updating to default', function (): void {
        $existing = AiOption::factory()->create([
            'provider' => AiProvider::Anthropic,
            'is_default' => true,
        ]);
        $aiOption = AiOption::factory()->create([
            'provider' => AiProvider::Anthropic,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.ai-options.update', ['ai_option' => $aiOption]), [
                'is_default' => true,
            ])
            ->assertOk();

        expect($existing->refresh()->is_default)->toBeFalse();
        expect($aiOption->refresh()->is_default)->toBeTrue();
    });

    it('allows updating identifier to unique value', function (): void {
        $aiOption = AiOption::factory()->create(['identifier' => 'old-id']);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.ai-options.update', ['ai_option' => $aiOption]), [
                'identifier' => 'new-id',
            ])
            ->assertOk()
            ->assertJsonPath('data.identifier', 'new-id');
    });

    it('updates token limits', function (): void {
        $aiOption = AiOption::factory()->create([
            'context_window_tokens' => 100000,
            'max_output_tokens' => 8000,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.ai-options.update', ['ai_option' => $aiOption]), [
                'context_window_tokens' => 200000,
                'max_output_tokens' => 64000,
            ])
            ->assertOk()
            ->assertJsonPath('data.context_window_tokens', 200000)
            ->assertJsonPath('data.max_output_tokens', 64000);

        $this->assertDatabaseHas('provider_models', [
            'id' => $aiOption->id,
            'context_window_tokens' => 200000,
            'max_output_tokens' => 64000,
        ]);
    });

    it('allows setting token limits to null', function (): void {
        $aiOption = AiOption::factory()->create([
            'context_window_tokens' => 200000,
            'max_output_tokens' => 64000,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.ai-options.update', ['ai_option' => $aiOption]), [
                'context_window_tokens' => null,
                'max_output_tokens' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.context_window_tokens', null)
            ->assertJsonPath('data.max_output_tokens', null);
    });
});

describe('destroy', function (): void {
    it('deletes an ai option', function (): void {
        $aiOption = AiOption::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->deleteJson(route('admin.ai-options.destroy', ['ai_option' => $aiOption]))
            ->assertOk()
            ->assertJsonPath('message', 'AI model deleted successfully.');

        $this->assertDatabaseMissing('provider_models', ['id' => $aiOption->id]);
    });

    it('prevents deletion when in use by provider keys', function (): void {
        $aiOption = AiOption::factory()->create();
        $repository = Repository::factory()->create();
        ProviderKey::factory()->create([
            'repository_id' => $repository->id,
            'provider' => $aiOption->provider,
            'provider_model_id' => $aiOption->id,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson(route('admin.ai-options.destroy', ['ai_option' => $aiOption]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete this AI model as it is currently in use by 1 provider key(s).');

        $this->assertDatabaseHas('provider_models', ['id' => $aiOption->id]);
    });
});
