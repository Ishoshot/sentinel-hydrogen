<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = now()->subDays(fake()->numberBetween(1, 15));

        return [
            'workspace_id' => Workspace::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'started_at' => $periodStart,
            'ends_at' => null,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodStart->copy()->addMonth(),
            'polar_customer_id' => null,
            'polar_subscription_id' => null,
        ];
    }
}
