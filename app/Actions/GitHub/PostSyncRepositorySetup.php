<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\SentinelConfig\SyncRepositorySentinelConfig;
use App\Jobs\GitHub\CreateConfigPullRequestJob;
use App\Models\Repository;

final readonly class PostSyncRepositorySetup
{
    /**
     * Create a new action instance.
     */
    public function __construct(private SyncRepositorySentinelConfig $syncSentinelConfig) {}

    /**
     * @param  array<int>  $syncedRepositoryIds
     * @param  array<int>  $newlyCreatedRepositoryIds
     */
    public function handle(array $syncedRepositoryIds, array $newlyCreatedRepositoryIds): void
    {
        if ($syncedRepositoryIds !== []) {
            $syncedRepositories = Repository::query()
                ->whereIn('id', $syncedRepositoryIds)
                ->with('settings')
                ->get();

            foreach ($syncedRepositories as $repository) {
                $this->syncSentinelConfig->handle($repository);
            }
        }

        foreach ($newlyCreatedRepositoryIds as $repositoryId) {
            CreateConfigPullRequestJob::dispatch($repositoryId)->delay(now()->addSeconds(10));
        }
    }
}
