<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Policies;

use App\Enums\Billing\PlanTier;
use App\Models\Repository;

/**
 * Resolves indexing throughput limits by workspace tier and change volume.
 */
final readonly class AdaptiveIndexingLimitPolicy
{
    /**
     * Resolve indexing limits for a repository and file-volume sample.
     *
     * @return array{full_reindex_threshold: int, batch_size: int, tier: string, volume_bucket: string, source: string, adaptive: bool}
     */
    public function resolve(Repository $repository, int $fileCount): array
    {
        $tier = $this->resolveTier($repository);
        $bucket = $this->resolveVolumeBucket($fileCount);
        $defaults = [
            'full_reindex_threshold' => (int) config('reviews.indexing.full_reindex_threshold', 500),
            'batch_size' => (int) config('reviews.indexing.batch_size', 50),
        ];

        $adaptiveEnabled = (bool) config('reviews.adaptive_limits.indexing.enabled', false);

        if (! $adaptiveEnabled) {
            return [
                'full_reindex_threshold' => $this->clampInt($defaults['full_reindex_threshold'], 500, 1, 10_000),
                'batch_size' => $this->clampInt($defaults['batch_size'], 50, 1, 500),
                'tier' => $tier,
                'volume_bucket' => $bucket,
                'source' => 'default',
                'adaptive' => false,
            ];
        }

        $overrides = config(sprintf('reviews.adaptive_limits.indexing.tiers.%s.%s', $tier, $bucket));

        if (! is_array($overrides)) {
            $overrides = [];
        }

        $fullReindexThreshold = $this->clampInt($overrides['full_reindex_threshold'] ?? null, $defaults['full_reindex_threshold'], 1, 10_000);
        $batchSize = $this->clampInt($overrides['batch_size'] ?? null, $defaults['batch_size'], 1, 500);

        return [
            'full_reindex_threshold' => $fullReindexThreshold,
            'batch_size' => $batchSize,
            'tier' => $tier,
            'volume_bucket' => $bucket,
            'source' => $overrides === [] ? 'default' : 'adaptive',
            'adaptive' => true,
        ];
    }

    /**
     * Resolve workspace tier from repository context.
     */
    private function resolveTier(Repository $repository): string
    {
        $workspace = $repository->workspace;
        $tier = $workspace?->getCurrentTier() ?? PlanTier::Foundation->value;

        if (PlanTier::tryFrom($tier) instanceof PlanTier) {
            return $tier;
        }

        return PlanTier::Foundation->value;
    }

    /**
     * Resolve change-volume bucket from count of files in scope.
     */
    private function resolveVolumeBucket(int $fileCount): string
    {
        $buckets = config('reviews.adaptive_limits.indexing.change_volume_buckets', []);

        if (! is_array($buckets) || $buckets === []) {
            return 'small';
        }

        foreach ($buckets as $bucket => $constraints) {
            if (! is_array($constraints)) {
                continue;
            }

            $maxFiles = $this->clampInt($constraints['max_files'] ?? null, PHP_INT_MAX, 1, PHP_INT_MAX);

            if ($fileCount <= $maxFiles) {
                return (string) $bucket;
            }
        }

        return (string) array_key_last($buckets);
    }

    /**
     * Clamp an integer to a safe operational range.
     */
    private function clampInt(mixed $value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $resolved = (int) $value;

        return min($max, max($min, $resolved));
    }
}
