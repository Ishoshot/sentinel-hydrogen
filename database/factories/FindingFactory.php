<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Finding;
use App\Models\Run;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
final class FindingFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Finding>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'workspace_id' => Workspace::factory(),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'category' => fake()->randomElement(['correctness', 'security', 'reliability', 'maintainability']),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'file_path' => fake()->optional()->filePath(),
            'line_start' => fake()->optional()->numberBetween(1, 400),
            'line_end' => fake()->optional()->numberBetween(1, 400),
            'confidence' => fake()->optional()->randomFloat(2, 0.5, 1.0),
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Set the run for the finding.
     */
    public function forRun(Run $run): static
    {
        return $this->state(fn (array $attributes): array => [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
        ]);
    }
}
