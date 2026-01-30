<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\SentinelConfig\SyncRepositorySentinelConfig;
use App\Jobs\GitHub\CreateConfigPullRequestJob;
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
                    $newlyCreatedRepositoryIds[] = $repository->id;

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

                $syncedRepositoryIds[] = $repository->id;
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

        // Batch load repositories with settings to avoid N+1 queries
        $syncedRepositories = Repository::query()
            ->whereIn('id', $syncedRepositoryIds)
            ->with('settings')
            ->get();

        // Sync Sentinel configs for all repositories (outside transaction)
        foreach ($syncedRepositories as $repository) {
            $this->syncSentinelConfig->handle($repository);
        }

        // Dispatch config PR creation jobs for newly created repositories
        // Add a delay to allow GitHub to propagate access permissions
        foreach ($newlyCreatedRepositoryIds as $repositoryId) {
            CreateConfigPullRequestJob::dispatch($repositoryId)->delay(now()->addSeconds(10));
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
        /** @var array<int> $addedRepositoryIds */
        $addedRepositoryIds = [];

        $added = DB::transaction(function () use ($installation, $repositories, &$addedRepositoryIds): int {
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

                    $addedRepositoryIds[] = $repository->id;
                }
            }

            return $count;
        });

        // Batch load repositories with settings to avoid N+1 queries
        if ($addedRepositoryIds !== []) {
            $addedRepositories = Repository::query()
                ->whereIn('id', $addedRepositoryIds)
                ->with('settings')
                ->get();

            // Sync Sentinel configs for newly added repositories (outside transaction)
            foreach ($addedRepositories as $repository) {
                $this->syncSentinelConfig->handle($repository);
            }

            // Dispatch config PR creation jobs for newly added repositories
            // Add a delay to allow GitHub to propagate access permissions
            foreach ($addedRepositoryIds as $repositoryId) {
                CreateConfigPullRequestJob::dispatch($repositoryId)->delay(now()->addSeconds(10));
            }
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
