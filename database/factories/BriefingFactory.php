<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataTransferObjects\Briefings\BriefingPropertyFormat;
use App\DataTransferObjects\Briefings\BriefingSchema;
use App\DataTransferObjects\Briefings\BriefingSchemaProperty;
use App\Models\Briefing;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Briefing>
 */
final class BriefingFactory extends Factory
{
    protected $model = Briefing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $title */
        $title = fake()->words(3, true);

        return [
            'workspace_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->randomNumber(5),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['chart-bar', 'users', 'trophy', 'clock', 'code']),
            'target_roles' => ['engineering_manager', 'developer'],
            'parameter_schema' => BriefingSchema::make()
                ->optionalArray(
                    'repository_ids',
                    BriefingSchemaProperty::integer(description: 'Repository ID'),
                    description: 'Filter by specific repositories',
                )
                ->optionalString(
                    'start_date',
                    description: 'Start of the period',
                    format: BriefingPropertyFormat::Date,
                )
                ->optionalString(
                    'end_date',
                    description: 'End of the period',
                    format: BriefingPropertyFormat::Date,
                )
                ->build()
                ->toArray(),
            'prompt_path' => 'briefings.prompts.default',
            'requires_ai' => true,
            'eligible_plan_ids' => null,
            'output_formats' => ['html', 'pdf'],
            'is_schedulable' => true,
            'is_system' => true,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Create a system briefing.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'workspace_id' => null,
            'is_system' => true,
        ]);
    }

    /**
     * Create a workspace-specific briefing.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes): array => [
            'workspace_id' => $workspace->id,
            'is_system' => false,
        ]);
    }

    /**
     * Create a briefing that doesn't require AI.
     */
    public function withoutAi(): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires_ai' => false,
            'prompt_path' => null,
        ]);
    }

    /**
     * Create an inactive briefing.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a briefing not available for scheduling.
     */
    public function notSchedulable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_schedulable' => false,
        ]);
    }

    /**
     * Restrict to specific plan IDs.
     *
     * @param  array<int>  $planIds
     */
    public function forPlans(array $planIds): static
    {
        return $this->state(fn (array $attributes): array => [
            'eligible_plan_ids' => $planIds,
        ]);
    }
}
