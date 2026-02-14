<?php

declare(strict_types=1);

namespace App\Services\GitHub\ValueObjects;

final readonly class RepositoryCoordinates
{
    /**
     * Create a new instance.
     */
    public function __construct(
        public int $installationId,
        public string $owner,
        public string $repo,
        public string $fullName,
    ) {}
}
