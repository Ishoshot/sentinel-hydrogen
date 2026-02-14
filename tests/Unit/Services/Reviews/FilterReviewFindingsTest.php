<?php

declare(strict_types=1);

use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Enums\SentinelConfig\SentinelConfigTone;
use App\Services\Reviews\FilterReviewFindings;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

function makeFinding(
    SentinelConfigSeverity $severity = SentinelConfigSeverity::Medium,
    FindingCategory $category = FindingCategory::Security,
    float $confidence = 0.9,
    ?string $filePath = 'src/App.php',
    ?int $lineStart = 10,
    string $title = 'Test finding',
): ReviewFinding {
    return new ReviewFinding(
        severity: $severity,
        category: $category,
        title: $title,
        description: 'Test description',
        impact: 'Test impact',
        confidence: $confidence,
        filePath: $filePath,
        lineStart: $lineStart,
    );
}

function makePolicy(
    string $commentThreshold = 'info',
    int $maxInlineComments = 25,
    array $enabledRules = [],
    array $ignoredPaths = [],
): ReviewPolicy {
    return new ReviewPolicy(
        severityThresholds: ['comment' => $commentThreshold],
        commentLimits: ['max_inline_comments' => $maxInlineComments],
        enabledRules: $enabledRules,
        tone: SentinelConfigTone::Constructive,
        language: 'en',
        focus: [],
        ignoredPaths: $ignoredPaths,
        annotations: [],
        provider: [],
        configSource: 'default',
    );
}

it('returns empty array when no findings provided', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy();

    expect($filter->handle([], $policy))->toBe([]);
});

it('filters findings below severity threshold', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy(commentThreshold: 'high');

    $findings = [
        makeFinding(severity: SentinelConfigSeverity::Critical, title: 'Critical'),
        makeFinding(severity: SentinelConfigSeverity::High, title: 'High'),
        makeFinding(severity: SentinelConfigSeverity::Medium, title: 'Medium'),
        makeFinding(severity: SentinelConfigSeverity::Low, title: 'Low'),
        makeFinding(severity: SentinelConfigSeverity::Info, title: 'Info'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toHaveCount(2)
        ->and($result[0]->title)->toBe('Critical')
        ->and($result[1]->title)->toBe('High');
});

it('filters findings below confidence threshold', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy();

    $findings = [
        makeFinding(confidence: 0.9, title: 'High confidence'),
        makeFinding(confidence: 0.5, title: 'Low confidence'),
        makeFinding(confidence: 0.7, title: 'Threshold confidence'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toHaveCount(2)
        ->and($result[0]->title)->toBe('High confidence')
        ->and($result[1]->title)->toBe('Threshold confidence');
});

it('filters findings by enabled rules', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy(enabledRules: ['security', 'performance']);

    $findings = [
        makeFinding(category: FindingCategory::Security, title: 'Security'),
        makeFinding(category: FindingCategory::Performance, title: 'Performance'),
        makeFinding(category: FindingCategory::Maintainability, title: 'Maintainability'),
        makeFinding(category: FindingCategory::Style, title: 'Style'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toHaveCount(2)
        ->and($result[0]->title)->toBe('Performance')
        ->and($result[1]->title)->toBe('Security');
});

it('passes all categories when enabled rules is empty', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy(enabledRules: []);

    $findings = [
        makeFinding(category: FindingCategory::Security, title: 'Security'),
        makeFinding(category: FindingCategory::Style, title: 'Style'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toHaveCount(2);
});

it('filters findings with ignored paths', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy(ignoredPaths: ['vendor/**', 'tests/**']);

    $findings = [
        makeFinding(filePath: 'src/App.php', title: 'Source'),
        makeFinding(filePath: 'vendor/autoload.php', title: 'Vendor'),
        makeFinding(filePath: 'tests/Unit/FooTest.php', title: 'Test'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toHaveCount(1)
        ->and($result[0]->title)->toBe('Source');
});

it('does not filter findings without file path against ignored paths', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy(ignoredPaths: ['vendor/**']);

    $findings = [
        makeFinding(filePath: null, title: 'No path'),
        makeFinding(filePath: 'vendor/foo.php', title: 'Vendor'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toHaveCount(1)
        ->and($result[0]->title)->toBe('No path');
});

it('sorts findings by severity descending then confidence descending', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy();

    $findings = [
        makeFinding(severity: SentinelConfigSeverity::Low, confidence: 0.9, title: 'Low/High'),
        makeFinding(severity: SentinelConfigSeverity::Critical, confidence: 0.8, title: 'Critical/Med'),
        makeFinding(severity: SentinelConfigSeverity::Critical, confidence: 0.95, title: 'Critical/High'),
        makeFinding(severity: SentinelConfigSeverity::Medium, confidence: 0.9, title: 'Medium/High'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result[0]->title)->toBe('Critical/High')
        ->and($result[1]->title)->toBe('Critical/Med')
        ->and($result[2]->title)->toBe('Medium/High')
        ->and($result[3]->title)->toBe('Low/High');
});

it('limits results to max inline comments', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy(maxInlineComments: 2);

    $findings = [
        makeFinding(title: 'First'),
        makeFinding(title: 'Second'),
        makeFinding(title: 'Third'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toHaveCount(2);
});

it('returns empty when max findings is zero', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy(maxInlineComments: 0);

    $findings = [
        makeFinding(title: 'Should not appear'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toBe([]);
});

it('handles glob pattern with double star for deep paths', function (): void {
    $filter = new FilterReviewFindings;
    $policy = makePolicy(ignoredPaths: ['src/generated/**']);

    $findings = [
        makeFinding(filePath: 'src/generated/models/User.php', title: 'Deep path'),
        makeFinding(filePath: 'src/app/User.php', title: 'Normal path'),
    ];

    $result = $filter->handle($findings, $policy);

    expect($result)->toHaveCount(1)
        ->and($result[0]->title)->toBe('Normal path');
});
