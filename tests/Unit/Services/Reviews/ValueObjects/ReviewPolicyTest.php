<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Enums\Reviews\AnnotationStyle;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Enums\SentinelConfig\SentinelConfigTone;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

it('can be constructed with all parameters', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: ['comment' => 'medium'],
        commentLimits: ['max_inline_comments' => 10],
        enabledRules: ['security', 'performance'],
        tone: SentinelConfigTone::Direct,
        language: 'en',
        focus: ['security'],
        ignoredPaths: ['vendor/*'],
        annotations: ['style' => 'review'],
        provider: ['preferred' => 'anthropic'],
        configSource: 'repository',
        configBranch: 'main',
    );

    expect($policy->severityThresholds)->toBe(['comment' => 'medium']);
    expect($policy->commentLimits)->toBe(['max_inline_comments' => 10]);
    expect($policy->enabledRules)->toBe(['security', 'performance']);
    expect($policy->tone)->toBe(SentinelConfigTone::Direct);
    expect($policy->language)->toBe('en');
    expect($policy->focus)->toBe(['security']);
    expect($policy->ignoredPaths)->toBe(['vendor/*']);
    expect($policy->annotations)->toBe(['style' => 'review']);
    expect($policy->provider)->toBe(['preferred' => 'anthropic']);
    expect($policy->configSource)->toBe('repository');
    expect($policy->configBranch)->toBe('main');
});

it('can be constructed without config branch', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: [],
        commentLimits: [],
        enabledRules: [],
        tone: SentinelConfigTone::Constructive,
        language: 'en',
        focus: [],
        ignoredPaths: [],
        annotations: [],
        provider: [],
        configSource: 'default',
    );

    expect($policy->configBranch)->toBeNull();
});

it('creates from array with all fields', function (): void {
    $policy = ReviewPolicy::fromArray([
        'severity_thresholds' => ['comment' => 'high'],
        'comment_limits' => ['max_inline_comments' => 20],
        'enabled_rules' => ['security', 'correctness'],
        'tone' => 'educational',
        'language' => 'es',
        'focus' => ['performance', 'security'],
        'ignored_paths' => ['tests/*', 'vendor/*'],
        'annotations' => ['style' => 'comment', 'grouped' => true],
        'provider' => ['preferred' => 'openai', 'fallback' => false],
        'config_source' => 'workspace',
        'config_branch' => 'develop',
    ]);

    expect($policy->tone)->toBe(SentinelConfigTone::Educational);
    expect($policy->language)->toBe('es');
    expect($policy->enabledRules)->toBe(['security', 'correctness']);
    expect($policy->ignoredPaths)->toBe(['tests/*', 'vendor/*']);
    expect($policy->configSource)->toBe('workspace');
    expect($policy->configBranch)->toBe('develop');
});

it('creates from array with defaults for missing fields', function (): void {
    $policy = ReviewPolicy::fromArray([]);

    expect($policy->severityThresholds)->toBe([]);
    expect($policy->commentLimits)->toBe([]);
    expect($policy->enabledRules)->toBe([]);
    expect($policy->tone)->toBe(SentinelConfigTone::Constructive);
    expect($policy->language)->toBe('en');
    expect($policy->focus)->toBe([]);
    expect($policy->ignoredPaths)->toBe([]);
    expect($policy->annotations)->toBe([]);
    expect($policy->provider)->toBe([]);
    expect($policy->configSource)->toBe('default');
    expect($policy->configBranch)->toBeNull();
});

it('handles invalid tone by using default', function (): void {
    $policy = ReviewPolicy::fromArray(['tone' => 'invalid_tone']);

    expect($policy->tone)->toBe(SentinelConfigTone::Constructive);
});

it('filters non-string keys from arrays', function (): void {
    $policy = ReviewPolicy::fromArray([
        'severity_thresholds' => ['comment' => 'high', 0 => 'invalid'],
        'enabled_rules' => ['security', 123, 'performance'],
    ]);

    expect($policy->severityThresholds)->toBe(['comment' => 'high']);
    expect($policy->enabledRules)->toBe(['security', 'performance']);
});

it('gets comment severity threshold', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: ['comment' => 'high'],
        commentLimits: [],
        enabledRules: [],
        tone: SentinelConfigTone::Constructive,
        language: 'en',
        focus: [],
        ignoredPaths: [],
        annotations: [],
        provider: [],
        configSource: 'default',
    );

    expect($policy->getCommentSeverityThreshold())->toBe(SentinelConfigSeverity::High);
});

it('returns default comment severity threshold when not set', function (): void {
    $policy = ReviewPolicy::fromArray([]);

    expect($policy->getCommentSeverityThreshold())->toBe(SentinelConfigSeverity::Info);
});

it('returns default comment severity threshold for invalid value', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: ['comment' => 'invalid'],
        commentLimits: [],
        enabledRules: [],
        tone: SentinelConfigTone::Constructive,
        language: 'en',
        focus: [],
        ignoredPaths: [],
        annotations: [],
        provider: [],
        configSource: 'default',
    );

    expect($policy->getCommentSeverityThreshold())->toBe(SentinelConfigSeverity::Info);
});

it('gets max inline comments', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: [],
        commentLimits: ['max_inline_comments' => 25],
        enabledRules: [],
        tone: SentinelConfigTone::Constructive,
        language: 'en',
        focus: [],
        ignoredPaths: [],
        annotations: [],
        provider: [],
        configSource: 'default',
    );

    expect($policy->getMaxInlineComments())->toBe(25);
});

