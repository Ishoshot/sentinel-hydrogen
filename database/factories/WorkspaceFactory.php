<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<Workspace>
 */
final class WorkspaceFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Workspace>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'owner_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'subscription_status' => \App\Enums\SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'settings' => null,
        ];
    }

    /**
     * Configure the factory.
     */
    #[Override]
    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace): void {
            if ($workspace->team === null) {
                $workspace->team()->create([
                    'name' => $workspace->name,
                ]);
            }
        });
    }

    /**
     * Set a specific owner for the workspace.
     */
    public function ownedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'owner_id' => $user->id,
        ]);
    }

    /**
     * Create a personal workspace for the user.
     */
    public function personal(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $user->name."'s Workspace",
            'slug' => Str::slug($user->name).'-'.Str::random(6),
            'owner_id' => $user->id,
        ]);
    }

    /**
     * Add custom settings to the workspace.
     *
     * @param  array<string, mixed>  $settings
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes): array => [
            'settings' => $settings,
        ]);
    }
}
