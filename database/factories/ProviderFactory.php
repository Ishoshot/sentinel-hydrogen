<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Auth\ProviderType;
use App\Models\Provider;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Override;

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
     * Create a model instance, reusing existing provider if type already exists.
     *
     * @param  array<string, mixed>  $attributes
     */
    #[Override]
    public function create($attributes = [], ?Model $parent = null): Provider
    {
        $type = $attributes['type'] ?? ProviderType::GitHub;

        if ($type instanceof ProviderType) {
            $type = $type->value;
        }

        $existing = Provider::query()->where('type', $type)->first();

        if ($existing instanceof Provider) {
            return $existing;
        }

        /** @var Provider $created */
        $created = parent::create($attributes, $parent);

        return $created;
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
