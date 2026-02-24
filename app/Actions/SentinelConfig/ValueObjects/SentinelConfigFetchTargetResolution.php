<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\ValueObjects;

final readonly class SentinelConfigFetchTargetResolution
{
    /**
     * Create a new target resolution.
     */
    public function __construct(
        public ?SentinelConfigFetchTarget $target,
        public ?ConfigFetchResult $failureResult,
    ) {}
}
