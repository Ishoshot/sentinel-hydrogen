<?php

declare(strict_types=1);

namespace App\Actions\ProviderKeys;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\AiProvider;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\User;
use SensitiveParameter;

/**
 * Store or update a provider key for a repository.
 *
 * Uses upsert pattern: creates a new key or updates existing for the same provider.
 */
final readonly class StoreProviderKey
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Store or update a provider key for the repository.
     *
     * @param  string  $key  The API key (will be encrypted at storage)
     */
    public function handle(
        Repository $repository,
        AiProvider $provider,
        #[SensitiveParameter] string $key,
        ?User $actor = null,
    ): ProviderKey {
        // Upsert: create or update existing key for this provider
        $providerKey = ProviderKey::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'provider' => $provider,
            ],
            [
                'workspace_id' => $repository->workspace_id,
                'encrypted_key' => $key,
            ]
        );

        $this->logActivity($repository, $provider, $actor);

        return $providerKey->refresh();
    }

    /**
     * Log the provider key configuration activity.
     */
    private function logActivity(Repository $repository, AiProvider $provider, ?User $actor): void
    {
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return;
        }

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::ProviderKeyUpdated,
            description: sprintf('%s API key configured for %s', $provider->value, $repository->full_name),
            actor: $actor,
            subject: $repository,
            metadata: ['provider' => $provider->value],
        );
    }
}
