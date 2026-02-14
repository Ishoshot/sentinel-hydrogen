<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Support;

use App\Models\Installation;
use App\Models\Repository;

final class InstallationRepositoryRemover
{
    /**
     * Remove repositories no longer present during installation sync.
     *
     * @param  array<int, int>  $existingRepositoryGithubIds
     * @param  array<int, int>  $incomingRepositoryGithubIds
     */
    public function removeMissingFromSync(
        Installation $installation,
        array $existingRepositoryGithubIds,
        array $incomingRepositoryGithubIds,
    ): int {
        $repositoriesToRemove = array_diff($existingRepositoryGithubIds, $incomingRepositoryGithubIds);

        /** @var int $removed */
        $removed = Repository::query()
            ->where('installation_id', $installation->id)
            ->whereIn('github_id', $repositoriesToRemove)
            ->delete();

        return $removed;
    }

    /**
     * Remove repositories removed via installation_repositories webhook.
     *
     * @param  array<int, array{id: int, name: string, full_name: string}>  $repositories
     */
    public function removeFromWebhook(Installation $installation, array $repositories): int
    {
        $githubIds = array_column($repositories, 'id');

        /** @var int $deleted */
        $deleted = Repository::query()
            ->where('installation_id', $installation->id)
            ->whereIn('github_id', $githubIds)
            ->delete();

        return $deleted;
    }
}
