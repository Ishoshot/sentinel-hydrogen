<?php

declare(strict_types=1);

use App\Enums\PlanFeature;

it('returns all values', function (): void {
    $values = PlanFeature::values();

    expect($values)->toBeArray()
        ->toContain('byok_enabled')
        ->toContain('custom_guidelines')
        ->toContain('priority_queue')
        ->toContain('api_access')
        ->toContain('sso_enabled')
        ->toContain('audit_logs');
});

it('returns correct labels', function (): void {
    expect(PlanFeature::ByokEnabled->label())->toBe('Bring Your Own Key');
    expect(PlanFeature::CustomGuidelines->label())->toBe('Custom Guidelines');
    expect(PlanFeature::PriorityQueue->label())->toBe('Priority Queue');
    expect(PlanFeature::ApiAccess->label())->toBe('API Access');
    expect(PlanFeature::SsoEnabled->label())->toBe('Single Sign-On');
    expect(PlanFeature::AuditLogs->label())->toBe('Audit Logs');
});

it('returns correct descriptions', function (): void {
    expect(PlanFeature::ByokEnabled->description())->toBe('Use your own API keys for AI providers');
    expect(PlanFeature::CustomGuidelines->description())->toBe('Define custom review guidelines');
    expect(PlanFeature::PriorityQueue->description())->toBe('Higher priority queue for reviews');
    expect(PlanFeature::ApiAccess->description())->toBe('Programmatic API access');
    expect(PlanFeature::SsoEnabled->description())->toBe('Enterprise SSO integration');
    expect(PlanFeature::AuditLogs->description())->toBe('Detailed audit logging');
});
