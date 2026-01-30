<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Workspace\ActivityType;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Activity>
 */
final class ActivityFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Activity>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'actor_id' => User::factory(),
            'type' => ActivityType::WorkspaceCreated,
            'subject_type' => null,
            'subject_id' => null,
            'description' => fake()->sentence(),
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Mark the activity as a system action (no actor).
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'actor_id' => null,
        ]);
    }

    /**
     * Set the activity type.
     */
    public function type(ActivityType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    /**
     * Set the actor for the activity.
     */
    public function byActor(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'actor_id' => $user->id,
        ]);
    }

    /**
     * Set the workspace for the activity.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes): array => [
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Set the subject for the activity.
     */
    public function forSubject(Model $subject): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
        ]);
    }

    /**
     * Set the metadata for the activity.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a workspace created activity.
     */
    public function workspaceCreated(): static
    {
        return $this->type(ActivityType::WorkspaceCreated);
    }

    /**
     * Create a member invited activity.
     */
    public function memberInvited(): static
    {
        return $this->type(ActivityType::MemberInvited);
    }

    /**
     * Create a member joined activity.
     */
    public function memberJoined(): static
    {
        return $this->type(ActivityType::MemberJoined);
    }

    /**
     * Create a GitHub connected activity.
     */
    public function githubConnected(): static
    {
        return $this->type(ActivityType::GitHubConnected);
    }
}
