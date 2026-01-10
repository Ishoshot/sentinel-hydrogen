<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepositorySettings>
 */
final class RepositorySettingsFactory extends Factory
{
    /**
     * @use RefreshOnCreate<RepositorySettings>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'workspace_id' => Workspace::factory(),
            'auto_review_enabled' => true,
            'review_rules' => null,
        ];
    }

    /**
     * Enable auto review.
     */
    public function autoReviewEnabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'auto_review_enabled' => true,
        ]);
    }

    /**
     * Disable auto review.
     */
    public function autoReviewDisabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'auto_review_enabled' => false,
        ]);
    }

    /**
     * Set review rules.
     *
     * @param  array<string, mixed>  $rules
     */
    public function withReviewRules(array $rules): static
    {
        return $this->state(fn (array $attributes): array => [
            'review_rules' => $rules,
        ]);
    }

    /**
     * Set the repository for the settings.
     */
    public function forRepository(Repository $repository): static
    {
        return $this->state(fn (array $attributes): array => [
            'repository_id' => $repository->id,
            'workspace_id' => $repository->workspace_id,
        ]);
    }
}
