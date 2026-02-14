<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Support;

use App\Models\Installation;
use App\Models\Repository;

final readonly class PushWebhookRepositoryResolution
{
    /**
     * Create a new resolution result.
     */
    public function __construct(
        public ?Installation $installation,
        public ?Repository $repository,
    ) {}
}
