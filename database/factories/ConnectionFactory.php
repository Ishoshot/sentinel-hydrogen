<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\Connection;
use App\Models\Provider;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
final class ConnectionFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Connection>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'provider_id' => Provider::factory(),
            'status' => ConnectionStatus::Active,
            'external_id' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'metadata' => null,
        ];
    }

    /**
     * Set the connection as pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConnectionStatus::Pending,
            'external_id' => null,
        ]);
    }

    /**
     * Set the connection as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConnectionStatus::Active,
        ]);
    }

    /**
     * Set the connection as disconnected.
     */
    public function disconnected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConnectionStatus::Disconnected,
        ]);
    }

    /**
     * Set the connection as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConnectionStatus::Failed,
        ]);
    }

    /**
     * Set the workspace for the connection.
     */
    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn (array $attributes): array => [
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Set the provider for the connection.
     */
    public function forProvider(Provider $provider): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider_id' => $provider->id,
        ]);
    }
}
