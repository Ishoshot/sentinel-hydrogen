<?php

declare(strict_types=1);

namespace App\Services\Commands\ValueObjects;

/**
 * Metrics captured during command execution.
 */
final readonly class ExecutionMetrics
{
    /**
     * Create a new ExecutionMetrics instance.
     */
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public int $thinkingTokens,
        public int $cacheCreationInputTokens,
        public int $cacheReadInputTokens,
        public int $durationMs,
        public string $model,
        public string $provider,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{input_tokens: int, output_tokens: int, thinking_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int, duration_ms: int, model: string, provider: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            inputTokens: $data['input_tokens'],
            outputTokens: $data['output_tokens'],
            thinkingTokens: $data['thinking_tokens'] ?? 0,
            cacheCreationInputTokens: $data['cache_creation_input_tokens'] ?? 0,
            cacheReadInputTokens: $data['cache_read_input_tokens'] ?? 0,
            durationMs: $data['duration_ms'],
            model: $data['model'],
            provider: $data['provider'],
        );
    }

    /**
     * Get the total tokens used.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens + $this->thinkingTokens;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{input_tokens: int, output_tokens: int, thinking_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, duration_ms: int, model: string, provider: string}
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'thinking_tokens' => $this->thinkingTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
            'duration_ms' => $this->durationMs,
            'model' => $this->model,
            'provider' => $this->provider,
        ];
    }
}
