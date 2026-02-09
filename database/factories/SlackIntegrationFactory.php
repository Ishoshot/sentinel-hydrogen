<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SlackIntegration;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlackIntegration>
 */
final class SlackIntegrationFactory extends Factory
{
    protected $model = SlackIntegration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'access_token' => 'xoxb-'.fake()->regexify('[0-9]{12}-[0-9]{12}-[a-zA-Z0-9]{24}'),
            'bot_user_id' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
            'slack_team_id' => 'T'.fake()->regexify('[A-Z0-9]{10}'),
            'team_name' => fake()->company(),
            'scope' => 'chat:write,channels:read',
            'authed_user_id' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
            'channel_id' => 'C'.fake()->regexify('[A-Z0-9]{10}'),
            'channel_name' => '#general',
            'is_active' => true,
            'connected_at' => now(),
        ];
    }

    /**
     * Set as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
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
     * Connected but without a channel selected.
     */
    public function withoutChannel(): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel_id' => null,
            'channel_name' => null,
        ]);
    }

    /**
     * Pending OAuth state (initiated but not yet completed).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'access_token' => null,
            'bot_user_id' => null,
            'slack_team_id' => null,
            'team_name' => null,
            'scope' => null,
            'authed_user_id' => null,
            'channel_id' => null,
            'channel_name' => null,
            'state' => fake()->regexify('[a-zA-Z0-9]{40}'),
            'state_expires_at' => now()->addMinutes(15),
            'is_active' => false,
            'connected_at' => null,
        ]);
    }
}
