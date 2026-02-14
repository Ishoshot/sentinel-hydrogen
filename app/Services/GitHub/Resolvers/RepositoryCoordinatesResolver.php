<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use App\Models\Repository;
use App\Services\GitHub\ValueObjects\RepositoryCoordinates;

/**
 * Resolves owner/repo/install coordinates from a repository model.
 */
final readonly class RepositoryCoordinatesResolver
{
    /**
     * Resolve repository coordinates when installation and full name are available.
     */
    public function resolve(Repository $repository): ?RepositoryCoordinates
    {
        $repository->loadMissing('installation');

        $installation = $repository->installation;

        if ($installation === null) {
            return null;
        }

        $fullName = $repository->full_name ?? '';

        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            return null;
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);

        return new RepositoryCoordinates(
            installationId: $installation->installation_id,
            owner: $owner,
            repo: $repo,
            fullName: $fullName,
        );
    }
}
