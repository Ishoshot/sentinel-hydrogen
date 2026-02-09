<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use App\Enums\AI\AiProvider;

/**
 * Carries the fully-resolved AI provider, model, and key for a briefing generation.
 */
final readonly class BriefingAiConfiguration
{
    /**
     * Create a new briefing AI configuration.
     */
    public function __construct(
        public AiProvider $provider,
        public string $model,
        public ?string $apiKey,
        public bool $isByok,
    ) {}

    /**
     * Whether this configuration uses the platform-provided API key.
     */
    public function usesPlatformKey(): bool
    {
        return ! $this->isByok;
    }
}
