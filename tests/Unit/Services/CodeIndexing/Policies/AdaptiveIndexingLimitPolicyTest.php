<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Repository;
use App\Models\Workspace;
use App\Services\CodeIndexing\Policies\AdaptiveIndexingLimitPolicy;

it('returns static indexing defaults when adaptive indexing limits are disabled', function (): void {
    config([
        'reviews.adaptive_limits.indexing.enabled' => false,
        'reviews.indexing.full_reindex_threshold' => 500,
        'reviews.indexing.batch_size' => 50,
    ]);

    $repository = fakeRepositoryWithTierForIndexing('orchestrate');
    $limits = (new AdaptiveIndexingLimitPolicy)->resolve($repository, 120);

    expect($limits['full_reindex_threshold'])->toBe(500)
        ->and($limits['batch_size'])->toBe(50)
        ->and($limits['source'])->toBe('default')
        ->and($limits['adaptive'])->toBeFalse();
});

it('uses tier and volume bucket overrides when adaptive indexing limits are enabled', function (): void {
    config([
        'reviews.adaptive_limits.indexing.enabled' => true,
        'reviews.adaptive_limits.indexing.change_volume_buckets' => [
            'small' => ['max_files' => 20],
            'medium' => ['max_files' => 80],
            'xlarge' => ['max_files' => 1000000],
        ],
        'reviews.adaptive_limits.indexing.tiers.illuminate.medium.full_reindex_threshold' => 333,
        'reviews.adaptive_limits.indexing.tiers.illuminate.medium.batch_size' => 27,
    ]);

    $repository = fakeRepositoryWithTierForIndexing('illuminate');
    $limits = (new AdaptiveIndexingLimitPolicy)->resolve($repository, 50);

    expect($limits['full_reindex_threshold'])->toBe(333)
        ->and($limits['batch_size'])->toBe(27)
        ->and($limits['tier'])->toBe('illuminate')
        ->and($limits['volume_bucket'])->toBe('medium')
        ->and($limits['source'])->toBe('adaptive')
        ->and($limits['adaptive'])->toBeTrue();
});

it('clamps unsafe adaptive indexing values', function (): void {
    config([
        'reviews.adaptive_limits.indexing.enabled' => true,
        'reviews.adaptive_limits.indexing.change_volume_buckets' => [
            'small' => ['max_files' => 1000],
        ],
        'reviews.adaptive_limits.indexing.tiers.foundation.small.full_reindex_threshold' => -100,
        'reviews.adaptive_limits.indexing.tiers.foundation.small.batch_size' => 99999,
    ]);

    $repository = fakeRepositoryWithTierForIndexing('foundation');
    $limits = (new AdaptiveIndexingLimitPolicy)->resolve($repository, 10);

    expect($limits['full_reindex_threshold'])->toBe(1)
        ->and($limits['batch_size'])->toBe(500)
        ->and($limits['source'])->toBe('adaptive');
});

function fakeRepositoryWithTierForIndexing(string $tier): Repository
{
    $plan = new Plan;
    $plan->tier = $tier;

    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    $repository = new Repository;
    $repository->setRelation('workspace', $workspace);

    return $repository;
}
