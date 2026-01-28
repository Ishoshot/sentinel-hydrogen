<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Partner;
use App\Models\IncomingWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomingWebhook>
 */
final class IncomingWebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner' => fake()->randomElement(Partner::cases()),
            'webhook_id' => fake()->uuid(),
            'event_type' => fake()->randomElement(['order.paid', 'subscription.created', 'subscription.canceled']),
            'payload' => [
                'type' => 'order.paid',
                'data' => [
                    'id' => fake()->uuid(),
                ],
            ],
            'headers' => [
                'webhook-id' => fake()->uuid(),
                'webhook-signature' => fake()->sha256(),
                'webhook-timestamp' => (string) time(),
            ],
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * Mark the webhook as processed.
     */
    public function processed(int $responseCode = 200): static
    {
        return $this->state(fn (array $attributes) => [
            'response_code' => $responseCode,
            'response_body' => ['received' => true],
            'processed_at' => now(),
        ]);
    }

    /**
     * Set the partner to Polar.
     */
    public function polar(): static
    {
        return $this->state(fn (array $attributes) => [
            'partner' => Partner::Polar,
        ]);
    }

    /**
     * Set the partner to GitHub.
     */
    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'partner' => Partner::GitHub,
        ]);
    }
}
