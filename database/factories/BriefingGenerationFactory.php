<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BriefingGeneration>
 */
final class BriefingGenerationFactory extends Factory
{
    /**
     * @use RefreshOnCreate<BriefingGeneration>
     */
    use RefreshOnCreate;

    protected $model = BriefingGeneration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workspace = Workspace::factory();

        return [
            'workspace_id' => $workspace,
            'briefing_id' => Briefing::factory(),
            'generated_by_id' => User::factory(),
            'parameters' => [
                'date_from' => now()->subWeek()->toDateString(),
                'date_to' => now()->toDateString(),
            ],
            'status' => BriefingGenerationStatus::Pending,
            'progress' => 0,
            'progress_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'narrative' => null,
            'structured_data' => null,
            'achievements' => null,
            'excerpts' => null,
            'output_paths' => null,
            'metadata' => null,
            'error_message' => null,
            'expires_at' => now()->addDays(90),
            'created_at' => now(),
        ];
    }

    /**
     * Set as pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BriefingGenerationStatus::Pending,
            'progress' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Set as processing status.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BriefingGenerationStatus::Processing,
            'progress' => fake()->numberBetween(10, 90),
            'progress_message' => 'Processing data...',
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * Set as completed status.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BriefingGenerationStatus::Completed,
            'progress' => 100,
            'progress_message' => null,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'narrative' => fake()->paragraphs(3, true),
            'structured_data' => [
                'slides' => [
                    ['type' => 'title', 'headline' => 'Weekly Summary'],
                    ['type' => 'stat_hero', 'value' => 47, 'label' => 'PRs Merged'],
                ],
            ],
            'achievements' => [
                ['type' => 'milestone', 'icon' => '🎯', 'title' => 'Century Club', 'description' => 'Team merged 100 PRs'],
            ],
            'excerpts' => [
                'slack' => '🚀 *Weekly Summary* | 47 PRs merged',
                'tweet' => 'Great week! 47 PRs merged. #engineering',
            ],
            'output_paths' => [
                'html' => 'briefings/1/1/html.html',
                'pdf' => 'briefings/1/1/pdf.pdf',
            ],
            'metadata' => [
                'tokens_used' => 1500,
                'duration_ms' => 45000,
            ],
        ]);
    }

    /**
     * Set as failed status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BriefingGenerationStatus::Failed,
            'progress' => 30,
            'started_at' => now()->subMinutes(1),
            'completed_at' => now(),
            'error_message' => 'Failed to generate narrative: AI provider unavailable',
        ]);
    }

    /**
     * Set for a specific workspace.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes): array => [
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Set for a specific briefing.
     */
    public function forBriefing(Briefing $briefing): static
    {
        return $this->state(fn (array $attributes): array => [
            'briefing_id' => $briefing->id,
        ]);
    }

    /**
     * Set as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
