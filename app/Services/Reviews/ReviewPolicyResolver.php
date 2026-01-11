<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\DataTransferObjects\SentinelConfig\ReviewConfig;
use App\Models\Repository;

final readonly class ReviewPolicyResolver
{
    /**
     * Resolve the review policy for a repository.
     *
     * @return array<string, mixed>
     */
    public function resolve(Repository $repository): array
    {
        $repository->loadMissing('settings');

        /** @var array<string, mixed> $result */
        $result = config('reviews.default_policy', []);

        $settings = $repository->settings;
        $sentinelConfig = $settings?->getConfigOrDefault();

        if ($sentinelConfig !== null) {
            $reviewConfig = $sentinelConfig->getReviewOrDefault();
            $result = $this->mergeReviewConfig($result, $reviewConfig);
        }

        return $result;
    }

    /**
     * Merge ReviewConfig into the policy array.
     *
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function mergeReviewConfig(array $policy, ReviewConfig $reviewConfig): array
    {
        // Map min_severity to severity_thresholds.comment
        $policy['severity_thresholds'] = array_merge(
            $policy['severity_thresholds'] ?? [],
            ['comment' => $reviewConfig->minSeverity->value]
        );

        // Map max_findings to comment_limits.max_inline_comments
        $policy['comment_limits'] = array_merge(
            $policy['comment_limits'] ?? [],
            ['max_inline_comments' => $reviewConfig->maxFindings]
        );

        // Add enabled categories to enabled_rules
        $enabledCategories = $reviewConfig->categories->getEnabled();

        if ($enabledCategories !== []) {
            $existingRules = $policy['enabled_rules'] ?? [];
            $policy['enabled_rules'] = array_unique(array_merge(
                is_array($existingRules) ? $existingRules : [],
                $enabledCategories
            ));
        }

        // Add tone, language, and focus for prompt customization
        $policy['tone'] = $reviewConfig->tone->value;
        $policy['language'] = $reviewConfig->language;

        if ($reviewConfig->focus !== []) {
            $policy['focus'] = $reviewConfig->focus;
        }

        return $policy;
    }
}
