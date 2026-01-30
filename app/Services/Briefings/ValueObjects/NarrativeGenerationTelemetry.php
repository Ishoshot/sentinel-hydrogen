<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

/**
 * Telemetry captured during narrative generation.
 */
final readonly class NarrativeGenerationTelemetry
{
    /**
     * Create a new narrative generation telemetry record.
     */
    public function __construct(
        public string $provider,
        public string $model,
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public int $durationMs,
        public ?string $errorMessage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'duration_ms' => $this->durationMs,
            'error_message' => $this->errorMessage,
        ];
    }
}
