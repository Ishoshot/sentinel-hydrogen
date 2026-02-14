<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

final readonly class RunAnnotationContext
{
    /**
     * Create a new annotation context value object.
     */
    public function __construct(
        public string $owner,
        public string $repo,
        public int $pullRequestNumber,
        public int $installationId,
        public string $fullName,
    ) {}
}
