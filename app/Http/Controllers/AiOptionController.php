<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AiProvider;
use App\Http\Resources\AiOptionResource;
use App\Models\AiOption;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * List available AI models for a provider.
 */
final class AiOptionController
{
    /**
     * List all active models for a provider.
     */
    public function __invoke(string $provider): AnonymousResourceCollection
    {
        $aiProvider = AiProvider::tryFrom($provider);

        if ($aiProvider === null) {
            abort(404, 'Invalid provider');
        }

        $models = AiOption::query()
            ->forProvider($aiProvider)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return AiOptionResource::collection($models);
    }
}
