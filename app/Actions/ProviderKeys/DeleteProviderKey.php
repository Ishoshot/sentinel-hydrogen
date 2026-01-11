<?php

declare(strict_types=1);

namespace App\Actions\ProviderKeys;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\AiProvider;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\User;

/**
 * Delete a provider key from a repository.
 */
final readonly class DeleteProviderKey
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Delete the provider key and log the activity.
     */
    public function handle(ProviderKey $providerKey, ?User $actor = null): void
    {
        $repository = $providerKey->repository;
        $provider = $providerKey->provider;

        $providerKey->delete();

        $this->logActivity($repository, $provider, $actor);
    }

    /**
     * Log the provider key deletion activity.
     */
    private function logActivity(?Repository $repository, AiProvider $provider, ?User $actor): void
    {
        if (! $repository instanceof Repository) {
            return;
        }

        $workspace = $repository->workspace;

        if ($workspace === null) {
            return;
        }

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::ProviderKeyDeleted,
            description: sprintf('%s API key removed from %s', $provider->value, $repository->full_name),
            actor: $actor,
            subject: $repository,
            metadata: ['provider' => $provider->value],
        );
    }
}
