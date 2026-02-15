<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Resolvers;

use App\Actions\Reviews\ValueObjects\RunAnnotationContext;
use App\Models\Run;

final readonly class RunAnnotationContextResolver
{
    /**
     * Resolve GitHub annotation posting context for a run.
     */
    public function resolve(Run $run): ?RunAnnotationContext
    {
        $repository = $run->repository;
        $installation = $repository?->installation;

        if ($repository === null || $installation === null) {
            return null;
        }

        $metadata = $run->metadata ?? [];
        $pullRequestNumber = $metadata['pull_request_number'] ?? null;

        if (! is_int($pullRequestNumber)) {
            return null;
        }

        $fullName = $repository->full_name;
        if ($fullName === null || ! str_contains($fullName, '/')) {
            return null;
        }

        [$owner, $repo] = explode('/', $fullName, 2);

        return new RunAnnotationContext(
            owner: $owner,
            repo: $repo,
            pullRequestNumber: $pullRequestNumber,
            installationId: $installation->installation_id,
            fullName: $fullName,
        );
    }
}
