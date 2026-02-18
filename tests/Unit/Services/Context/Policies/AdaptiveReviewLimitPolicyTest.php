<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\Context\Policies\AdaptiveReviewLimitPolicy;

it('returns static defaults when adaptive limits are disabled', function (): void {
    config([
        'reviews.adaptive_limits.enabled' => false,
        'reviews.file_context.max_files' => 10,
        'reviews.file_context.max_file_size' => 50000,
    ]);

    $repository = fakeRepositoryWithTier('sanctum');
    $limits = (new AdaptiveReviewLimitPolicy)->fileContextLimits($repository, [
        ['filename' => 'app/A.php', 'status' => 'modified', 'additions' => 10, 'deletions' => 4, 'changes' => 14, 'patch' => '+a'],
        ['filename' => 'app/B.php', 'status' => 'modified', 'additions' => 8, 'deletions' => 3, 'changes' => 11, 'patch' => '+b'],
    ]);

    expect($limits['max_files'])->toBe(10)
        ->and($limits['max_file_size'])->toBe(50000)
        ->and($limits['source'])->toBe('default')
        ->and($limits['adaptive'])->toBeFalse();
});

it('uses tier and pr-size bucket overrides when adaptive limits are enabled', function (): void {
    config([
        'reviews.adaptive_limits.enabled' => true,
        'reviews.adaptive_limits.pr_size_buckets' => [
            'small' => ['max_files_changed' => 1, 'max_lines_changed' => 20],
            'medium' => ['max_files_changed' => 4, 'max_lines_changed' => 200],
            'xlarge' => ['max_files_changed' => 1000000, 'max_lines_changed' => 1000000],
        ],
        'reviews.adaptive_limits.tiers.sanctum.medium.file_context.max_files' => 42,
        'reviews.adaptive_limits.tiers.sanctum.medium.file_context.max_file_size' => 123456,
    ]);

    $repository = fakeRepositoryWithTier('sanctum');
    $files = [
        ['filename' => 'app/A.php', 'status' => 'modified', 'additions' => 30, 'deletions' => 5, 'changes' => 35, 'patch' => '+a'],
        ['filename' => 'app/B.php', 'status' => 'modified', 'additions' => 20, 'deletions' => 5, 'changes' => 25, 'patch' => '+b'],
    ];

    $limits = (new AdaptiveReviewLimitPolicy)->fileContextLimits($repository, $files);

    expect($limits['max_files'])->toBe(42)
        ->and($limits['max_file_size'])->toBe(123456)
        ->and($limits['tier'])->toBe('sanctum')
        ->and($limits['pr_size_bucket'])->toBe('medium')
        ->and($limits['source'])->toBe('adaptive')
        ->and($limits['adaptive'])->toBeTrue();
});

it('falls back to static impact defaults and clamps unsafe override values', function (): void {
    config([
        'reviews.adaptive_limits.enabled' => true,
        'reviews.impact_analysis.max_symbols' => 25,
        'reviews.impact_analysis.max_files' => 20,
        'reviews.impact_analysis.max_file_size' => 50000,
        'reviews.impact_analysis.search_limit_per_symbol' => 50,
        'reviews.impact_analysis.min_relevance_score' => 0.3,
        'reviews.adaptive_limits.pr_size_buckets' => [
            'small' => ['max_files_changed' => 100, 'max_lines_changed' => 1000],
        ],
        'reviews.adaptive_limits.tiers.foundation.small.impact_analysis.max_symbols' => -10,
        'reviews.adaptive_limits.tiers.foundation.small.impact_analysis.max_files' => -20,
        'reviews.adaptive_limits.tiers.foundation.small.impact_analysis.min_relevance_score' => 9,
    ]);

    $repository = fakeRepositoryWithTier('foundation');
    $limits = (new AdaptiveReviewLimitPolicy)->impactAnalysisLimits($repository, [
        ['filename' => 'app/A.php', 'status' => 'modified', 'additions' => 1, 'deletions' => 0, 'changes' => 1, 'patch' => '+a'],
    ]);

    expect($limits['max_symbols'])->toBe(1)
        ->and($limits['max_files'])->toBe(1)
        ->and($limits['min_relevance_score'])->toBe(1.0)
        ->and($limits['source'])->toBe('adaptive')
        ->and($limits['adaptive'])->toBeTrue();
});

function fakeRepositoryWithTier(string $tier): Repository
{
    $plan = new Plan;
    $plan->tier = $tier;

    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    $repository = new Repository;
    $repository->setRelation('workspace', $workspace);

    return $repository;
}
