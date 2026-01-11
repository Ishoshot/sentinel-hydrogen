<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Contracts;

use App\Models\Repository;

interface FetchesSentinelConfig
{
    /**
     * Fetch .sentinel/config.yaml from a repository.
     *
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function handle(Repository $repository): array;
}
