<?php

declare(strict_types=1);

namespace App\Actions\Admin\AiOptions;

use App\Enums\AiProvider;
use App\Models\AiOption;
use Illuminate\Support\Facades\DB;

/**
 * Update an existing AI option.
 */
final readonly class UpdateAiOption
{
    /**
     * Update an AI option.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(AiOption $aiOption, array $data): AiOption
    {
        return DB::transaction(function () use ($aiOption, $data): AiOption {
            $provider = $aiOption->provider;

            // If provider is being changed
            if (isset($data['provider'])) {
                $provider = $data['provider'] instanceof AiProvider
                    ? $data['provider']
                    : AiProvider::from($data['provider']);
            }

            // If this is set as default, unset other defaults for this provider
            if (($data['is_default'] ?? $aiOption->is_default) === true) {
                AiOption::query()
                    ->where('provider', $provider)
                    ->where('is_default', true)
                    ->where('id', '!=', $aiOption->id)
                    ->update(['is_default' => false]);
            }

            $aiOption->update([
                'provider' => $provider,
                'identifier' => $data['identifier'] ?? $aiOption->identifier,
                'name' => $data['name'] ?? $aiOption->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $aiOption->description,
                'is_default' => $data['is_default'] ?? $aiOption->is_default,
                'is_active' => $data['is_active'] ?? $aiOption->is_active,
                'sort_order' => $data['sort_order'] ?? $aiOption->sort_order,
            ]);

            return $aiOption->refresh();
        });
    }
}
