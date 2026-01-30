<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\AI\AiProvider;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Reviews\Contracts\ProviderKeyResolver;

/**
 * Resolves BYOK provider keys for repositories.
 *
 * Keys are fetched from the database and cached in memory
 * for the duration of the request.
 */
final class ProviderKeyResolverService implements ProviderKeyResolver
{
    /**
     * Cache of provider keys keyed by repository ID and provider.
     *
     * @var array<int, array<string, ProviderKey>>
     */
    private array $cache = [];

    /**
     * Track which repositories have been loaded.
     *
     * @var array<int, bool>
     */
    private array $loaded = [];

    /**
     * {@inheritdoc}
     */
    public function resolve(Repository $repository, AiProvider $provider): ?string
    {
        $providerKey = $this->getProviderKey($repository, $provider);

        return $providerKey?->encrypted_key;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderKey(Repository $repository, AiProvider $provider): ?ProviderKey
    {
        $this->loadKeys($repository);

        return $this->cache[$repository->id][$provider->value] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstAvailableProvider(Repository $repository): ?AiProvider
    {
        $this->loadKeys($repository);

        // Priority: Anthropic > OpenAI
        if (isset($this->cache[$repository->id][AiProvider::Anthropic->value])) {
            return AiProvider::Anthropic;
        }

        if (isset($this->cache[$repository->id][AiProvider::OpenAI->value])) {
            return AiProvider::OpenAI;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableProviders(Repository $repository): array
    {
        $this->loadKeys($repository);
        $providers = [];

        // Maintain priority order: Anthropic > OpenAI
        if (isset($this->cache[$repository->id][AiProvider::Anthropic->value])) {
            $providers[] = AiProvider::Anthropic;
        }

        if (isset($this->cache[$repository->id][AiProvider::OpenAI->value])) {
            $providers[] = AiProvider::OpenAI;
        }

        return $providers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasProvider(Repository $repository, AiProvider $provider): bool
    {
        $this->loadKeys($repository);

        return isset($this->cache[$repository->id][$provider->value]);
    }

    /**
     * Load and cache provider keys for a repository.
     */
    private function loadKeys(Repository $repository): void
    {
        // Skip if already loaded for this repository
        if (isset($this->loaded[$repository->id])) {
            return;
        }

        $this->loaded[$repository->id] = true;
        $this->cache[$repository->id] = [];

        $keys = ProviderKey::query()
            ->with('providerModel')
            ->where('repository_id', $repository->id)
            ->get();

        foreach ($keys as $key) {
            $this->cache[$repository->id][$key->provider->value] = $key;
        }
    }
}
