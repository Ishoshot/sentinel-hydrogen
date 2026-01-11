<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\SentinelConfig\SyncRepositorySentinelConfig;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Services\GitHub\GitHubApiService;
use Illuminate\Support\Facades\DB;

final readonly class SyncInstallationRepositories
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiService $gitHubApiService,
        private SyncRepositorySentinelConfig $syncSentinelConfig,
    ) {}

    /**
     * Sync repositories from GitHub for an installation.
     *
     * @return array{added: int, updated: int, removed: int}
     */
    public function handle(Installation $installation): array
    {
        $githubRepos = $this->gitHubApiService->getInstallationRepositories(
            $installation->installation_id
        );

        /** @var array<int, Repository> $syncedRepositories */
        $syncedRepositories = [];

        $result = DB::transaction(function () use ($installation, $githubRepos, &$syncedRepositories): array {
            $existingRepoIds = $installation->repositories()->pluck('github_id')->toArray();
            $githubRepoIds = array_column($githubRepos, 'id');

            $added = 0;
            $updated = 0;

            foreach ($githubRepos as $repoData) {
                $repository = Repository::updateOrCreate(
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
                        'language' => $repoData['language'],
                        'description' => $repoData['description'],
                    ]
                );

                if ($repository->wasRecentlyCreated) {
                    $added++;

                    // Create default repository settings
                    RepositorySettings::create([
                        'repository_id' => $repository->id,
                        'workspace_id' => $installation->workspace_id,
                        'auto_review_enabled' => true,
                        'review_rules' => null,
                    ]);
                } else {
                    $updated++;
                }

                $syncedRepositories[] = $repository;
            }

            // Remove repositories that are no longer accessible
            $reposToRemove = array_diff($existingRepoIds, $githubRepoIds);
            /** @var int $removed */
            $removed = Repository::where('installation_id', $installation->id)
                ->whereIn('github_id', $reposToRemove)
                ->delete();

            return [
                'added' => $added,
                'updated' => $updated,
                'removed' => $removed,
            ];
        });

        // Sync Sentinel configs for all repositories (outside transaction)
        foreach ($syncedRepositories as $repository) {
            $repository->refresh(); // Ensure settings relation is loaded
            $this->syncSentinelConfig->handle($repository);
        }

        return $result;
    }

    /**
     * Add repositories from a webhook event.
     *
     * @param  array<int, array{id: int, name: string, full_name: string, private: bool}>  $repositories
     * @return int Number of repositories added
     */
    public function addRepositories(Installation $installation, array $repositories): int
    {
        /** @var array<int, Repository> $addedRepositories */
        $addedRepositories = [];

        $added = DB::transaction(function () use ($installation, $repositories, &$addedRepositories): int {
            $count = 0;

            foreach ($repositories as $repoData) {
                $repository = Repository::firstOrCreate(
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

                    // Create default repository settings
                    RepositorySettings::create([
                        'repository_id' => $repository->id,
                        'workspace_id' => $installation->workspace_id,
                        'auto_review_enabled' => true,
                        'review_rules' => null,
                    ]);

                    $addedRepositories[] = $repository;
                }
            }

            return $count;
        });

        // Sync Sentinel configs for newly added repositories (outside transaction)
        foreach ($addedRepositories as $repository) {
            $repository->refresh();
            $this->syncSentinelConfig->handle($repository);
        }

        return $added;
    }

    /**
     * Remove repositories from a webhook event.
     *
     * @param  array<int, array{id: int, name: string, full_name: string}>  $repositories
     * @return int Number of repositories removed
     */
    public function removeRepositories(Installation $installation, array $repositories): int
    {
        $githubIds = array_column($repositories, 'id');

        /** @var int $deleted */
        $deleted = Repository::where('installation_id', $installation->id)
            ->whereIn('github_id', $githubIds)
            ->delete();

        return $deleted;
    }
}
