<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

/**
 * Result of narrative generation.
 */
final readonly class NarrativeGenerationResult
{
    /**
     * Create a new narrative generation result.
     */
    public function __construct(
        public string $text,
        public NarrativeGenerationTelemetry $telemetry,
    ) {}
}
