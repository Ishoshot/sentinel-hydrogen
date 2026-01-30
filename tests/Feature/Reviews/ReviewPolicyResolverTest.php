<?php

declare(strict_types=1);

use App\DataTransferObjects\SentinelConfig\CategoriesConfig;
use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\DataTransferObjects\SentinelConfig\ReviewConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Enums\Auth\ProviderType;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Enums\SentinelConfig\SentinelConfigTone;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Services\Reviews\ReviewPolicyResolver;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

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

    // Default values match config/reviews.php and SentinelConfig::default()
    expect($policy)->toBeInstanceOf(ReviewPolicy::class)
        ->and($policy->enabledRules)->toBe(['security', 'correctness', 'performance', 'maintainability', 'testing'])
        ->and($policy->getCommentSeverityThreshold()->value)->toBe('low')
        ->and($policy->getMaxInlineComments())->toBe(25)
        ->and($policy->tone->value)->toBe('constructive')
        ->and($policy->language)->toBe('en');
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
                testing: false,
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
    expect($policy->getCommentSeverityThreshold()->value)->toBe('high');

    // Check max_findings mapping
    expect($policy->getMaxInlineComments())->toBe(15);

    // Check enabled categories replace enabled_rules (not merge)
    // User set performance: false and maintainability: false, so they should NOT be in enabled_rules
    expect($policy->enabledRules)->toBe(['security', 'correctness']);

    // Check tone, language, and focus
    expect($policy->tone->value)->toBe('direct')
        ->and($policy->language)->toBe('es')
        ->and($policy->focus)->toBe(['SQL injection prevention', 'Input validation']);
});

it('prefers branch sentinel config when provided', function (): void {
    $repository = createRepositoryWithProvider();

    $settingsConfig = new SentinelConfig(
        version: 1,
        paths: new PathsConfig(ignore: ['ignored-from-settings/**']),
        review: new ReviewConfig(
            minSeverity: SentinelConfigSeverity::Low,
            maxFindings: 25,
            categories: new CategoriesConfig(),
            tone: SentinelConfigTone::Constructive,
            language: 'en',
        ),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $settingsConfig->toArray(),
    ]);

    $branchConfig = new SentinelConfig(
        version: 1,
        paths: new PathsConfig(ignore: ['app/Secret/**']),
        review: new ReviewConfig(
            minSeverity: SentinelConfigSeverity::High,
            maxFindings: 5,
            categories: new CategoriesConfig(
                security: false,
                correctness: true,
                performance: false,
                maintainability: false,
                style: false,
                testing: false,
                documentation: false,
            ),
            tone: SentinelConfigTone::Direct,
            language: 'en',
        ),
    );

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository, $branchConfig->toArray(), 'main');

    expect($policy->getCommentSeverityThreshold()->value)->toBe('high')
        ->and($policy->getMaxInlineComments())->toBe(5)
        ->and($policy->enabledRules)->toBe(['correctness'])
        ->and($policy->ignoredPaths)->toContain('app/Secret/**')
        ->and($policy->ignoredPaths)->not->toContain('ignored-from-settings/**')
        ->and($policy->configSource)->toBe('branch')
        ->and($policy->configBranch)->toBe('main');
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
    expect($policy->getCommentSeverityThreshold()->value)->toBe('low')
        ->and($policy->getMaxInlineComments())->toBe(25)
        ->and($policy->tone->value)->toBe('constructive')
        ->and($policy->language)->toBe('en');
});

it('replaces enabled_rules with user categories', function (): void {
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
                testing: false,
            ),
        ),
    );

    RepositorySettings::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'sentinel_config' => $sentinelConfig->toArray(),
    ]);

    $resolver = new ReviewPolicyResolver();
    $policy = $resolver->resolve($repository);

    // User's categories replace defaults - only security and style enabled
    expect($policy->enabledRules)->toBe(['security', 'style']);
});

it('keeps empty focus array when not specified by user', function (): void {
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

    // Default focus is empty array (from config/reviews.php), not added from user config
    expect($policy->focus)->toBe([]);
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

    expect($policy->tone->value)->toBe('constructive');
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

    expect($policy->tone->value)->toBe('direct');
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

    expect($policy->tone->value)->toBe('educational');
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

    expect($policy->tone->value)->toBe('minimal');
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

    expect($policy->getCommentSeverityThreshold()->value)->toBe('critical');
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

    expect($policy->getCommentSeverityThreshold()->value)->toBe('medium');
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

    expect($policy->getCommentSeverityThreshold()->value)->toBe('info');
});
