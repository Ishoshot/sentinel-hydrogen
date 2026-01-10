<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProviderType;
use App\Models\Provider;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
final class ProviderFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Provider>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ProviderType::GitHub,
            'name' => 'GitHub',
            'is_active' => true,
            'settings' => null,
        ];
    }

    /**
     * Set the provider as GitHub.
     */
    public function github(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ProviderType::GitHub,
            'name' => 'GitHub',
        ]);
    }

    /**
     * Set the provider as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
