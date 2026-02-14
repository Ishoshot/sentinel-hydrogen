<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

final class FilterReviewFindings
{
    private const float DEFAULT_CONFIDENCE_THRESHOLD = 0.7;

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

            if ($finding->filePath !== null && $this->matchesAnyPattern($finding->filePath, $ignoredPaths)) {
                return false;
            }

            if ($finding->severity->priority() < $minSeverity->priority()) {
                return false;
            }

            return $finding->confidence >= $confidenceThreshold;
        });

        $filtered = array_values($filtered);

        usort($filtered, fn (ReviewFinding $a, ReviewFinding $b): int => ($b->severity->priority() <=> $a->severity->priority())
            ?: ($b->confidence <=> $a->confidence)
            ?: $this->compareNullableStrings($a->filePath, $b->filePath)
            ?: $this->compareNullableInts($a->lineStart, $b->lineStart)
            ?: strcmp($a->title, $b->title)
        );

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

    /**
     * Compare nullable strings while sorting null values last.
     */
    private function compareNullableStrings(?string $left, ?string $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return strcmp($left, $right);
    }

    /**
     * Compare nullable integers while sorting null values last.
     */
    private function compareNullableInts(?int $left, ?int $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return $left <=> $right;
    }

    /**
     * @param  array<string>  $patterns
     */
    private function matchesAnyPattern(string $path, array $patterns): bool
    {
        if ($patterns === []) {
            return false;
        }

        return array_any($patterns, fn (string $pattern): bool => $this->matchesGlob($path, $pattern));
    }

    /**
     * Evaluate whether a path matches a single glob expression.
     */
    private function matchesGlob(string $path, string $pattern): bool
    {
        if ($path === $pattern) {
            return true;
        }

        return preg_match($this->globToRegex($pattern), $path) === 1;
    }

    /**
     * Convert a glob expression into a regular expression.
     */
    private function globToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $escaped = str_replace('\\*\\*', '.*', $escaped);
        $escaped = str_replace('\\*', '[^\/]*', $escaped);
        $escaped = str_replace('\\?', '[^\/]', $escaped);

        return '/^'.$escaped.'$/';
    }
}
