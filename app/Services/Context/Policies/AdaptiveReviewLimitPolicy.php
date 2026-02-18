<?php

declare(strict_types=1);

namespace App\Services\Context\Policies;

use App\Enums\Billing\PlanTier;
use App\Models\Repository;

/**
 * Resolves review-context limits using workspace tier and PR size bucket.
 */
final readonly class AdaptiveReviewLimitPolicy
{
    /**
     * Resolve file-content collection limits.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{max_files: int, max_file_size: int, tier: string, pr_size_bucket: string, source: string, adaptive: bool}
     */
    public function fileContextLimits(Repository $repository, array $files): array
    {
        $tier = $this->resolveTier($repository);
        $bucket = $this->resolvePrSizeBucket($files);
        $defaults = [
            'max_files' => (int) config('reviews.file_context.max_files', 10),
            'max_file_size' => (int) config('reviews.file_context.max_file_size', 50_000),
        ];

        $resolved = $this->resolveSection($tier, $bucket, 'file_context', $defaults);

        return [
            'max_files' => $this->clampInt($resolved['values']['max_files'] ?? null, $defaults['max_files'], 1, 120),
            'max_file_size' => $this->clampInt($resolved['values']['max_file_size'] ?? null, $defaults['max_file_size'], 1, 512_000),
            'tier' => $tier,
            'pr_size_bucket' => $bucket,
            'source' => $resolved['source'],
            'adaptive' => $resolved['adaptive'],
        ];
    }

    /**
     * Resolve semantic analysis limits.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{max_files: int, max_file_size: int, tier: string, pr_size_bucket: string, source: string, adaptive: bool}
     */
    public function semanticLimits(Repository $repository, array $files): array
    {
        $tier = $this->resolveTier($repository);
        $bucket = $this->resolvePrSizeBucket($files);
        $defaults = [
            'max_files' => (int) config('reviews.semantic.max_files', 15),
            'max_file_size' => (int) config('reviews.semantic.max_file_size', 100_000),
        ];

        $resolved = $this->resolveSection($tier, $bucket, 'semantic', $defaults);

        return [
            'max_files' => $this->clampInt($resolved['values']['max_files'] ?? null, $defaults['max_files'], 1, 150),
            'max_file_size' => $this->clampInt($resolved['values']['max_file_size'] ?? null, $defaults['max_file_size'], 1, 1_000_000),
            'tier' => $tier,
            'pr_size_bucket' => $bucket,
            'source' => $resolved['source'],
            'adaptive' => $resolved['adaptive'],
        ];
    }

    /**
     * Resolve impact-analysis limits.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{max_symbols: int, max_files: int, max_file_size: int, search_limit_per_symbol: int, min_relevance_score: float, tier: string, pr_size_bucket: string, source: string, adaptive: bool}
     */
    public function impactAnalysisLimits(Repository $repository, array $files): array
    {
        $tier = $this->resolveTier($repository);
        $bucket = $this->resolvePrSizeBucket($files);
        $configuredMinScore = config('reviews.impact_analysis.min_relevance_score', 0.3);
        $defaults = [
            'max_symbols' => (int) config('reviews.impact_analysis.max_symbols', 25),
            'max_files' => (int) config('reviews.impact_analysis.max_files', 20),
            'max_file_size' => (int) config('reviews.impact_analysis.max_file_size', 50_000),
            'search_limit_per_symbol' => (int) config('reviews.impact_analysis.search_limit_per_symbol', 50),
            'min_relevance_score' => is_numeric($configuredMinScore) ? (float) $configuredMinScore : 0.3,
        ];

        $resolved = $this->resolveSection($tier, $bucket, 'impact_analysis', $defaults);

        return [
            'max_symbols' => $this->clampInt($resolved['values']['max_symbols'] ?? null, $defaults['max_symbols'], 1, 300),
            'max_files' => $this->clampInt($resolved['values']['max_files'] ?? null, $defaults['max_files'], 1, 150),
            'max_file_size' => $this->clampInt($resolved['values']['max_file_size'] ?? null, $defaults['max_file_size'], 1, 1_000_000),
            'search_limit_per_symbol' => $this->clampInt($resolved['values']['search_limit_per_symbol'] ?? null, $defaults['search_limit_per_symbol'], 1, 500),
            'min_relevance_score' => $this->clampFloat($resolved['values']['min_relevance_score'] ?? null, $defaults['min_relevance_score'], 0.0, 1.0),
            'tier' => $tier,
            'pr_size_bucket' => $bucket,
            'source' => $resolved['source'],
            'adaptive' => $resolved['adaptive'],
        ];
    }

    /**
     * Resolve section-level limits with static fallback.
     *
     * @param  array<string, int|float>  $defaults
     * @return array{values: array<string, int|float>, source: string, adaptive: bool}
     */
    private function resolveSection(string $tier, string $bucket, string $section, array $defaults): array
    {
        $adaptiveEnabled = (bool) config('reviews.adaptive_limits.enabled', false);

        if (! $adaptiveEnabled) {
            return ['values' => $defaults, 'source' => 'default', 'adaptive' => false];
        }

        $overrides = config(sprintf('reviews.adaptive_limits.tiers.%s.%s.%s', $tier, $bucket, $section));

        if (! is_array($overrides) || $overrides === []) {
            return ['values' => $defaults, 'source' => 'default', 'adaptive' => true];
        }

        /** @var array<string, int|float> $overrides */
        $values = array_replace($defaults, $overrides);

        return ['values' => $values, 'source' => 'adaptive', 'adaptive' => true];
    }

    /**
     * Resolve workspace tier from the repository.
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
     * Resolve PR size bucket from changed-file count and changed lines.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     */
    private function resolvePrSizeBucket(array $files): string
    {
        $filesChanged = count($files);
        $changedLines = array_sum(array_map(
            static fn (array $file): int => max($file['changes'], $file['additions'] + $file['deletions']),
            $files
        ));

        $buckets = config('reviews.adaptive_limits.pr_size_buckets', []);

        if (! is_array($buckets) || $buckets === []) {
            return 'small';
        }

        foreach ($buckets as $bucket => $constraints) {
            if (! is_array($constraints)) {
                continue;
            }

            $maxFiles = $this->clampInt($constraints['max_files_changed'] ?? null, PHP_INT_MAX, 1, PHP_INT_MAX);
            $maxLines = $this->clampInt($constraints['max_lines_changed'] ?? null, PHP_INT_MAX, 1, PHP_INT_MAX);

            if ($filesChanged <= $maxFiles && $changedLines <= $maxLines) {
                return (string) $bucket;
            }
        }

        return (string) array_key_last($buckets);
    }

    /**
     * Clamp an integer value to a safe range.
     */
    private function clampInt(mixed $value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $resolved = (int) $value;

        return min($max, max($min, $resolved));
    }

    /**
     * Clamp a float value to a safe range.
     */
    private function clampFloat(mixed $value, float $default, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $resolved = (float) $value;

        return min($max, max($min, $resolved));
    }
}
