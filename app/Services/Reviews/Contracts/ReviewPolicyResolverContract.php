<?php

declare(strict_types=1);

namespace App\Services\Reviews\Contracts;

use App\Models\Repository;
use App\Services\Reviews\ValueObjects\ReviewPolicy;

/**
 * Contract for resolving review policy configuration.
 */
interface ReviewPolicyResolverContract
{
    /**
     * Resolve the review policy for a repository.
     *
     * @param  array<string, mixed>|null  $sentinelConfigData
     */
    public function resolve(
        Repository $repository,
        ?array $sentinelConfigData = null,
        ?string $configBranch = null
    ): ReviewPolicy;
}
