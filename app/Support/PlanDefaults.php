<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PlanFeature;
use App\Enums\PlanTier;

final class PlanDefaults
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            PlanTier::Foundation->value => [
                'description' => 'For individual developers and small projects getting started with trusted code review.',
                'monthly_runs_limit' => 20,
                'monthly_commands_limit' => 50,
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
            ],
            PlanTier::Illuminate->value => [
                'description' => 'For growing teams that want deeper insight and consistent code quality across projects.',
                'monthly_runs_limit' => 500,
                'monthly_commands_limit' => 200,
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
            ],
            PlanTier::Orchestrate->value => [
                'description' => 'For professional teams coordinating code quality at scale across multiple repositories.',
                'monthly_runs_limit' => 2000,
                'monthly_commands_limit' => 1000,
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
            ],
            PlanTier::Sanctum->value => [
                'description' => 'For organizations that require governance, security, and reliability guarantees.',
                'monthly_runs_limit' => null,
                'monthly_commands_limit' => null,
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
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTier(PlanTier $tier): array
    {
        $defaults = self::all();

        return $defaults[$tier->value];
    }
}
