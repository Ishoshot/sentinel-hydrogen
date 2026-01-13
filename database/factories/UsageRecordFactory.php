<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UsageRecord;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageRecord>
 */
final class UsageRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'workspace_id' => Workspace::factory(),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'runs_count' => fake()->numberBetween(0, 50),
            'findings_count' => fake()->numberBetween(0, 200),
            'annotations_count' => fake()->numberBetween(0, 200),
        ];
    }
}
