<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SentinelConfig;

use App\Enums\AI\AiProvider;

/**
 * Configuration for AI provider preferences.
 */
final readonly class ProviderConfig
{
    /**
     * Create a new ProviderConfig instance.
     *
     * @param  AiProvider|null  $preferred  The preferred AI provider to use
     * @param  string|null  $model  The specific model to use (e.g., claude-3-5-sonnet, gpt-4o)
     * @param  bool  $fallback  Whether to fallback to other providers on failure
     */
    public function __construct(
        public ?AiProvider $preferred = null,
        public ?string $model = null,
        public bool $fallback = false,
    ) {}

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $preferred = null;

        if (isset($data['preferred']) && is_string($data['preferred'])) {
            $preferred = AiProvider::tryFrom($data['preferred']);
        }

        return new self(
            preferred: $preferred,
            model: isset($data['model']) && is_string($data['model']) ? $data['model'] : null,
            fallback: (bool) ($data['fallback'] ?? false),
        );
    }

    /**
     * Create default configuration.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'preferred' => $this->preferred?->value,
            'model' => $this->model,
            'fallback' => $this->fallback,
        ];
    }
}
