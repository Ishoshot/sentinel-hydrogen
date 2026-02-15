<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Resolvers;

use App\Actions\SentinelConfig\ValueObjects\SentinelConfigFetchTarget;
use App\Actions\SentinelConfig\ValueObjects\SentinelConfigFetchTargetResolution;
use App\Models\Repository;
use App\Support\RepositoryNameParser;

final readonly class SentinelConfigFetchTargetResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(private SentinelConfigFetchResultResolver $resultResolver) {}

    /**
     * Resolve installation/repository coordinates for sentinel config fetch.
     */
    public function resolve(Repository $repository, ?string $ref): SentinelConfigFetchTargetResolution
    {
        $installation = $repository->installation;

        if ($installation === null) {
            return new SentinelConfigFetchTargetResolution(
                target: null,
                failureResult: $this->resultResolver->failed('Repository has no installation'),
            );
        }

        $parsed = RepositoryNameParser::parse($repository->full_name);

        if (! is_array($parsed)) {
            return new SentinelConfigFetchTargetResolution(
                target: null,
                failureResult: $this->resultResolver->failed('Invalid repository full_name format'),
            );
        }

        ['owner' => $owner, 'repo' => $repo] = $parsed;

        return new SentinelConfigFetchTargetResolution(
            target: new SentinelConfigFetchTarget(
                installationId: $installation->installation_id,
                owner: $owner,
                repo: $repo,
                branch: $ref ?? $repository->default_branch,
            ),
            failureResult: null,
        );
    }
}
