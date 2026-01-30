<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AiOptions\ListProviderAiOptions;
use App\Enums\AI\AiProvider;
use App\Http\Resources\AiOptionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * List available AI models for a provider.
 */
final class AiOptionController
{
    /**
     * List all active models for a provider.
     */
    public function __invoke(string $provider, ListProviderAiOptions $listProviderAiOptions): AnonymousResourceCollection
    {
        $aiProvider = AiProvider::tryFrom($provider);

        if ($aiProvider === null) {
            abort(404, 'Invalid provider');
        }

        $models = $listProviderAiOptions->handle($aiProvider);

        return AiOptionResource::collection($models);
    }
}
