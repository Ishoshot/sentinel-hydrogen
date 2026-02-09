<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Enums\AI\AiProvider;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingAiConfiguration;
use RuntimeException;

/**
 * Resolves workspace-level BYOK keys and AI configuration for briefing generation.
 */
final readonly class BriefingProviderKeyResolver
{
    /**
     * Resolve the decrypted API key for a specific provider.
     */
    public function resolve(Workspace $workspace, AiProvider $provider): ?string
    {
        $key = ProviderKey::query()
            ->where('workspace_id', $workspace->id)
            ->workspaceLevel()
            ->where('provider', $provider)
            ->first();

        return $key?->encrypted_key;
    }

    /**
     * Check if a workspace has any workspace-level BYOK key.
     */
    public function hasAnyKey(Workspace $workspace): bool
    {
        return ProviderKey::query()
            ->where('workspace_id', $workspace->id)
            ->workspaceLevel()
            ->exists();
    }

    /**
     * Resolve the full AI configuration for briefing generation.
     *
     * BYOK workspaces get their own provider + key with model from DB defaults.
     * Non-BYOK workspaces get the platform provider + model with no key (platform key).
     */
    public function resolveConfiguration(Workspace $workspace): BriefingAiConfiguration
    {
        // Try BYOK: Anthropic first, then OpenAI
        $fallbackOrder = [AiProvider::Anthropic, AiProvider::OpenAI];

        foreach ($fallbackOrder as $provider) {
            $key = $this->resolve($workspace, $provider);

            if ($key !== null) {
                return new BriefingAiConfiguration(
                    provider: $provider,
                    model: $this->resolveModel($provider),
                    apiKey: $key,
                    isByok: true,
                );
            }
        }

        // No BYOK key — use platform config
        $platformProvider = (string) config('briefings.platform.provider');
        $aiProvider = AiProvider::tryFrom($platformProvider);

        if ($aiProvider === null) {
            throw new RuntimeException(sprintf('Invalid briefings platform provider: %s', $platformProvider));
        }

        return new BriefingAiConfiguration(
            provider: $aiProvider,
            model: $this->resolveModel($aiProvider),
            apiKey: null,
            isByok: false,
        );
    }

    /**
     * Resolve the default model identifier for a provider.
     */
    private function resolveModel(AiProvider $provider): string
    {
        return AiOption::getDefault($provider)?->identifier ?? match ($provider) {
            AiProvider::Anthropic => 'claude-sonnet-4-5-20250929',
            AiProvider::OpenAI => 'gpt-4o',
        };
    }
}
