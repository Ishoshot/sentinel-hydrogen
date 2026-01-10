<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InstallationStatus;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Workspace;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installation>
 */
final class InstallationFactory extends Factory
{
    /**
     * @use RefreshOnCreate<Installation>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'workspace_id' => Workspace::factory(),
            'installation_id' => fake()->unique()->numberBetween(10000000, 99999999),
            'account_type' => fake()->randomElement(['User', 'Organization']),
            'account_login' => fake()->userName(),
            'account_avatar_url' => fake()->imageUrl(200, 200, 'avatar'),
            'status' => InstallationStatus::Active,
            'permissions' => [
                'contents' => 'read',
                'metadata' => 'read',
                'pull_requests' => 'write',
            ],
            'events' => ['pull_request', 'push'],
            'suspended_at' => null,
        ];
    }

    /**
     * Set the installation as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InstallationStatus::Active,
            'suspended_at' => null,
        ]);
    }

    /**
     * Set the installation as suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InstallationStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    /**
     * Set the installation as uninstalled.
     */
    public function uninstalled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InstallationStatus::Uninstalled,
        ]);
    }

    /**
     * Set as a user installation.
     */
    public function user(): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_type' => 'User',
        ]);
    }

    /**
     * Set as an organization installation.
     */
    public function organization(): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_type' => 'Organization',
        ]);
    }

    /**
     * Set the connection for the installation.
     */
    public function forConnection(Connection $connection): static
    {
        return $this->state(fn (array $attributes): array => [
            'connection_id' => $connection->id,
            'workspace_id' => $connection->workspace_id,
        ]);
    }
}
