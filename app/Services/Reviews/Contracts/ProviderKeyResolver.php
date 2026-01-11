<?php

declare(strict_types=1);

namespace App\Services\Reviews\Contracts;

use App\Enums\AiProvider;
use App\Models\Repository;

interface ProviderKeyResolver
{
    /**
     * Resolve the API key for a specific provider.
     *
     * @return string|null The decrypted API key, or null if not configured
     */
    public function resolve(Repository $repository, AiProvider $provider): ?string;

    /**
     * Get the first available provider with a configured key.
     *
     * @return AiProvider|null The provider, or null if no keys configured
     */
    public function getFirstAvailableProvider(Repository $repository): ?AiProvider;

    /**
     * Get all available providers with configured keys.
     *
     * @return array<int, AiProvider> List of providers with valid keys
     */
    public function getAvailableProviders(Repository $repository): array;

    /**
     * Check if a provider has a configured key.
     */
    public function hasProvider(Repository $repository, AiProvider $provider): bool;
}
