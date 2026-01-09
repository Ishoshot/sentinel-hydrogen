<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
final class TeamFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Team>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'workspace_id' => Workspace::factory(),
        ];
    }

    /**
     * Set the workspace for the team.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $workspace->name,
            'workspace_id' => $workspace->id,
        ]);
    }
}
