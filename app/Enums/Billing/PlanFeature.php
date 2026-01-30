<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Plan features that can be enabled or disabled per plan tier.
 */
enum PlanFeature: string
{
    case Briefings = 'briefings';
    case ByokEnabled = 'byok_enabled';
    case CustomGuidelines = 'custom_guidelines';
    case PriorityQueue = 'priority_queue';
    case ApiAccess = 'api_access';
    case SsoEnabled = 'sso_enabled';
    case AuditLogs = 'audit_logs';

    /**
     * Get all plan feature values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label for the feature.
     */
    public function label(): string
    {
        return match ($this) {
            self::Briefings => 'Briefings',
            self::ByokEnabled => 'Bring Your Own Key',
            self::CustomGuidelines => 'Custom Guidelines',
            self::PriorityQueue => 'Priority Queue',
            self::ApiAccess => 'API Access',
            self::SsoEnabled => 'Single Sign-On',
            self::AuditLogs => 'Audit Logs',
        };
    }

    /**
     * Get the description for the feature.
     */
    public function description(): string
    {
        return match ($this) {
            self::Briefings => 'AI-generated team briefings and reports',
            self::ByokEnabled => 'Use your own API keys for AI providers',
            self::CustomGuidelines => 'Define custom review guidelines',
            self::PriorityQueue => 'Higher priority queue for reviews',
            self::ApiAccess => 'Programmatic API access',
            self::SsoEnabled => 'Enterprise SSO integration',
            self::AuditLogs => 'Detailed audit logging',
        };
    }
}
