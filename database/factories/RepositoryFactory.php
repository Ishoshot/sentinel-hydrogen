<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Installation;
use App\Models\Repository;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
final class RepositoryFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Repository>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $owner = fake()->userName();
        $name = fake()->slug(2);

        return [
            'installation_id' => Installation::factory(),
            'workspace_id' => Workspace::factory(),
            'github_id' => fake()->unique()->numberBetween(100000000, 999999999),
            'name' => $name,
            'full_name' => sprintf('%s/%s', $owner, $name),
            'private' => fake()->boolean(30),
            'default_branch' => 'main',
            'language' => fake()->randomElement(['PHP', 'JavaScript', 'TypeScript', 'Python', 'Go', 'Rust']),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Set as a public repository.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'private' => false,
        ]);
    }

    /**
     * Set as a private repository.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'private' => true,
        ]);
    }

    /**
     * Set a specific language.
     */
    public function language(string $language): static
    {
        return $this->state(fn (array $attributes): array => [
            'language' => $language,
        ]);
    }

    /**
     * Set the installation for the repository.
     */
    public function forInstallation(Installation $installation): static
    {
        return $this->state(fn (array $attributes): array => [
            'installation_id' => $installation->id,
            'workspace_id' => $installation->workspace_id,
        ]);
    }

    /**
     * Set a specific full name.
     */
    public function withFullName(string $owner, string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
            'full_name' => sprintf('%s/%s', $owner, $name),
        ]);
    }
}
