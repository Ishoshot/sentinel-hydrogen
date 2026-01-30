<?php

declare(strict_types=1);

namespace App\Actions\AiOptions;

use App\Enums\AI\AiProvider;
use App\Models\AiOption;
use Illuminate\Support\Collection;

/**
 * List active AI options for a specific provider.
 */
final class ListProviderAiOptions
{
    /**
     * Get all active AI options for the given provider.
     *
     * @return Collection<int, AiOption>
     */
    public function handle(AiProvider $provider): Collection
    {
        return AiOption::query()
            ->forProvider($provider)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
