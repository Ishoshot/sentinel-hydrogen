<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
final class RunFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Run>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workspace = Workspace::factory();

        return [
            'workspace_id' => $workspace,
            'repository_id' => Repository::factory()->state(fn (): array => [
                'workspace_id' => $workspace,
            ]),
            'external_reference' => sprintf('github:pull_request:%s:%s', fake()->uuid(), fake()->sha1()),
            'status' => RunStatus::Queued,
            'started_at' => now(),
            'completed_at' => null,
            'metrics' => null,
            'policy_snapshot' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Set the run as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Set the repository for the run.
     */
    public function forRepository(Repository $repository): static
    {
        return $this->state(fn (array $attributes): array => [
            'repository_id' => $repository->id,
            'workspace_id' => $repository->workspace_id,
        ]);
    }
}
