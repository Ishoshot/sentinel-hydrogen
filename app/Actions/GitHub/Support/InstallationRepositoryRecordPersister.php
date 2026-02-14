<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Support;

use App\Models\Installation;
use App\Models\Repository;
use App\Models\RepositorySettings;

final class InstallationRepositoryRecordPersister
{
    /**
     * Persist repository details during full installation sync.
     *
     * @param  array{id: int, name: string, full_name: string, private: bool, default_branch?: string, language?: string|null, description?: string|null}  $repoData
     */
    public function persistForSync(Installation $installation, array $repoData): Repository
    {
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
            $this->createDefaultSettings($repository, $installation->workspace_id);
        }

        return $repository;
    }

    /**
     * Persist repository details for repositories added via webhook.
     *
     * @param  array{id: int, name: string, full_name: string, private: bool}  $repoData
     */
    public function persistFromWebhook(Installation $installation, array $repoData): Repository
    {
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
            $this->createDefaultSettings($repository, $installation->workspace_id);
        }

        return $repository;
    }

    /**
     * Create default repository settings for newly persisted repositories.
     */
    private function createDefaultSettings(Repository $repository, int $workspaceId): void
    {
        RepositorySettings::query()->create([
            'repository_id' => $repository->id,
            'workspace_id' => $workspaceId,
            'auto_review_enabled' => true,
            'review_rules' => null,
        ]);
    }
}
