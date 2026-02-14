<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Models\Installation;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;

final readonly class SyncInstallationRepositories
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private PersistInstallationRepositories $persistInstallationRepositories,
        private PostSyncRepositorySetup $postSyncRepositorySetup,
    ) {}

    /**
     * Sync repositories from GitHub for an installation.
     *
     * @return array{added: int, updated: int, removed: int}
     */
    public function handle(Installation $installation): array
    {
        $githubRepos = array_map(
            $this->normalizeRepository(...),
            $this->gitHubApiService->getInstallationRepositories($installation->installation_id),
        );

        $result = $this->persistInstallationRepositories->syncFromGitHub($installation, $githubRepos);

        $this->postSyncRepositorySetup->handle(
            $result['synced_repository_ids'],
            $result['newly_created_repository_ids'],
        );

        return [
            'added' => $result['added'],
            'updated' => $result['updated'],
            'removed' => $result['removed'],
        ];
    }

    /**
     * Add repositories from a webhook event.
     *
     * @param  array<int, array{id: int, name: string, full_name: string, private: bool}>  $repositories
     * @return int Number of repositories added
     */
    public function addRepositories(Installation $installation, array $repositories): int
    {
        $result = $this->persistInstallationRepositories->addFromWebhook($installation, $repositories);

        $this->postSyncRepositorySetup->handle(
            $result['added_repository_ids'],
            $result['added_repository_ids'],
        );

        return $result['added'];
    }

    /**
     * Remove repositories from a webhook event.
     *
     * @param  array<int, array{id: int, name: string, full_name: string}>  $repositories
     * @return int Number of repositories removed
     */
    public function removeRepositories(Installation $installation, array $repositories): int
    {
        return $this->persistInstallationRepositories->removeFromWebhook($installation, $repositories);
    }

    /**
     * @param  array<string, mixed>  $repository
     * @return array{id: int, name: string, full_name: string, private: bool, default_branch?: string, language?: string|null, description?: string|null}
     */
    private function normalizeRepository(array $repository): array
    {
        $normalized = [
            'id' => (int) ($repository['id'] ?? 0),
            'name' => (string) ($repository['name'] ?? ''),
            'full_name' => (string) ($repository['full_name'] ?? ''),
            'private' => (bool) ($repository['private'] ?? false),
        ];

        if (is_string($repository['default_branch'] ?? null) && $repository['default_branch'] !== '') {
            $normalized['default_branch'] = $repository['default_branch'];
        }

        if (is_string($repository['language'] ?? null) || $repository['language'] === null) {
            $normalized['language'] = $repository['language'];
        }

        if (is_string($repository['description'] ?? null) || $repository['description'] === null) {
            $normalized['description'] = $repository['description'];
        }

        return $normalized;
    }
}
