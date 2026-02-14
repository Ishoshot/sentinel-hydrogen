<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Models\Installation;
use App\Models\Repository;
use App\Models\RepositorySettings;
use Illuminate\Support\Facades\DB;

final class PersistInstallationRepositories
{
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
            $existingRepoIds = $installation->repositories()->pluck('github_id')->toArray();
            $githubRepoIds = array_column($githubRepos, 'id');

            $added = 0;
            $updated = 0;

            foreach ($githubRepos as $repoData) {
                $repository = Repository::query()->updateOrCreate(
                    [
                        'installation_id' => $installation->id,
                        'github_id' => $repoData['id'],
                    ],
                    [
                        'workspace_id' => $installation->workspace_id,
                        'name' => $repoData['name'],
                        'full_name' => $repoData['full_name'],
                        'private' => $repoData['private'],
                        'default_branch' => $repoData['default_branch'] ?? 'main',
                        'language' => $repoData['language'] ?? null,
                        'description' => $repoData['description'] ?? null,
                    ]
                );

                if ($repository->wasRecentlyCreated) {
                    $added++;
                    $newlyCreatedRepositoryIds[] = $repository->id;

                    RepositorySettings::query()->create([
                        'repository_id' => $repository->id,
                        'workspace_id' => $installation->workspace_id,
                        'auto_review_enabled' => true,
                        'review_rules' => null,
                    ]);
                } else {
                    $updated++;
                }

                $syncedRepositoryIds[] = $repository->id;
            }

            $reposToRemove = array_diff($existingRepoIds, $githubRepoIds);
            /** @var int $removed */
            $removed = Repository::query()
                ->where('installation_id', $installation->id)
                ->whereIn('github_id', $reposToRemove)
                ->delete();

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
                $repository = Repository::query()->firstOrCreate(
                    [
                        'installation_id' => $installation->id,
                        'github_id' => $repoData['id'],
                    ],
                    [
                        'workspace_id' => $installation->workspace_id,
                        'name' => $repoData['name'],
                        'full_name' => $repoData['full_name'],
                        'private' => $repoData['private'],
                        'default_branch' => 'main',
                    ]
                );

                if ($repository->wasRecentlyCreated) {
                    $count++;

                    RepositorySettings::query()->create([
                        'repository_id' => $repository->id,
                        'workspace_id' => $installation->workspace_id,
                        'auto_review_enabled' => true,
                        'review_rules' => null,
                    ]);

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
        $githubIds = array_column($repositories, 'id');

        /** @var int $deleted */
        $deleted = Repository::query()
            ->where('installation_id', $installation->id)
            ->whereIn('github_id', $githubIds)
            ->delete();

        return $deleted;
    }
}
