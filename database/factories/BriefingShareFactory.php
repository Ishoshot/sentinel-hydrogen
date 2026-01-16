<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<BriefingShare>
 */
final class BriefingShareFactory extends Factory
{
    protected $model = BriefingShare::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workspace = Workspace::factory();

        return [
            'briefing_generation_id' => BriefingGeneration::factory()->state(fn (): array => [
                'workspace_id' => $workspace,
            ]),
            'workspace_id' => $workspace,
            'created_by_id' => User::factory(),
            'token' => BriefingShare::generateToken(),
            'password_hash' => null,
            'access_count' => 0,
            'max_accesses' => null,
            'expires_at' => now()->addDays(7),
            'is_active' => true,
            'created_at' => now(),
        ];
    }

    /**
     * Set for a specific generation.
     */
    public function forGeneration(BriefingGeneration $generation): static
    {
        return $this->state(fn (array $attributes): array => [
            'briefing_generation_id' => $generation->id,
            'workspace_id' => $generation->workspace_id,
        ]);
    }

    /**
     * Set with password protection.
     */
    public function withPassword(string $password = 'secret123'): static
    {
        return $this->state(fn (array $attributes): array => [
            'password_hash' => Hash::make($password),
        ]);
    }

    /**
     * Set with access limit.
     */
    public function withAccessLimit(int $maxAccesses = 10): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_accesses' => $maxAccesses,
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

    /**
     * Set as revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Set as exhausted (max accesses reached).
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_accesses' => 10,
            'access_count' => 10,
        ]);
    }

    /**
     * Set custom expiry.
     */
    public function expiresIn(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->addDays($days),
        ]);
    }
}
