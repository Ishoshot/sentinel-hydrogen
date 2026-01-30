<?php

declare(strict_types=1);

namespace App\Services\Context\TokenCounting;

use App\Enums\AI\AiProvider;
use App\Enums\AI\TokenCountMode;

/**
 * Carries provider and mode details for token counting.
 */
final readonly class TokenCounterContext
{
    /**
     * Create a new token counter context.
     */
    public function __construct(
        public ?AiProvider $provider = null,
        public ?string $model = null,
        public TokenCountMode $mode = TokenCountMode::Estimate,
        public ?string $apiKey = null,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function fromMetadata(array $metadata, TokenCountMode $mode = TokenCountMode::Estimate): self
    {
        $providerValue = $metadata['token_counter_provider'] ?? null;
        $provider = is_string($providerValue) ? AiProvider::tryFrom($providerValue) : null;
        $model = is_string($metadata['token_counter_model'] ?? null)
            ? $metadata['token_counter_model']
            : null;

        return new self($provider, $model, $mode);
    }

    /**
     * Return a copy of the context with a different counting mode.
     */
    public function withMode(TokenCountMode $mode, ?string $apiKey = null): self
    {
        return new self(
            $this->provider,
            $this->model,
            $mode,
            $apiKey ?? $this->apiKey
        );
    }
}
