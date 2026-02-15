<?php

declare(strict_types=1);

namespace App\Services\GitHub\Clients;

use App\Services\GitHub\Policies\GitHubApiResponsePolicy;
use Closure;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubAppOperationClient
{
    /**
     * Create a new app operation invoker instance.
     */
    public function __construct(
        private GitHubApiRequestClient $requestExecutor,
        private GitHubApiResponsePolicy $responseGuard,
    ) {}

    /**
     * @param  Closure(GitHubManager): mixed  $operation
     * @return array<string, mixed>
     */
    public function map(string $operationName, Closure $operation): array
    {
        return $this->responseGuard->map($this->requestExecutor->runApp($operationName, $operation));
    }
}
