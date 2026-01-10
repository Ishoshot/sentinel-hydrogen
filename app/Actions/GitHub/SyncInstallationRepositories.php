<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

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
        private GitHubApiService $gitHubApiService
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

        return DB::transaction(function () use ($installation, $githubRepos): array {
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
    }

    /**
     * Add repositories from a webhook event.
     *
     * @param  array<int, array{id: int, name: string, full_name: string, private: bool}>  $repositories
     * @return int Number of repositories added
     */
    public function addRepositories(Installation $installation, array $repositories): int
    {
        return DB::transaction(function () use ($installation, $repositories): int {
            $added = 0;

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
                    $added++;

                    // Create default repository settings
                    RepositorySettings::create([
                        'repository_id' => $repository->id,
                        'workspace_id' => $installation->workspace_id,
                        'auto_review_enabled' => true,
                        'review_rules' => null,
                    ]);
                }
            }

            return $added;
        });
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
