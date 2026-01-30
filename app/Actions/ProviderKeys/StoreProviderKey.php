<?php

declare(strict_types=1);

namespace App\Actions\ProviderKeys;

use App\Actions\Activities\LogActivity;
use App\Enums\AI\AiProvider;
use App\Enums\Billing\PlanFeature;
use App\Enums\Workspace\ActivityType;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\User;
use App\Services\Plans\PlanLimitEnforcer;
use InvalidArgumentException;
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
        private PlanLimitEnforcer $planLimitEnforcer,
    ) {}

    /**
     * Store or update a provider key for the repository.
     *
     * @param  string  $key  The API key (will be encrypted at storage)
     * @param  int|null  $providerModelId  Optional ID of the selected AI model
     */
    public function handle(
        Repository $repository,
        AiProvider $provider,
        #[SensitiveParameter] string $key,
        ?User $actor = null,
        ?int $providerModelId = null,
    ): ProviderKey {
        $workspace = $repository->workspace;

        if ($workspace !== null) {
            $limitCheck = $this->planLimitEnforcer->ensureFeatureEnabled(
                $workspace,
                PlanFeature::ByokEnabled,
                'Bring Your Own Key is not available on your current plan.'
            );

            if (! $limitCheck->allowed) {
                throw new InvalidArgumentException($limitCheck->message ?? 'BYOK is not available.');
            }
        }

        // Validate provider model belongs to the selected provider
        if ($providerModelId !== null) {
            $aiOption = AiOption::query()
                ->where('id', $providerModelId)
                ->where('provider', $provider)
                ->where('is_active', true)
                ->first();

            if ($aiOption === null) {
                throw new InvalidArgumentException('Invalid model selected for this provider.');
            }
        }

        // Upsert: create or update existing key for this provider
        $providerKey = ProviderKey::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'provider' => $provider,
            ],
            [
                'workspace_id' => $repository->workspace_id,
                'encrypted_key' => $key,
                'provider_model_id' => $providerModelId,
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
