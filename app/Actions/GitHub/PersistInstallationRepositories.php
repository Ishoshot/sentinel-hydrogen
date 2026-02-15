<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\GitHub\Handlers\InstallationRepositoryRecordHandler;
use App\Actions\GitHub\Handlers\InstallationRepositoryRemovalHandler;
use App\Models\Installation;
use Illuminate\Support\Facades\DB;

final readonly class PersistInstallationRepositories
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private InstallationRepositoryRecordHandler $recordPersister,
        private InstallationRepositoryRemovalHandler $repositoryRemover,
    ) {}

    /**
     * @param  array<int, array{id: int, name: string, full_name: string, private: bool, default_branch?: string, language?: string|null, description?: string|null}>  $githubRepos
     * @return array{added: int, updated: int, removed: int, synced_repository_ids: array<int>, newly_created_repository_ids: array<int>}
     */
    public function syncFromGitHub(Installation $installation, array $githubRepos): array
    {
        /** @var array<int> $syncedRepositoryIds */
        $syncedRepositoryIds = [];

        /** @var array<int> $newlyCreatedRepositoryIds */
        $newlyCreatedRepositoryIds = [];

        $result = DB::transaction(function () use ($installation, $githubRepos, &$syncedRepositoryIds, &$newlyCreatedRepositoryIds): array {
            /** @var array<int, int> $existingRepoIds */
            $existingRepoIds = array_map(
                static fn (mixed $githubId): int => (int) $githubId,
                $installation->repositories()->pluck('github_id')->toArray(),
            );
            /** @var array<int, int> $githubRepoIds */
            $githubRepoIds = array_map(
                static fn (mixed $githubId): int => $githubId,
                array_column($githubRepos, 'id'),
            );

            $added = 0;
            $updated = 0;

            foreach ($githubRepos as $repoData) {
                $repository = $this->recordPersister->persistForSync($installation, $repoData);

                if ($repository->wasRecentlyCreated) {
                    $added++;
                    $newlyCreatedRepositoryIds[] = $repository->id;
                } else {
                    $updated++;
                }

                $syncedRepositoryIds[] = $repository->id;
            }

            $removed = $this->repositoryRemover->removeMissingFromSync($installation, $existingRepoIds, $githubRepoIds);

            return [
                'added' => $added,
                'updated' => $updated,
                'removed' => $removed,
            ];
        });

        return [
            'added' => $result['added'],
            'updated' => $result['updated'],
            'removed' => $result['removed'],
            'synced_repository_ids' => $syncedRepositoryIds,
            'newly_created_repository_ids' => $newlyCreatedRepositoryIds,
        ];
    }

    /**
     * @param  array<int, array{id: int, name: string, full_name: string, private: bool}>  $repositories
     * @return array{added: int, added_repository_ids: array<int>}
     */
    public function addFromWebhook(Installation $installation, array $repositories): array
    {
        /** @var array<int> $addedRepositoryIds */
        $addedRepositoryIds = [];

        $added = DB::transaction(function () use ($installation, $repositories, &$addedRepositoryIds): int {
            $count = 0;

            foreach ($repositories as $repoData) {
                $repository = $this->recordPersister->persistFromWebhook($installation, $repoData);

                if ($repository->wasRecentlyCreated) {
                    $count++;
                    $addedRepositoryIds[] = $repository->id;
                }
            }

            return $count;
        });

        return [
            'added' => $added,
            'added_repository_ids' => $addedRepositoryIds,
        ];
    }

    /**
     * @param  array<int, array{id: int, name: string, full_name: string}>  $repositories
     */
    public function removeFromWebhook(Installation $installation, array $repositories): int
    {
        return $this->repositoryRemover->removeFromWebhook($installation, $repositories);
    }
}
