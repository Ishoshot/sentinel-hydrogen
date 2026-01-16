<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiOption;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiOption>
 */
final class AiOptionFactory extends Factory
{
    /**
     * @use RefreshOnCreate<AiOption>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(AiProvider::cases()),
            'identifier' => fake()->unique()->slug(3),
            'name' => fake()->words(3, true),
            'description' => null,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Set the provider to Anthropic with Claude Sonnet 4.5.
     */
    public function anthropicSonnet45(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::Anthropic,
            'identifier' => 'claude-sonnet-4-5-20250929',
            'name' => 'Claude Sonnet 4.5',
            'is_default' => true,
        ]);
    }

    /**
     * Set the provider to Anthropic with Claude Sonnet 4.
     */
    public function anthropicSonnet4(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::Anthropic,
            'identifier' => 'claude-sonnet-4-20250514',
            'name' => 'Claude Sonnet 4',
        ]);
    }

    /**
     * Set the provider to Anthropic with Claude Haiku 3.5.
     */
    public function anthropicHaiku35(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::Anthropic,
            'identifier' => 'claude-3-5-haiku-20241022',
            'name' => 'Claude Haiku 3.5',
        ]);
    }

    /**
     * Set the provider to OpenAI with GPT-4o.
     */
    public function openaiGpt4o(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::OpenAI,
            'identifier' => 'gpt-4o',
            'name' => 'GPT-4o',
            'is_default' => true,
        ]);
    }

    /**
     * Set the provider to OpenAI with GPT-4o Mini.
     */
    public function openaiGpt4oMini(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => AiProvider::OpenAI,
            'identifier' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
        ]);
    }

    /**
     * Mark this model as the default for its provider.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }

    /**
     * Mark this model as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
