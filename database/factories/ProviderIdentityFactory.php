<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OAuthProvider;
use App\Models\ProviderIdentity;
use App\Models\User;
use Database\Factories\Concerns\RefreshOnCreate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProviderIdentity>
 */
final class ProviderIdentityFactory extends Factory
{
    /**
     * @use RefreshOnCreate<ProviderIdentity>
     */
    use RefreshOnCreate;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(OAuthProvider::cases()),
            'provider_user_id' => (string) fake()->unique()->randomNumber(8),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'avatar_url' => fake()->imageUrl(200, 200, 'people'),
            'access_token' => Str::random(40),
            'refresh_token' => Str::random(40),
            'token_expires_at' => now()->addHour(),
        ];
    }

    /**
     * Set the provider as GitHub.
     */
    public function github(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => OAuthProvider::GitHub,
        ]);
    }

    /**
     * Set the provider as Google.
     */
    public function google(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => OAuthProvider::Google,
        ]);
    }

    /**
     * Set the user for the identity.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    /**
     * Set the token as expired.
     */
    public function expiredToken(): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_expires_at' => now()->subHour(),
        ]);
    }
}
