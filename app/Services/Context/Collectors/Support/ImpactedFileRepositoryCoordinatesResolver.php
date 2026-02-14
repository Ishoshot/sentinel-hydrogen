<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Models\Repository;
use App\Models\Run;

/**
 * Resolves repository and run coordinates needed for impacted file content fetches.
 */
final readonly class ImpactedFileRepositoryCoordinatesResolver
{
    /**
     * @return array{installation_id: int, owner: string, repo: string, head_sha: string|null}|null
     */
    public function resolve(Repository $repository, Run $run): ?array
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

        $metadata = $run->metadata ?? [];
        $headSha = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        return [
            'installation_id' => $installation->installation_id,
            'owner' => $owner,
            'repo' => $repo,
            'head_sha' => $headSha,
        ];
    }
}
