<?php

declare(strict_types=1);

namespace App\Actions\ProviderKeys;

use App\Models\AiOption;
use App\Models\ProviderKey;
use InvalidArgumentException;

/**
 * Update the AI model selection for an existing provider key.
 */
final class UpdateProviderKeyModel
{
    /**
     * Update the model selection for a provider key.
     *
     * @param  int|null  $providerModelId  The new model ID, or null to use default
     */
    public function handle(ProviderKey $providerKey, ?int $providerModelId): ProviderKey
    {
        // Validate provider model belongs to the key's provider
        if ($providerModelId !== null) {
            $aiOption = AiOption::query()
                ->where('id', $providerModelId)
                ->where('provider', $providerKey->provider)
                ->where('is_active', true)
                ->first();

            if ($aiOption === null) {
                throw new InvalidArgumentException('Invalid model selected for this provider.');
            }
        }

        $providerKey->update([
            'provider_model_id' => $providerModelId,
        ]);

        return $providerKey->refresh();
    }
}
