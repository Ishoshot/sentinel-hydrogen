<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\AiProvider;
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
     * Cache of provider keys keyed by repository ID.
     *
     * @var array<int, array<string, string>>
     */
    private array $cache = [];

    /**
     * {@inheritdoc}
     */
    public function resolve(Repository $repository, AiProvider $provider): ?string
    {
        $keys = $this->loadKeys($repository);

        return $keys[$provider->value] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstAvailableProvider(Repository $repository): ?AiProvider
    {
        $keys = $this->loadKeys($repository);

        // Priority: Anthropic > OpenAI
        if (isset($keys[AiProvider::Anthropic->value])) {
            return AiProvider::Anthropic;
        }

        if (isset($keys[AiProvider::OpenAI->value])) {
            return AiProvider::OpenAI;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableProviders(Repository $repository): array
    {
        $keys = $this->loadKeys($repository);
        $providers = [];

        // Maintain priority order: Anthropic > OpenAI
        if (isset($keys[AiProvider::Anthropic->value])) {
            $providers[] = AiProvider::Anthropic;
        }

        if (isset($keys[AiProvider::OpenAI->value])) {
            $providers[] = AiProvider::OpenAI;
        }

        return $providers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasProvider(Repository $repository, AiProvider $provider): bool
    {
        $keys = $this->loadKeys($repository);

        return isset($keys[$provider->value]);
    }

    /**
     * Load and cache provider keys for a repository.
     *
     * @return array<string, string> Provider name => decrypted key mapping
     */
    private function loadKeys(Repository $repository): array
    {
        // Single query per repository, cached for request duration
        if (! isset($this->cache[$repository->id])) {
            /** @var array<string, string> $keys */
            $keys = ProviderKey::query()
                ->where('repository_id', $repository->id)
                ->pluck('encrypted_key', 'provider')
                ->toArray();

            $this->cache[$repository->id] = $keys;
        }

        return $this->cache[$repository->id];
    }
}
