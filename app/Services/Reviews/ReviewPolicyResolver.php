<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\DataTransferObjects\SentinelConfig\AnnotationsConfig;
use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\DataTransferObjects\SentinelConfig\ProviderConfig;
use App\DataTransferObjects\SentinelConfig\ReviewConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Models\Repository;
use App\Services\Reviews\Contracts\ReviewPolicyResolverContract;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

final readonly class ReviewPolicyResolver implements ReviewPolicyResolverContract
{
    /**
     * Resolve the review policy for a repository.
     *
     * @param  array<string, mixed>|null  $sentinelConfigData
     */
    public function resolve(
        Repository $repository,
        ?array $sentinelConfigData = null,
        ?string $configBranch = null
    ): ReviewPolicy {
        $repository->loadMissing('settings');

        /** @var array<string, mixed> $result */
        $result = config('reviews.default_policy', []);

        $settings = $repository->settings;
        $configSource = 'default';
        $sentinelConfig = null;

        if (is_array($sentinelConfigData)) {
            /** @var array<string, mixed> $sentinelConfigData */
            $sentinelConfig = SentinelConfig::fromArray($sentinelConfigData);
            $configSource = 'branch';
        } else {
            $sentinelConfig = $settings?->getSentinelConfigDto();
            if ($sentinelConfig !== null) {
                $configSource = 'settings';
            }
        }

        if ($sentinelConfig !== null) {
            $reviewConfig = $sentinelConfig->getReviewOrDefault();
            $pathsConfig = $sentinelConfig->getPathsOrDefault();
            $annotationsConfig = $sentinelConfig->getAnnotationsOrDefault();
            $providerConfig = $sentinelConfig->getProviderOrDefault();
            $result = $this->mergeReviewConfig($result, $reviewConfig);
            $result = $this->mergePathsConfig($result, $pathsConfig);
            $result = $this->mergeAnnotationsConfig($result, $annotationsConfig);
            $result = $this->mergeProviderConfig($result, $providerConfig);
        }

        $result['config_source'] = $configSource;
        if ($configBranch !== null) {
            $result['config_branch'] = $configBranch;
        }

        return ReviewPolicy::fromArray($result);
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
     * Merge PathsConfig into the policy array.
     *
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    private function mergePathsConfig(array $policy, PathsConfig $pathsConfig): array
    {
        $existingIgnored = $policy['ignored_paths'] ?? [];
        $ignored = array_merge(
            is_array($existingIgnored) ? $existingIgnored : [],
            $pathsConfig->ignore
        );

        $policy['ignored_paths'] = array_values(array_unique($ignored));

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
