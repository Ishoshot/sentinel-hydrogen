<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Provider;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Annotation>
 */
final class AnnotationFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Annotation>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finding_id' => Finding::factory(),
            'workspace_id' => Workspace::factory(),
            'provider_id' => Provider::factory(),
            'external_id' => fake()->optional()->uuid(),
            'type' => fake()->randomElement(['inline', 'summary', 'check']),
            'created_at' => now(),
        ];
    }

    /**
     * Set the finding for the annotation.
     */
    public function forFinding(Finding $finding): static
    {
        return $this->state(fn (array $attributes): array => [
            'finding_id' => $finding->id,
            'workspace_id' => $finding->workspace_id,
        ]);
    }

    /**
     * Set the provider for the annotation.
     */
    public function forProvider(Provider $provider): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider_id' => $provider->id,
        ]);
    }
}
