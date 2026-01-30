<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Workspace\TeamRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
final class TeamMemberFactory extends Factory
{
    /**
     * @use RefreshOnCreate<TeamMember>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'workspace_id' => Workspace::factory(),
            'role' => TeamRole::Member,
            'joined_at' => now(),
        ];
    }

    /**
     * Set the role as owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TeamRole::Owner,
        ]);
    }

    /**
     * Set the role as admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TeamRole::Admin,
        ]);
    }

    /**
     * Set the role as member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TeamRole::Member,
        ]);
    }

    /**
     * Set the user for the membership.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set the workspace and team for the membership.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        $team = $workspace->team;

        return $this->state(fn (array $attributes): array => [
            'workspace_id' => $workspace->id,
            'team_id' => $team?->id,
        ]);
    }
}
