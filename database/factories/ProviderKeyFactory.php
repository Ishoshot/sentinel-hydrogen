<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AI\AiProvider;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderKey>
 */
final class ProviderKeyFactory extends Factory
{
    /**
     * @use RefreshOnCreate<ProviderKey>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'workspace_id' => Workspace::factory(),
            'provider' => AiProvider::Anthropic,
            'encrypted_key' => 'sk-ant-test-'.fake()->sha256(),
        ];
    }

    /**
     * Set the provider to Anthropic.
     */
    public function anthropic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::Anthropic,
            'encrypted_key' => 'sk-ant-test-'.fake()->sha256(),
        ]);
    }

    /**
     * Set the provider to OpenAI.
     */
    public function openai(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::OpenAI,
            'encrypted_key' => 'sk-proj-test-'.fake()->sha256(),
        ]);
    }

    /**
     * Set the repository for the provider key.
     */
    public function forRepository(Repository $repository): static
    {
        return $this->state(fn (array $attributes): array => [
            'repository_id' => $repository->id,
            'workspace_id' => $repository->workspace_id,
        ]);
    }
}
