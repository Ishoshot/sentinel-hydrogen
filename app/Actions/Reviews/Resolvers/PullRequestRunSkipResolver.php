<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Resolvers;

use App\Actions\Reviews\Support\PullRequestRunSkipResolution;
use App\Models\Repository;
use App\Services\Plans\PlanLimitEnforcer;

final readonly class PullRequestRunSkipResolver
{
    /**
     * Create a new pull request run skip resolver.
     */
    public function __construct(private PlanLimitEnforcer $planLimitEnforcer) {}

    /**
     * Resolve skip conditions for pull request run creation.
     */
    public function resolve(Repository $repository, ?string $requestedSkipReason): PullRequestRunSkipResolution
    {
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return new PullRequestRunSkipResolution(
                workspace: null,
                skipReason: 'Repository is not associated with any workspace.',
            );
        }

        if ($requestedSkipReason !== null) {
            return new PullRequestRunSkipResolution(
                workspace: $workspace,
                skipReason: $requestedSkipReason,
            );
        }

        $limitCheck = $this->planLimitEnforcer->ensureRunAllowed($workspace);

        if (! $limitCheck->allowed) {
            return new PullRequestRunSkipResolution(
                workspace: $workspace,
                skipReason: $limitCheck->message ?? 'Run limit reached.',
                planLimitTriggered: true,
                skipReasonCode: $limitCheck->code ?? 'plan_limit',
            );
        }

        return new PullRequestRunSkipResolution(
            workspace: $workspace,
            skipReason: null,
        );
    }
}
