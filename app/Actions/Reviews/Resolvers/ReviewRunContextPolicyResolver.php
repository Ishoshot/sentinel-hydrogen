<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Resolvers;

use App\Actions\Reviews\Support\ReviewRunContextResolution;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Contracts\ContextEngineContract;
use App\Services\Reviews\Contracts\ReviewPolicyResolverContract;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

final readonly class ReviewRunContextPolicyResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(
        private ReviewPolicyResolverContract $policyResolver,
        private ContextEngineContract $contextEngine,
    ) {}

    /**
     * Resolve the default policy snapshot.
     */
    public function defaultPolicy(Repository $repository): ReviewPolicy
    {
        return $this->policyResolver->resolve($repository);
    }

    /**
     * Resolve context and effective policy snapshot for a run.
     */
    public function resolve(Repository $repository, Run $run, ReviewPolicy $policySnapshot): ReviewRunContextResolution
    {
        $contextBag = $this->contextEngine->build([
            'repository' => $repository,
            'run' => $run,
        ]);

        $sentinelConfigData = $contextBag->metadata['sentinel_config'] ?? null;
        $configBranch = $contextBag->metadata['config_from_branch'] ?? null;

        /** @var array<string, mixed>|null $branchConfig */
        $branchConfig = is_array($sentinelConfigData) ? $sentinelConfigData : null;

        $allowedBranches = array_values(array_unique(array_filter([
            $contextBag->pullRequest['base_branch'] ?? null,
            $repository->default_branch,
        ])));

        if ($branchConfig !== null && is_string($configBranch) && in_array($configBranch, $allowedBranches, true)) {
            $policySnapshot = $this->policyResolver->resolve($repository, $branchConfig, $configBranch);
        }

        return new ReviewRunContextResolution($contextBag, $policySnapshot);
    }
}
