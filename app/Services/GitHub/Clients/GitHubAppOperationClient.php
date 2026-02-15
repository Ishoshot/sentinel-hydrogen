<?php

declare(strict_types=1);

namespace App\Services\GitHub\Clients;

use App\Services\GitHub\Policies\GitHubApiResponsePolicy;
use Closure;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubAppOperationClient
{
    /**
     * Create a new app operation client instance.
     */
    public function __construct(
        private GitHubApiOperationClient $operationClient,
        private GitHubApiResponsePolicy $responsePolicy,
    ) {}

    /**
     * @param  Closure(GitHubManager): mixed  $operation
     * @return array<string, mixed>
     */
    public function map(string $operationName, Closure $operation): array
    {
        return $this->responsePolicy->map($this->operationClient->runWithAppAuthentication($operationName, $operation));
    }
}
