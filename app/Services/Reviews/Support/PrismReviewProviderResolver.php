<?php

declare(strict_types=1);

namespace App\Services\Reviews\Support;

use App\DataTransferObjects\SentinelConfig\ProviderConfig;
use App\Enums\AI\AiProvider;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use Prism\Prism\Enums\Provider;

final readonly class PrismReviewProviderResolver
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private ProviderKeyResolver $keyResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $policySnapshot
     */
    public function resolveProviderConfig(array $policySnapshot): ProviderConfig
    {
        if (isset($policySnapshot['provider']) && is_array($policySnapshot['provider'])) {
            /** @var array<string, mixed> $providerData */
            $providerData = $policySnapshot['provider'];

            return ProviderConfig::fromArray($providerData);
        }

        return ProviderConfig::default();
    }

    /**
     * @return array<int, AiProvider>
     */
    public function getProvidersToTry(Repository $repository, ProviderConfig $providerConfig): array
    {
        $availableProviders = $this->keyResolver->getAvailableProviders($repository);

        if ($availableProviders === []) {
            return [];
        }

        if ($providerConfig->preferred instanceof AiProvider) {
            $preferredProvider = $providerConfig->preferred;

            if ($this->keyResolver->hasProvider($repository, $preferredProvider)) {
                $fallbackProviders = array_filter($availableProviders, fn (AiProvider $provider): bool => $provider !== $preferredProvider);

                return [$preferredProvider, ...array_values($fallbackProviders)];
            }

            if (! $providerConfig->fallback) {
                return [];
            }
        }

        return $availableProviders;
    }

    /**
     * Map internal provider enum values to Prism providers.
     */
    public function mapToProvider(AiProvider $aiProvider): Provider
    {
        return match ($aiProvider) {
            AiProvider::Anthropic => Provider::Anthropic,
            AiProvider::OpenAI => Provider::OpenAI,
        };
    }

    /**
     * Resolve the workspace provider key for a specific AI provider.
     */
    public function getProviderKey(Repository $repository, AiProvider $aiProvider): ?ProviderKey
    {
        $providerKey = $this->keyResolver->getProviderKey($repository, $aiProvider);

        return $providerKey instanceof ProviderKey ? $providerKey : null;
    }

    /**
     * Resolve the model identifier to use for the selected provider.
     */
    public function resolveModel(AiProvider $aiProvider, ProviderConfig $providerConfig, ?ProviderKey $providerKey): string
    {
        if ($providerKey?->providerModel !== null) {
            return $providerKey->providerModel->identifier;
        }

        if ($providerConfig->model !== null && $providerConfig->preferred === $aiProvider) {
            return $providerConfig->model;
        }

        $defaultModel = AiOption::getDefault($aiProvider);
        if ($defaultModel instanceof AiOption) {
            return $defaultModel->identifier;
        }

        return match ($aiProvider) {
            AiProvider::Anthropic => 'claude-sonnet-4-5-20250929',
            AiProvider::OpenAI => 'gpt-4o',
        };
    }
}
