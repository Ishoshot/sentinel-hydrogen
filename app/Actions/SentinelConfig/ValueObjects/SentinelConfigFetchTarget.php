<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\ValueObjects;

final readonly class SentinelConfigFetchTarget
{
    /**
     * Create a new fetch target.
     */
    public function __construct(
        public int $installationId,
        public string $owner,
        public string $repo,
        public ?string $branch,
    ) {}
}
