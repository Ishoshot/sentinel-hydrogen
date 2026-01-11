<?php

declare(strict_types=1);

use App\DataTransferObjects\SentinelConfig\CategoriesConfig;
use App\DataTransferObjects\SentinelConfig\ReviewConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Enums\ProviderType;
use App\Enums\SentinelConfigSeverity;
use App\Enums\SentinelConfigTone;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Services\Reviews\ReviewPolicyResolver;

/**
 * Helper function to create a repository with proper provider chain.
 */
function createRepositoryWithProvider(): Repository
{
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create([
        'installation_id' => fake()->unique()->randomNumber(8),
    ]);

    return Repository::factory()->forInstallation($installation)->create();
}

it('returns default policy when repository has no settings', function (): void {
    $repository = createRepositoryWithProvider();

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy)->toHaveKey('policy_version')
        ->and($policy['policy_version'])->toBe(1)
        ->and($policy['enabled_rules'])->toBe(['summary_only'])
        ->and($policy['severity_thresholds']['comment'])->toBe('medium')
        ->and($policy['comment_limits']['max_inline_comments'])->toBe(10);
});

it('merges repository review_rules with default policy', function (): void {
    $repository = createRepositoryWithProvider();

    // Create settings without sentinel_config to test review_rules merging
    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'review_rules' => [
            'comment_limits' => ['max_inline_comments' => 20],
            'custom_key' => 'custom_value',
        ],
        'sentinel_config' => null, // No sentinel config
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    // Default sentinel config will be applied (25 max findings)
    // But then we verify custom_key was merged
    expect($policy['custom_key'])->toBe('custom_value')
        ->and($policy['policy_version'])->toBe(1);
});

it('merges sentinel config review settings into policy', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(
            minSeverity: SentinelConfigSeverity::High,
            maxFindings: 15,
            categories: new CategoriesConfig(
                security: true,
                correctness: true,
                performance: false,
                maintainability: false,
                style: false,
            ),
            tone: SentinelConfigTone::Direct,
            language: 'es',
            focus: ['SQL injection prevention', 'Input validation'],
        ),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    // Check min_severity mapping
    expect($policy['severity_thresholds']['comment'])->toBe('high');

    // Check max_findings mapping
    expect($policy['comment_limits']['max_inline_comments'])->toBe(15);

    // Check enabled categories are added to enabled_rules
    expect($policy['enabled_rules'])->toContain('security')
        ->and($policy['enabled_rules'])->toContain('correctness')
        ->and($policy['enabled_rules'])->not->toContain('performance')
        ->and($policy['enabled_rules'])->not->toContain('maintainability');

    // Check tone, language, and focus
    expect($policy['tone'])->toBe('direct')
        ->and($policy['language'])->toBe('es')
        ->and($policy['focus'])->toBe(['SQL injection prevention', 'Input validation']);
});

it('uses default review config when sentinel config is empty', function (): void {
    $repository = createRepositoryWithProvider();

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => null,
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    // Should use default SentinelConfig values
    expect($policy['severity_thresholds']['comment'])->toBe('low')
        ->and($policy['comment_limits']['max_inline_comments'])->toBe(25)
        ->and($policy['tone'])->toBe('constructive')
        ->and($policy['language'])->toBe('en');
});

it('preserves existing enabled_rules when merging categories', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(
            categories: new CategoriesConfig(
                security: true,
                correctness: false,
                performance: false,
                maintainability: false,
                style: true,
            ),
        ),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'review_rules' => [
            'enabled_rules' => ['summary_only', 'custom_rule'],
        ],
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy['enabled_rules'])->toContain('summary_only')
        ->and($policy['enabled_rules'])->toContain('custom_rule')
        ->and($policy['enabled_rules'])->toContain('security')
        ->and($policy['enabled_rules'])->toContain('style');
});

it('does not add focus when focus array is empty', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(
            focus: [],
        ),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy)->not->toHaveKey('focus');
});

it('applies constructive tone', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(tone: SentinelConfigTone::Constructive),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy['tone'])->toBe('constructive');
});

it('applies direct tone', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(tone: SentinelConfigTone::Direct),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy['tone'])->toBe('direct');
});

it('applies educational tone', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(tone: SentinelConfigTone::Educational),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy['tone'])->toBe('educational');
});

it('applies minimal tone', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(tone: SentinelConfigTone::Minimal),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy['tone'])->toBe('minimal');
});

it('applies critical severity level', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(minSeverity: SentinelConfigSeverity::Critical),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy['severity_thresholds']['comment'])->toBe('critical');
});

it('applies medium severity level', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(minSeverity: SentinelConfigSeverity::Medium),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy['severity_thresholds']['comment'])->toBe('medium');
});

it('applies info severity level', function (): void {
    $repository = createRepositoryWithProvider();

    $sentinelConfig = new SentinelConfig(
        version: 1,
        review: new ReviewConfig(minSeverity: SentinelConfigSeverity::Info),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    expect($policy['severity_thresholds']['comment'])->toBe('info');
});
