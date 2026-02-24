<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Contracts;

use App\Actions\SentinelConfig\ValueObjects\ConfigFetchResult;
use App\Models\Repository;

interface FetchesSentinelConfig
{
    /**
     * Fetch .sentinel/config.yaml from a repository.
     *
     * @param  Repository  $repository  The repository to fetch the config from
     * @param  string|null  $ref  The branch/ref to fetch from (defaults to repository's default branch)
     */
    public function handle(Repository $repository, ?string $ref = null): ConfigFetchResult;
}
