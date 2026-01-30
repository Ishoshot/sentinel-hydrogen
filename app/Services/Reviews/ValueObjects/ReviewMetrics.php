<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Metrics from a code review execution.
 */
final readonly class ReviewMetrics
{
    /**
     * Create a new ReviewMetrics instance.
     */
    public function __construct(
        public int $filesChanged,
        public int $linesAdded,
        public int $linesDeleted,
        public int $inputTokens,
        public int $outputTokens,
        public int $tokensUsedEstimated,
        public string $model,
        public string $provider,
        public int $durationMs,
    ) {}

    /**
     * Create from array.
     *
     * @param  array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filesChanged: $data['files_changed'],
            linesAdded: $data['lines_added'],
            linesDeleted: $data['lines_deleted'],
            inputTokens: $data['input_tokens'],
            outputTokens: $data['output_tokens'],
            tokensUsedEstimated: $data['tokens_used_estimated'],
            model: $data['model'],
            provider: $data['provider'],
            durationMs: $data['duration_ms'],
        );
    }

    /**
     * Get total tokens used.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}
     */
    public function toArray(): array
    {
        return [
            'files_changed' => $this->filesChanged,
            'lines_added' => $this->linesAdded,
            'lines_deleted' => $this->linesDeleted,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'tokens_used_estimated' => $this->tokensUsedEstimated,
            'model' => $this->model,
            'provider' => $this->provider,
            'duration_ms' => $this->durationMs,
        ];
    }
}
