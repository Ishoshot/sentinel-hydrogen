<?php

declare(strict_types=1);

namespace App\Actions\ProviderKeys;

use App\Actions\Activities\LogActivity;
use App\Enums\AI\AiProvider;
use App\Enums\Workspace\ActivityType;
use App\Models\ProviderKey;
use App\Models\User;
use App\Models\Workspace;
use SensitiveParameter;

/**
 * Store or update a workspace-level provider key (for briefings BYOK).
 *
 * Uses upsert pattern: creates a new key or updates existing for the same provider.
 */
final readonly class StoreWorkspaceProviderKey
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Store or update a workspace-level provider key.
     *
     * @param  string  $key  The API key (will be encrypted at storage)
     */
    public function handle(
        Workspace $workspace,
        AiProvider $provider,
        #[SensitiveParameter] string $key,
        ?User $actor = null,
    ): ProviderKey {
        $providerKey = ProviderKey::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'repository_id' => null,
                'provider' => $provider,
            ],
            [
                'encrypted_key' => $key,
            ]
        );

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::ProviderKeyUpdated,
            description: sprintf('Workspace-level %s API key configured', $provider->value),
            actor: $actor,
            subject: $workspace,
            metadata: ['provider' => $provider->value, 'scope' => 'workspace'],
        );

        return $providerKey->refresh();
    }
}
