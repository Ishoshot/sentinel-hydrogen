<?php

declare(strict_types=1);

namespace App\Actions\Admin\AiOptions;

use App\Enums\AiProvider;
use App\Models\AiOption;
use Illuminate\Support\Facades\DB;

/**
 * Create a new AI option.
 */
final readonly class CreateAiOption
{
    /**
     * Create a new AI option.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): AiOption
    {
        return DB::transaction(function () use ($data): AiOption {
            $provider = $data['provider'] instanceof AiProvider
                ? $data['provider']
                : AiProvider::from($data['provider']);

            // If this is set as default, unset other defaults for this provider
            if (($data['is_default'] ?? false) === true) {
                AiOption::query()
                    ->where('provider', $provider)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return AiOption::create([
                'provider' => $provider,
                'identifier' => $data['identifier'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
        });
    }
}
