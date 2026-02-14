<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Checkers;

use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Enums\Billing\PlanFeature;
use App\Models\Repository;
use App\Services\Plans\PlanLimitEnforcer;

final readonly class RepositorySentinelConfigGuidelineChecker
{
    /**
     * Create a new guideline checker.
     */
    public function __construct(private PlanLimitEnforcer $planLimitEnforcer) {}

    /**
     * Apply plan-based guideline constraints to sentinel config.
     *
     * @return array{config: SentinelConfig, error: ?string}
     */
    public function apply(Repository $repository, SentinelConfig $config): array
    {
        $configError = null;
        $workspace = $repository->workspace;

        if ($workspace !== null && $config->guidelines !== []) {
            $featureCheck = $this->planLimitEnforcer->ensureFeatureEnabled(
                $workspace,
                PlanFeature::CustomGuidelines,
                'Custom guidelines are not available on your current plan.'
            );

            if (! $featureCheck->allowed) {
                $configError = $featureCheck->message;
                $config = new SentinelConfig(
                    version: $config->version,
                    triggers: $config->triggers,
                    paths: $config->paths,
                    review: $config->review,
                    guidelines: [],
                    annotations: $config->annotations,
                    provider: $config->provider,
                );
            }
        }

        return [
            'config' => $config,
            'error' => $configError,
        ];
    }
}
