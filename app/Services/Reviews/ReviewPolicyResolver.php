<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Models\Repository;
use App\Services\Reviews\Contracts\ReviewPolicyResolverContract;
use App\Services\Reviews\Support\SentinelConfigPolicyMerger;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

final readonly class ReviewPolicyResolver implements ReviewPolicyResolverContract
{
    /**
     * Create a new ReviewPolicyResolver instance.
     */
    public function __construct(
        private SentinelConfigPolicyMerger $configMerger = new SentinelConfigPolicyMerger,
    ) {}

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
            $result = $this->configMerger->mergeReviewConfig($result, $reviewConfig);
            $result = $this->configMerger->mergePathsConfig($result, $pathsConfig);
            $result = $this->configMerger->mergeAnnotationsConfig($result, $annotationsConfig);
            $result = $this->configMerger->mergeProviderConfig($result, $providerConfig);
        }

        $result['config_source'] = $configSource;
        if ($configBranch !== null) {
            $result['config_branch'] = $configBranch;
        }

        return ReviewPolicy::fromArray($result);
    }
}
