<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\User;

final readonly class UpdateRepositorySettings
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Update repository settings.
     *
     * @param  array{auto_review_enabled?: bool}  $data
     */
    public function handle(Repository $repository, array $data, ?User $actor = null): RepositorySettings
    {
        $settings = $repository->settings;

        if ($settings === null) {
            $settings = RepositorySettings::create([
                'repository_id' => $repository->id,
                'workspace_id' => $repository->workspace_id,
                'auto_review_enabled' => $data['auto_review_enabled'] ?? true,
            ]);
        } else {
            $settings->update($data);
        }

        $workspace = $repository->workspace;

        if ($workspace !== null) {
            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::RepositorySettingsUpdated,
                description: 'Settings updated for '.$repository->full_name,
                actor: $actor,
                subject: $repository,
                metadata: $data,
            );
        }

        return $settings->refresh();
    }
}
