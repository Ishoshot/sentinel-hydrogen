<?php

declare(strict_types=1);

namespace App\Services\Context\TokenCounting;

use App\Enums\AI\AiProvider;
use App\Services\Context\Contracts\TokenCounter;

/**
 * Delegates token counting to the provider-specific counter when available.
 */
final readonly class CompositeTokenCounter implements TokenCounter
{
    /**
     * Create a new composite token counter.
     */
    public function __construct(
        private OpenAiTokenCounter $openAi,
        private AnthropicTokenCounter $anthropic,
        private HeuristicTokenCounter $fallback,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function countTextTokens(string $text, TokenCounterContext $context): int
    {
        return $this->resolveCounter($context->provider)->countTextTokens($text, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function countMessageTokens(?string $systemPrompt, string $userPrompt, TokenCounterContext $context): int
    {
        return $this->resolveCounter($context->provider)->countMessageTokens($systemPrompt, $userPrompt, $context);
    }

    /**
     * Resolve the appropriate token counter for the provider.
     */
    private function resolveCounter(?AiProvider $provider): TokenCounter
    {
        return match ($provider) {
            AiProvider::OpenAI => $this->openAi,
            AiProvider::Anthropic => $this->anthropic,
            default => $this->fallback,
        };
    }
}
