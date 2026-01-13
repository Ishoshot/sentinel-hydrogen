<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlanFeature;
use App\Enums\PlanTier;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tier' => PlanTier::Foundation->value,
            'description' => 'For individual developers and small projects getting started with trusted code review.',
            'monthly_runs_limit' => 20,
            'team_size_limit' => 2,
            'features' => [
                PlanFeature::ByokEnabled->value => true,
                PlanFeature::CustomGuidelines->value => false,
                PlanFeature::PriorityQueue->value => false,
                PlanFeature::ApiAccess->value => false,
                PlanFeature::SsoEnabled->value => false,
                PlanFeature::AuditLogs->value => false,
            ],
            'price_monthly' => 0,
            'price_yearly' => 0,
        ];
    }

    /**
     * Configure the factory for Illuminate tier.
     */
    public function illuminate(): self
    {
        return $this->state(fn (): array => [
            'tier' => PlanTier::Illuminate->value,
            'description' => 'For growing teams that want deeper insight and consistent code quality across projects.',
            'monthly_runs_limit' => 500,
            'team_size_limit' => 5,
            'features' => [
                PlanFeature::ByokEnabled->value => true,
                PlanFeature::CustomGuidelines->value => true,
                PlanFeature::PriorityQueue->value => true,
                PlanFeature::ApiAccess->value => false,
                PlanFeature::SsoEnabled->value => false,
                PlanFeature::AuditLogs->value => false,
            ],
            'price_monthly' => 2000,
            'price_yearly' => 21000,
        ]);
    }

    /**
     * Configure the factory for Orchestrate tier.
     */
    public function orchestrate(): self
    {
        return $this->state(fn (): array => [
            'tier' => PlanTier::Orchestrate->value,
            'description' => 'For professional teams coordinating code quality at scale across multiple repositories.',
            'monthly_runs_limit' => 2000,
            'team_size_limit' => null,
            'features' => [
                PlanFeature::ByokEnabled->value => true,
                PlanFeature::CustomGuidelines->value => true,
                PlanFeature::PriorityQueue->value => true,
                PlanFeature::ApiAccess->value => true,
                PlanFeature::SsoEnabled->value => false,
                PlanFeature::AuditLogs->value => false,
            ],
            'price_monthly' => 5000,
            'price_yearly' => 45000,
        ]);
    }

    /**
     * Configure the factory for Sanctum tier.
     */
    public function sanctum(): self
    {
        return $this->state(fn (): array => [
            'tier' => PlanTier::Sanctum->value,
            'description' => 'For organizations that require governance, security, and reliability guarantees.',
            'monthly_runs_limit' => null,
            'team_size_limit' => null,
            'features' => [
                PlanFeature::ByokEnabled->value => true,
                PlanFeature::CustomGuidelines->value => true,
                PlanFeature::PriorityQueue->value => true,
                PlanFeature::ApiAccess->value => true,
                PlanFeature::SsoEnabled->value => true,
                PlanFeature::AuditLogs->value => true,
            ],
            'price_monthly' => 20000,
            'price_yearly' => 210000,
        ]);
    }
}