it('returns default max inline comments when not set', function (): void {
    $policy = ReviewPolicy::fromArray([]);

    expect($policy->getMaxInlineComments())->toBe(15);
});

it('gets annotation style', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: [],
        commentLimits: [],
        enabledRules: [],
        tone: SentinelConfigTone::Constructive,
        language: 'en',
        focus: [],
        ignoredPaths: [],
        annotations: ['style' => 'comment'],
        provider: [],
        configSource: 'default',
    );

    expect($policy->getAnnotationStyle())->toBe(AnnotationStyle::Comment);
});

it('returns default annotation style when not set', function (): void {
    $policy = ReviewPolicy::fromArray([]);

    expect($policy->getAnnotationStyle())->toBe(AnnotationStyle::Review);
});

it('gets annotation post threshold', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: [],
        commentLimits: [],
        enabledRules: [],
        tone: SentinelConfigTone::Constructive,
        language: 'en',
        focus: [],
        ignoredPaths: [],
        annotations: ['post_threshold' => 'high'],
        provider: [],
        configSource: 'default',
    );

    expect($policy->getAnnotationPostThreshold())->toBe(SentinelConfigSeverity::High);
});

it('returns default annotation post threshold when not set', function (): void {
    $policy = ReviewPolicy::fromArray([]);

    expect($policy->getAnnotationPostThreshold())->toBe(SentinelConfigSeverity::Medium);
});

it('checks if annotations should be grouped', function (): void {
    $grouped = ReviewPolicy::fromArray(['annotations' => ['grouped' => true]]);
    $notGrouped = ReviewPolicy::fromArray(['annotations' => ['grouped' => false]]);
    $notSet = ReviewPolicy::fromArray([]);

    expect($grouped->shouldGroupAnnotations())->toBeTrue();
    expect($notGrouped->shouldGroupAnnotations())->toBeFalse();
    expect($notSet->shouldGroupAnnotations())->toBeFalse();
});

it('checks if suggestions should be included', function (): void {
    $included = ReviewPolicy::fromArray(['annotations' => ['include_suggestions' => true]]);
    $excluded = ReviewPolicy::fromArray(['annotations' => ['include_suggestions' => false]]);
    $notSet = ReviewPolicy::fromArray([]);

    expect($included->shouldIncludeSuggestions())->toBeTrue();
    expect($excluded->shouldIncludeSuggestions())->toBeFalse();
    expect($notSet->shouldIncludeSuggestions())->toBeTrue();
});

it('gets preferred provider', function (): void {
    $policy = ReviewPolicy::fromArray(['provider' => ['preferred' => 'anthropic']]);

    expect($policy->getPreferredProvider())->toBe(AiProvider::Anthropic);
});

it('returns null preferred provider when not set', function (): void {
    $policy = ReviewPolicy::fromArray([]);

    expect($policy->getPreferredProvider())->toBeNull();
});

it('returns null preferred provider for invalid value', function (): void {
    $policy = ReviewPolicy::fromArray(['provider' => ['preferred' => 'invalid_provider']]);

    expect($policy->getPreferredProvider())->toBeNull();
});

it('checks if fallback is enabled', function (): void {
    $enabled = ReviewPolicy::fromArray(['provider' => ['fallback' => true]]);
    $disabled = ReviewPolicy::fromArray(['provider' => ['fallback' => false]]);
    $notSet = ReviewPolicy::fromArray([]);

    expect($enabled->isFallbackEnabled())->toBeTrue();
    expect($disabled->isFallbackEnabled())->toBeFalse();
    expect($notSet->isFallbackEnabled())->toBeTrue();
});

it('checks if path should be ignored', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: [],
        commentLimits: [],
        enabledRules: [],
        tone: SentinelConfigTone::Constructive,
        language: 'en',
        focus: [],
        ignoredPaths: ['vendor/*', 'tests/*', '*.lock'],
        annotations: [],
        provider: [],
        configSource: 'default',
    );

    expect($policy->shouldIgnorePath('vendor/autoload.php'))->toBeTrue();
    expect($policy->shouldIgnorePath('tests/Unit/Test.php'))->toBeTrue();
    expect($policy->shouldIgnorePath('composer.lock'))->toBeTrue();
    expect($policy->shouldIgnorePath('src/App.php'))->toBeFalse();
});

it('converts to array with config branch', function (): void {
    $policy = new ReviewPolicy(
        severityThresholds: ['comment' => 'medium'],
        commentLimits: ['max_inline_comments' => 15],
        enabledRules: ['security'],
        tone: SentinelConfigTone::Direct,
        language: 'en',
        focus: ['security'],
        ignoredPaths: ['vendor/*'],
        annotations: ['style' => 'review'],
        provider: ['preferred' => 'anthropic'],
        configSource: 'repository',
        configBranch: 'main',
    );

    $array = $policy->toArray();

    expect($array['severity_thresholds'])->toBe(['comment' => 'medium']);
    expect($array['tone'])->toBe('direct');
    expect($array['config_source'])->toBe('repository');
    expect($array['config_branch'])->toBe('main');
});

it('converts to array without config branch', function (): void {
    $policy = ReviewPolicy::fromArray([]);

    $array = $policy->toArray();

    expect($array)->not->toHaveKey('config_branch');
    expect($array['config_source'])->toBe('default');
});
