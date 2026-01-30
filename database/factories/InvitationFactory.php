<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Workspace\TeamRole;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
final class InvitationFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Invitation>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'workspace_id' => Workspace::factory(),
            'team_id' => Team::factory(),
            'invited_by_id' => User::factory(),
            'role' => TeamRole::Member,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    /**
     * Set the invitation as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Set the invitation as accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now(),
        ]);
    }

    /**
     * Set the role as admin.
     */
    public function asAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TeamRole::Admin,
        ]);
    }

    /**
     * Set the role as member.
     */
    public function asMember(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TeamRole::Member,
        ]);
    }

    /**
     * Set the workspace for the invitation.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        $team = $workspace->team;

        return $this->state(fn (array $attributes): array => [
            'workspace_id' => $workspace->id,
            'team_id' => $team?->id,
        ]);
    }

    /**
     * Set the inviter for the invitation.
     */
    public function invitedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'invited_by_id' => $user->id,
        ]);
    }
}
