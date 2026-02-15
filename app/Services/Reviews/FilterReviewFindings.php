<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Services\Reviews\Strategies\FindingPathMatchStrategy;
use App\Services\Reviews\Strategies\FindingSortStrategy;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

final readonly class FilterReviewFindings
{
    private const float DEFAULT_CONFIDENCE_THRESHOLD = 0.7;

    /**
     * Create a new FilterReviewFindings instance.
     */
    public function __construct(
        private FindingPathMatchStrategy $pathMatcher = new FindingPathMatchStrategy,
        private FindingSortStrategy $sorter = new FindingSortStrategy,
    ) {}

    /**
     * @param  array<int, ReviewFinding>  $findings
     * @return array<int, ReviewFinding>
     */
    public function handle(array $findings, ReviewPolicy $policy): array
    {
        if ($findings === []) {
            return [];
        }

        $minSeverity = $policy->getCommentSeverityThreshold();
        $maxFindings = $policy->getMaxInlineComments();
        $confidenceThreshold = $this->resolveConfidenceThreshold();
        $enabledRules = $this->resolveEnabledRules($policy);
        $ignoredPaths = $policy->ignoredPaths;

        $filtered = array_filter($findings, function (ReviewFinding $finding) use ($minSeverity, $confidenceThreshold, $enabledRules, $ignoredPaths): bool {
            if ($enabledRules !== null && ! in_array($finding->category->value, $enabledRules, true)) {
                return false;
            }

            if ($finding->filePath !== null && $this->pathMatcher->matchesAny($finding->filePath, $ignoredPaths)) {
                return false;
            }

            if ($finding->severity->priority() < $minSeverity->priority()) {
                return false;
            }

            return $finding->confidence >= $confidenceThreshold;
        });

        $filtered = $this->sorter->sort(array_values($filtered));

        if ($maxFindings < 1) {
            return [];
        }

        return array_slice($filtered, 0, $maxFindings);
    }

    /**
     * @return array<int, string>|null
     */
    private function resolveEnabledRules(ReviewPolicy $policy): ?array
    {
        return $policy->enabledRules === [] ? null : array_values($policy->enabledRules);
    }

    /**
     * Resolve the configured confidence threshold fallback.
     */
    private function resolveConfidenceThreshold(): float
    {
        $defaultValue = config('reviews.default_policy.confidence_thresholds.finding', self::DEFAULT_CONFIDENCE_THRESHOLD);

        return is_numeric($defaultValue) ? (float) $defaultValue : self::DEFAULT_CONFIDENCE_THRESHOLD;
    }
}
