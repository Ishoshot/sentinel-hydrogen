<?php

declare(strict_types=1);

namespace App\Services\Commands\Resolvers;

use App\Enums\AI\AiProvider;
use App\Exceptions\NoProviderKeyException;
use App\Models\AiOption;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use Prism\Prism\Enums\Provider;

final readonly class CommandAgentProviderResolver
{
    private const int THINKING_BUDGET = 8192;

    /**
     * Create a new instance.
     */
    public function __construct(
        private ProviderKeyResolver $keyResolver,
    ) {}

    /**
     * Resolve the first available provider key supported by command execution.
     */
    public function resolveProviderKey(Repository $repository): ProviderKey
    {
        foreach ([AiProvider::Anthropic, AiProvider::OpenAI] as $aiProvider) {
            $providerKey = $this->keyResolver->getProviderKey($repository, $aiProvider);

            if ($providerKey instanceof ProviderKey) {
                return $providerKey;
            }
        }

        throw NoProviderKeyException::noProvidersConfigured();
    }

    /**
     * Map an internal AI provider enum to Prism's provider enum.
     */
    public function mapToProvider(AiProvider $aiProvider): Provider
    {
        return match ($aiProvider) {
            AiProvider::Anthropic => Provider::Anthropic,
            AiProvider::OpenAI => Provider::OpenAI,
        };
    }

    /**
     * Resolve the model identifier for the configured provider key.
     */
    public function resolveModel(AiProvider $aiProvider, ProviderKey $providerKey): string
    {
        if ($providerKey->providerModel !== null) {
            return $providerKey->providerModel->identifier;
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

    /**
     * @return array<string, mixed>
     */
    public function buildProviderOptions(AiProvider $aiProvider, bool $enableThinking): array
    {
        if ($aiProvider !== AiProvider::Anthropic) {
            return [];
        }

        $options = ['cacheType' => 'ephemeral'];

        if ($enableThinking) {
            $options['thinking'] = ['enabled' => true, 'budget_tokens' => self::THINKING_BUDGET];
        }

        return $options;
    }
}
