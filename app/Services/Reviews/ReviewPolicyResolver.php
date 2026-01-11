<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\DataTransferObjects\SentinelConfig\AnnotationsConfig;
use App\DataTransferObjects\SentinelConfig\ProviderConfig;
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
            $annotationsConfig = $sentinelConfig->getAnnotationsOrDefault();
            $providerConfig = $sentinelConfig->getProviderOrDefault();
            $result = $this->mergeReviewConfig($result, $reviewConfig);
            $result = $this->mergeAnnotationsConfig($result, $annotationsConfig);
            $result = $this->mergeProviderConfig($result, $providerConfig);
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
        $existingThresholds = $policy['severity_thresholds'] ?? [];
        $policy['severity_thresholds'] = array_merge(
            is_array($existingThresholds) ? $existingThresholds : [],
            ['comment' => $reviewConfig->minSeverity->value]
        );

        // Map max_findings to comment_limits.max_inline_comments
        $existingLimits = $policy['comment_limits'] ?? [];
        $policy['comment_limits'] = array_merge(
            is_array($existingLimits) ? $existingLimits : [],
            ['max_inline_comments' => $reviewConfig->maxFindings]
        );

        // Replace enabled_rules with user's enabled categories
        // This allows users to disable default categories (e.g., setting performance: false)
        $policy['enabled_rules'] = $reviewConfig->categories->getEnabled();

        // Add tone, language, and focus for prompt customization
        $policy['tone'] = $reviewConfig->tone->value;
        $policy['language'] = $reviewConfig->language;

        if ($reviewConfig->focus !== []) {
            $policy['focus'] = $reviewConfig->focus;
        }

        return $policy;
    }

    /**
     * Merge AnnotationsConfig into the policy array.
     *
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function mergeAnnotationsConfig(array $policy, AnnotationsConfig $annotationsConfig): array
    {
        $policy['annotations'] = [
            'style' => $annotationsConfig->style->value,
            'post_threshold' => $annotationsConfig->postThreshold->value,
            'grouped' => $annotationsConfig->grouped,
            'include_suggestions' => $annotationsConfig->includeSuggestions,
        ];

        return $policy;
    }

    /**
     * Merge ProviderConfig into the policy array.
     *
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function mergeProviderConfig(array $policy, ProviderConfig $providerConfig): array
    {
        $policy['provider'] = [
            'preferred' => $providerConfig->preferred?->value,
            'model' => $providerConfig->model,
            'fallback' => $providerConfig->fallback,
        ];

        return $policy;
    }
}
