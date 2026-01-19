<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Contracts;

use App\Models\Repository;

interface FetchesSentinelConfig
{
    /**
     * Fetch .sentinel/config.yaml from a repository.
     *
     * @param  Repository  $repository  The repository to fetch the config from
     * @param  string|null  $ref  The branch/ref to fetch from (defaults to repository's default branch)
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function handle(Repository $repository, ?string $ref = null): array;
}
