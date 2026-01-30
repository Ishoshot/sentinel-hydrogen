<?php

declare(strict_types=1);

namespace App\Services\Reviews\ValueObjects;

/**
 * Resolved token limits for a specific provider model.
 */
final readonly class ModelLimits
{
    /**
     * Create a new ModelLimits instance.
     */
    public function __construct(
        public int $contextWindowTokens,
        public int $maxOutputTokens,
    ) {}
}
