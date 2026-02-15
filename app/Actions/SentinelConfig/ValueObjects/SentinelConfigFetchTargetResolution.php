<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\ValueObjects;

final readonly class SentinelConfigFetchTargetResolution
{
    /**
     * Create a new target resolution.
     *
     * @param  array{found: bool, content: ?string, sha: ?string, error: ?string}|null  $failureResult
     */
    public function __construct(
        public ?SentinelConfigFetchTarget $target,
        public ?array $failureResult,
    ) {}
}
